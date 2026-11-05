<?php

namespace App\Services;

use App\Enums\CateringUnitBillPaymentStatus;
use App\Enums\CateringUnitBillSyncStatus;
use App\Models\CateringEmployeeUnit;
use App\Models\CateringUnitBill;
use App\Models\CateringUnitPayment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records and summarizes payments for monthly unit bills.
 *
 * Unit payments are a separate domain from student payments: they never touch
 * catering_payments, student credits, or member foreign keys. Payments are
 * informational against the bill snapshot, so resynchronizing a bill only
 * changes the derived summary and never deletes existing payments.
 */
class CateringUnitPaymentService
{
    public function __construct(
        private CateringUnitBillingService $billingService,
        private CateringUnitFinancialReconciliationService $financialReconciliation,
    ) {}

    /**
     * New payments are only accepted against a synchronized bill, so the admin
     * always pays the latest known amount. Existing payments stay readable when
     * the source later becomes stale.
     */
    public function create(
        CateringUnitBill $bill,
        int $amount,
        ?User $actor = null,
        ?string $note = null,
        ?CarbonInterface $paidAt = null,
        ?string $paymentMethod = null,
    ): CateringUnitPayment {
        $this->validateAmount($amount);
        $this->ensureBillIsSynchronized($bill);

        return DB::transaction(function () use ($bill, $amount, $actor, $note, $paidAt, $paymentMethod): CateringUnitPayment {
            CateringEmployeeUnit::query()
                ->whereKey($bill->catering_employee_unit_id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedBill = CateringUnitBill::query()->lockForUpdate()->findOrFail($bill->id);

            $net = (int) ($lockedBill->net_amount ?? $lockedBill->gross_amount);
            $net = $net > 0 ? $net : max(0, (int) $lockedBill->gross_amount - (int) ($lockedBill->total_subsidy_amount ?? 0));

            if ($net === 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Tagihan unit ini tidak memiliki nilai tagihan.',
                ]);
            }

            $payment = $lockedBill->payments()->create([
                'amount' => $amount,
                'paid_at' => $paidAt ?? now(),
                'payment_method' => $paymentMethod,
                'note' => $note,
                'created_by' => $actor?->id,
            ]);

            $this->financialReconciliation->clearDerivedStateForLockedUnits([
                (int) $lockedBill->catering_employee_unit_id,
            ]);
            $this->financialReconciliation->rebuildLockedUnits([
                (int) $lockedBill->catering_employee_unit_id,
            ]);

            return $payment;
        }, attempts: 5);
    }

    public function update(
        CateringUnitPayment $payment,
        int $amount,
        CarbonInterface $paidAt,
        ?string $note = null,
        ?string $paymentMethod = null,
    ): CateringUnitPayment {
        $this->validateAmount($amount);

        return DB::transaction(function () use ($payment, $amount, $paidAt, $note, $paymentMethod): CateringUnitPayment {
            $unitId = $this->unitIdForPayment($payment);

            CateringEmployeeUnit::query()->whereKey($unitId)->lockForUpdate()->firstOrFail();
            $lockedPayment = CateringUnitPayment::query()->lockForUpdate()->findOrFail($payment->id);
            $lockedPayment->update([
                'amount' => $amount,
                'paid_at' => $paidAt,
                'payment_method' => $paymentMethod,
                'note' => $note,
            ]);

            $this->financialReconciliation->clearDerivedStateForLockedUnits([$unitId]);
            $this->financialReconciliation->rebuildLockedUnits([$unitId]);

            return $lockedPayment->fresh(['creator']);
        }, attempts: 5);
    }

    public function delete(CateringUnitPayment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $unitId = $this->unitIdForPayment($payment);

            CateringEmployeeUnit::query()->whereKey($unitId)->lockForUpdate()->firstOrFail();
            $lockedPayment = CateringUnitPayment::query()->lockForUpdate()->findOrFail($payment->id);
            $this->financialReconciliation->clearDerivedStateForLockedUnits([$unitId]);
            $lockedPayment->delete();
            $this->financialReconciliation->rebuildLockedUnits([$unitId]);
        }, attempts: 5);
    }

    /**
     * @return array{
     *     billed_amount: int,
     *     paid_amount: int,
     *     credit_applied_amount: int,
     *     recognized_paid_amount: int,
     *     remaining_amount: int,
     *     overpaid_amount: int,
     *     status: CateringUnitBillPaymentStatus,
     *     status_label: string
     * }
     */
    public function summaryForBill(CateringUnitBill $bill): array
    {
        $billedAmount = $bill->billedAmount();

        if ($billedAmount === 0) {
            $billedAmount = max(0, (int) $bill->gross_amount - (int) ($bill->total_subsidy_amount ?? 0));
        }

        return $this->summarize(
            $billedAmount,
            $this->paidAmountFor($bill),
            $this->creditAppliedFor($bill),
        );
    }

    /**
     * Credit applied to later bills reduces their outstanding balance, while a
     * source bill's overpayment is reported separately and never counted as
     * recognized settlement for that same bill.
     *
     * @return array{
     *     total_billed: int,
     *     total_paid: int,
     *     total_remaining: int,
     *     total_overpaid: int,
     *     paid_count: int,
     *     partial_count: int,
     *     unpaid_count: int
     * }
     */
    public function periodSummary(int $month, int $year): array
    {
        $paymentTotals = DB::table('catering_unit_payments')
            ->select('catering_unit_bill_id')
            ->selectRaw('SUM(amount) as paid_amount')
            ->groupBy('catering_unit_bill_id');
        $creditTotals = DB::table('catering_unit_credit_allocations')
            ->select('catering_unit_bill_id')
            ->selectRaw('SUM(amount) as credit_applied_amount')
            ->groupBy('catering_unit_bill_id');
        $periodBills = DB::table('catering_unit_bills')
            ->leftJoinSub($paymentTotals, 'payment_totals', function (JoinClause $join): void {
                $join->on('catering_unit_bills.id', '=', 'payment_totals.catering_unit_bill_id');
            })
            ->leftJoinSub($creditTotals, 'credit_totals', function (JoinClause $join): void {
                $join->on('catering_unit_bills.id', '=', 'credit_totals.catering_unit_bill_id');
            })
            ->where('catering_unit_bills.period_month', $month)
            ->where('catering_unit_bills.period_year', $year)
            ->selectRaw('COALESCE(catering_unit_bills.net_amount, catering_unit_bills.gross_amount) as billed_amount')
            ->selectRaw('COALESCE(payment_totals.paid_amount, 0) as paid_amount')
            ->selectRaw('COALESCE(credit_totals.credit_applied_amount, 0) as credit_applied_amount');
        $summary = DB::query()
            ->fromSub($periodBills, 'period_bills')
            ->selectRaw('COALESCE(SUM(billed_amount), 0) as total_billed')
            ->selectRaw('COALESCE(SUM(CASE WHEN paid_amount + credit_applied_amount >= billed_amount THEN billed_amount ELSE paid_amount + credit_applied_amount END), 0) as total_paid')
            ->selectRaw('COALESCE(SUM(CASE WHEN billed_amount > paid_amount + credit_applied_amount THEN billed_amount - paid_amount - credit_applied_amount ELSE 0 END), 0) as total_remaining')
            ->selectRaw('COALESCE(SUM(CASE WHEN paid_amount + credit_applied_amount > billed_amount THEN paid_amount + credit_applied_amount - billed_amount ELSE 0 END), 0) as total_overpaid')
            ->selectRaw('COALESCE(SUM(CASE WHEN paid_amount + credit_applied_amount = billed_amount THEN 1 ELSE 0 END), 0) as paid_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN paid_amount + credit_applied_amount > 0 AND paid_amount + credit_applied_amount < billed_amount THEN 1 ELSE 0 END), 0) as partial_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN paid_amount + credit_applied_amount = 0 AND billed_amount > 0 THEN 1 ELSE 0 END), 0) as unpaid_count')
            ->first();

        return [
            'total_billed' => (int) ($summary?->total_billed ?? 0),
            'total_paid' => (int) ($summary?->total_paid ?? 0),
            'total_remaining' => (int) ($summary?->total_remaining ?? 0),
            'total_overpaid' => (int) ($summary?->total_overpaid ?? 0),
            'paid_count' => (int) ($summary?->paid_count ?? 0),
            'partial_count' => (int) ($summary?->partial_count ?? 0),
            'unpaid_count' => (int) ($summary?->unpaid_count ?? 0),
        ];
    }

    /** @return Collection<int, CateringUnitPayment> */
    public function historyForBill(CateringUnitBill $bill): Collection
    {
        return $bill->payments()
            ->with('creator:id,name')
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get();
    }

    private function ensureBillIsSynchronized(CateringUnitBill $bill): void
    {
        if ($this->billingService->syncStatusForBill($bill) !== CateringUnitBillSyncStatus::Synced) {
            throw ValidationException::withMessages([
                'amount' => 'Sinkronkan Tagihan Unit terlebih dahulu sebelum mencatat pembayaran.',
            ]);
        }
    }

    private function paidAmountFor(CateringUnitBill $bill): int
    {
        $attributes = $bill->getAttributes();

        return array_key_exists('payments_sum_amount', $attributes)
            ? (int) $attributes['payments_sum_amount']
            : (int) $bill->payments()->sum('amount');
    }

    private function creditAppliedFor(CateringUnitBill $bill): int
    {
        $attributes = $bill->getAttributes();

        return array_key_exists('credit_allocations_sum_amount', $attributes)
            ? (int) $attributes['credit_allocations_sum_amount']
            : (int) $bill->creditAllocations()->sum('amount');
    }

    private function unitIdForPayment(CateringUnitPayment $payment): int
    {
        $billId = CateringUnitPayment::query()->whereKey($payment->id)->value('catering_unit_bill_id');

        return (int) CateringUnitBill::query()->whereKey($billId)->value('catering_employee_unit_id');
    }

    /**
     * @return array{
     *     billed_amount: int,
     *     paid_amount: int,
     *     credit_applied_amount: int,
     *     recognized_paid_amount: int,
     *     remaining_amount: int,
     *     overpaid_amount: int,
     *     status: CateringUnitBillPaymentStatus,
     *     status_label: string
     * }
     */
    private function summarize(int $billedAmount, int $paidAmount, int $creditApplied): array
    {
        $status = $this->statusFor($billedAmount, $paidAmount, $creditApplied);
        $recognizedPaid = min($billedAmount, $paidAmount + $creditApplied);

        return [
            'billed_amount' => $billedAmount,
            'paid_amount' => $paidAmount,
            'credit_applied_amount' => $creditApplied,
            'recognized_paid_amount' => $recognizedPaid,
            'remaining_amount' => max(0, $billedAmount - $paidAmount - $creditApplied),
            'overpaid_amount' => max(0, $paidAmount + $creditApplied - $billedAmount),
            'status' => $status,
            'status_label' => $status->label(),
        ];
    }

    private function statusFor(
        int $billedAmount,
        int $paidAmount,
        int $creditApplied = 0,
    ): CateringUnitBillPaymentStatus {
        if ($paidAmount + $creditApplied > $billedAmount) {
            return CateringUnitBillPaymentStatus::Overpaid;
        }

        if ($billedAmount === 0) {
            return CateringUnitBillPaymentStatus::Paid;
        }

        $recognizedPaid = min($billedAmount, $paidAmount + $creditApplied);

        if ($recognizedPaid === 0) {
            return CateringUnitBillPaymentStatus::Unpaid;
        }

        if ($recognizedPaid < $billedAmount) {
            return CateringUnitBillPaymentStatus::Partial;
        }

        return CateringUnitBillPaymentStatus::Paid;
    }

    private function validateAmount(int $amount): void
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Nominal pembayaran harus lebih dari 0.',
            ]);
        }
    }
}
