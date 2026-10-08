<?php

namespace App\Services;

use App\Models\CateringBill;
use App\Models\CateringMember;
use App\Models\CateringPayment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CateringPaymentService
{
    public function __construct(private CateringFinancialReconciliationService $financialReconciliation) {}

    /**
     * Record one payment transaction and rebuild the member's financial state.
     */
    public function record(
        CateringBill $bill,
        int $amount,
        ?User $actor = null,
        ?string $note = null,
        ?CarbonInterface $paidAt = null,
    ): CateringPayment {
        $this->validateAmount($amount);

        return DB::transaction(function () use ($bill, $amount, $actor, $note, $paidAt): CateringPayment {
            CateringMember::query()->whereKey($bill->catering_member_id)->lockForUpdate()->firstOrFail();
            $lockedBill = CateringBill::query()->lockForUpdate()->findOrFail($bill->id);
            $paidAmount = (int) $lockedBill->payments()->sum('amount');
            $creditApplied = (int) $lockedBill->creditAllocations()->sum('amount');
            $remaining = max(0, $lockedBill->gross_amount - $paidAmount - $creditApplied);

            if ($remaining === 0) {
                throw ValidationException::withMessages([
                    'paymentAmount' => 'Tagihan ini sudah lunas.',
                ]);
            }

            $this->financialReconciliation->clearDerivedStateForLockedMembers([$lockedBill->catering_member_id]);

            $payment = $lockedBill->payments()->create([
                'catering_member_id' => $lockedBill->catering_member_id,
                'amount' => $amount,
                'paid_at' => $paidAt ?? now(),
                'note' => $note,
                'created_by' => $actor?->id,
            ]);

            $this->financialReconciliation->rebuildLockedMembers([$lockedBill->catering_member_id]);

            return $payment->fresh(['generatedCredit']);
        }, attempts: 5);
    }

    public function update(
        CateringPayment $payment,
        int $amount,
        CarbonInterface $paidAt,
        ?string $note = null,
    ): CateringPayment {
        $this->validateAmount($amount);

        return DB::transaction(function () use ($payment, $amount, $paidAt, $note): CateringPayment {
            CateringMember::query()->whereKey($payment->catering_member_id)->lockForUpdate()->firstOrFail();
            $lockedPayment = CateringPayment::query()->lockForUpdate()->findOrFail($payment->id);

            $this->financialReconciliation->clearDerivedStateForLockedMembers([$lockedPayment->catering_member_id]);
            $lockedPayment->update([
                'amount' => $amount,
                'paid_at' => $paidAt,
                'note' => $note,
            ]);
            $this->financialReconciliation->rebuildLockedMembers([$lockedPayment->catering_member_id]);

            return $lockedPayment->fresh(['generatedCredit']);
        }, attempts: 5);
    }

    public function delete(CateringPayment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            CateringMember::query()->whereKey($payment->catering_member_id)->lockForUpdate()->firstOrFail();
            $lockedPayment = CateringPayment::query()->lockForUpdate()->findOrFail($payment->id);

            $this->financialReconciliation->clearDerivedStateForLockedMembers([$lockedPayment->catering_member_id]);
            $memberId = $lockedPayment->catering_member_id;
            $lockedPayment->delete();
            $this->financialReconciliation->rebuildLockedMembers([$memberId]);
        }, attempts: 5);
    }

    private function validateAmount(int $amount): void
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'paymentAmount' => 'Nominal pembayaran harus lebih dari 0.',
            ]);
        }
    }
}
