<?php

namespace App\Services;

use App\Enums\CateringBillStatus;
use App\Models\CateringBill;
use App\Models\CateringCredit;
use App\Models\CateringCreditAllocation;
use App\Models\CateringMember;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class CateringFinancialReconciliationService
{
    /**
     * Rebuild derived credits, allocations, and bill statuses from payment transactions.
     *
     * @param  array<int, int>  $memberIds
     */
    public function reconcileMembers(array $memberIds): int
    {
        $memberIds = array_values(array_unique(array_map('intval', $memberIds)));
        sort($memberIds);

        if ($memberIds === []) {
            return 0;
        }

        return DB::transaction(function () use ($memberIds): int {
            CateringMember::query()
                ->whereKey($memberIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            $this->clearDerivedStateForLockedMembers($memberIds);

            return $this->rebuildLockedMembers($memberIds);
        }, attempts: 5);
    }

    /**
     * Remove projections before a payment mutation inside an existing transaction.
     *
     * @param  array<int, int>  $memberIds
     */
    public function clearDerivedStateForLockedMembers(array $memberIds): void
    {
        $creditIds = CateringCredit::query()
            ->whereIn('catering_member_id', $memberIds)
            ->lockForUpdate()
            ->pluck('id');

        if ($creditIds->isNotEmpty()) {
            CateringCreditAllocation::query()
                ->whereIn('catering_credit_id', $creditIds)
                ->delete();

            CateringCredit::query()->whereKey($creditIds)->delete();
        }
    }

    /**
     * Recreate financial projections after the caller has locked members and mutated payments.
     *
     * @param  array<int, int>  $memberIds
     */
    public function rebuildLockedMembers(array $memberIds): int
    {
        $bills = CateringBill::query()
            ->whereIn('catering_member_id', $memberIds)
            ->with([
                'payments' => fn (HasMany $query): HasMany => $query
                    ->orderBy('paid_at')
                    ->orderBy('id')
                    ->lockForUpdate(),
            ])
            ->orderBy('catering_member_id')
            ->orderBy('period_year')
            ->orderBy('period_month')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $now = now();
        $creditRows = [];
        $allocationStates = [];
        $statusIds = array_fill_keys(array_map(
            fn (CateringBillStatus $status): string => $status->value,
            CateringBillStatus::cases(),
        ), []);
        $totalApplied = 0;

        foreach ($bills->groupBy('catering_member_id') as $memberBills) {
            $availableCreditPaymentIds = [];
            $creditsByPayment = [];

            foreach ($memberBills as $bill) {
                $remainingBill = $bill->gross_amount;
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
                        'catering_bill_id' => $bill->id,
                        'amount' => $amount,
                    ];
                    $creditsByPayment[$sourcePaymentId]['remaining_amount'] -= $amount;
                    $remainingBill -= $amount;
                    $creditApplied += $amount;
                    $totalApplied += $amount;
                }

                $paidAmount = 0;

                foreach ($bill->payments as $payment) {
                    $remainingBeforePayment = max(0, $bill->gross_amount - $creditApplied - $paidAmount);
                    $paidAmount += $payment->amount;
                    $excess = max(0, $payment->amount - $remainingBeforePayment);

                    if ($excess > 0) {
                        $creditsByPayment[$payment->id] = [
                            'catering_member_id' => $bill->catering_member_id,
                            'source_payment_id' => $payment->id,
                            'source_bill_id' => $bill->id,
                            'original_amount' => $excess,
                            'remaining_amount' => $excess,
                        ];
                        $availableCreditPaymentIds[] = $payment->id;
                    }
                }

                $status = CateringBill::statusFor($bill->gross_amount, $creditApplied + $paidAmount);
                $statusIds[$status->value][] = $bill->id;
            }

            $creditRows = [...$creditRows, ...array_values($creditsByPayment)];
        }

        if ($creditRows !== []) {
            CateringCredit::query()->insert(array_map(
                fn (array $credit): array => $credit + ['created_at' => $now, 'updated_at' => $now],
                $creditRows,
            ));
        }

        if ($allocationStates !== []) {
            $creditIdsByPayment = CateringCredit::query()
                ->whereIn('source_payment_id', array_column($allocationStates, 'source_payment_id'))
                ->pluck('id', 'source_payment_id');

            CateringCreditAllocation::query()->insert(array_map(
                fn (array $allocation): array => [
                    'catering_credit_id' => $creditIdsByPayment[$allocation['source_payment_id']],
                    'catering_bill_id' => $allocation['catering_bill_id'],
                    'amount' => $allocation['amount'],
                    'applied_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                $allocationStates,
            ));
        }

        foreach ($statusIds as $status => $billIds) {
            if ($billIds !== []) {
                CateringBill::query()->whereKey($billIds)->update([
                    'payment_status' => $status,
                    'updated_at' => $now,
                ]);
            }
        }

        return $totalApplied;
    }
}
