<?php

namespace App\Services;

use App\Models\CateringEmployeeUnit;
use App\Models\CateringUnitBill;
use App\Models\CateringUnitCredit;
use App\Models\CateringUnitCreditAllocation;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds persisted unit overpayment credits and their forward allocations.
 *
 * Credits are always derived from cash payments, then allocated to later bills
 * of the same unit in chronological order. The source bill can never receive
 * its own credit back, and a unit's credit can never reach another unit.
 */
class CateringUnitFinancialReconciliationService
{
    /**
     * @param  array<int, int>  $unitIds
     */
    public function reconcileUnits(array $unitIds): int
    {
        $unitIds = array_values(array_unique(array_map('intval', $unitIds)));
        sort($unitIds);

        if ($unitIds === []) {
            return 0;
        }

        return DB::transaction(function () use ($unitIds): int {
            CateringEmployeeUnit::query()
                ->whereKey($unitIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            $this->clearDerivedStateForLockedUnits($unitIds);

            return $this->rebuildLockedUnits($unitIds);
        }, attempts: 5);
    }

    public function reconcileAll(int $chunkSize = 100): int
    {
        $totalApplied = 0;

        CateringEmployeeUnit::query()
            ->orderBy('id')
            ->chunkById($chunkSize, function ($units) use (&$totalApplied): void {
                $totalApplied += $this->reconcileUnits($units->modelKeys());
            });

        return $totalApplied;
    }

    /**
     * Remove projections before a payment or bill mutation inside an existing
     * transaction. The caller must already have locked the unit rows.
     *
     * @param  array<int, int>  $unitIds
     */
    public function clearDerivedStateForLockedUnits(array $unitIds): void
    {
        $creditIds = CateringUnitCredit::query()
            ->whereIn('catering_employee_unit_id', $unitIds)
            ->lockForUpdate()
            ->pluck('id');

        if ($creditIds->isNotEmpty()) {
            CateringUnitCreditAllocation::query()
                ->whereIn('catering_unit_credit_id', $creditIds)
                ->delete();

            CateringUnitCredit::query()->whereKey($creditIds)->delete();
        }
    }

    /**
     * Recreate credits and allocations after the caller has locked the units
     * and mutated payments or bill snapshots.
     *
     * @param  array<int, int>  $unitIds
     */
    public function rebuildLockedUnits(array $unitIds): int
    {
        $bills = CateringUnitBill::query()
            ->whereIn('catering_employee_unit_id', $unitIds)
            ->with([
                'payments' => fn (HasMany $query): HasMany => $query
                    ->orderBy('paid_at')
                    ->orderBy('id')
                    ->lockForUpdate(),
            ])
            ->orderBy('catering_employee_unit_id')
            ->orderBy('period_year')
            ->orderBy('period_month')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $now = now();
        $creditRows = [];
        $allocationStates = [];
        $totalApplied = 0;

        foreach ($bills->groupBy('catering_employee_unit_id') as $unitBills) {
            $availableCreditPaymentIds = [];
            $creditsByPayment = [];

            foreach ($unitBills as $bill) {
                $billedAmount = $bill->billedAmount();
                $remainingBill = $billedAmount;
                $creditApplied = 0;

                foreach ($availableCreditPaymentIds as $sourcePaymentId) {
                    if ($remainingBill === 0) {
                        break;
                    }

                    $amount = min($remainingBill, $creditsByPayment[$sourcePaymentId]['remaining_amount']);

                    if ($amount === 0) {
                        continue;
                    }

                    $allocationStates[] = [
                        'source_payment_id' => $sourcePaymentId,
                        'catering_unit_bill_id' => $bill->id,
                        'amount' => $amount,
                    ];
                    $creditsByPayment[$sourcePaymentId]['remaining_amount'] -= $amount;
                    $remainingBill -= $amount;
                    $creditApplied += $amount;
                    $totalApplied += $amount;
                }

                $paidAmount = 0;

                foreach ($bill->payments as $payment) {
                    $remainingBeforePayment = max(0, $billedAmount - $creditApplied - $paidAmount);
                    $paidAmount += $payment->amount;
                    $excess = max(0, $payment->amount - $remainingBeforePayment);

                    if ($excess > 0) {
                        $creditsByPayment[$payment->id] = [
                            'catering_employee_unit_id' => $bill->catering_employee_unit_id,
                            'source_unit_payment_id' => $payment->id,
                            'source_unit_bill_id' => $bill->id,
                            'original_amount' => $excess,
                            'remaining_amount' => $excess,
                        ];
                        $availableCreditPaymentIds[] = $payment->id;
                    }
                }
            }

            $creditRows = [...$creditRows, ...array_values($creditsByPayment)];
        }

        if ($creditRows !== []) {
            CateringUnitCredit::query()->insert(array_map(
                fn (array $credit): array => $credit + ['created_at' => $now, 'updated_at' => $now],
                $creditRows,
            ));
        }

        if ($allocationStates !== []) {
            $creditIdsByPayment = CateringUnitCredit::query()
                ->whereIn('source_unit_payment_id', array_column($allocationStates, 'source_payment_id'))
                ->pluck('id', 'source_unit_payment_id');

            CateringUnitCreditAllocation::query()->insert(array_map(
                fn (array $allocation): array => [
                    'catering_unit_credit_id' => $creditIdsByPayment[$allocation['source_payment_id']],
                    'catering_unit_bill_id' => $allocation['catering_unit_bill_id'],
                    'amount' => $allocation['amount'],
                    'applied_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                $allocationStates,
            ));
        }

        return $totalApplied;
    }
}
