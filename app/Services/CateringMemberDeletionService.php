<?php

namespace App\Services;

use App\Exceptions\CateringMemberDeletionException;
use App\Models\CateringAttendance;
use App\Models\CateringBill;
use App\Models\CateringCredit;
use App\Models\CateringCreditAllocation;
use App\Models\CateringEmployeeAssignment;
use App\Models\CateringMember;
use App\Models\CateringMemberPeriod;
use App\Models\CateringPayment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CateringMemberDeletionService
{
    /**
     * Permanently delete members and every record exclusively owned by them.
     *
     * @param  array<int, int|string>  $memberIds
     */
    public function deleteMany(array $memberIds): int
    {
        $memberIds = $this->normaliseIds($memberIds);

        if ($memberIds === []) {
            return 0;
        }

        return DB::transaction(function () use ($memberIds): int {
            $lockedMemberIds = CateringMember::query()
                ->whereKey($memberIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id')
                ->all();

            if ($lockedMemberIds === []) {
                return 0;
            }

            $ownedBillIds = CateringBill::query()
                ->whereIn('catering_member_id', $lockedMemberIds)
                ->orderBy('id')
                ->pluck('id')
                ->all();

            $paymentIds = CateringPayment::query()
                ->where(function (Builder $query) use ($lockedMemberIds, $ownedBillIds): void {
                    $query->whereIn('catering_member_id', $lockedMemberIds);

                    if ($ownedBillIds !== []) {
                        $query->orWhereIn('catering_bill_id', $ownedBillIds);
                    }
                })
                ->orderBy('id')
                ->pluck('id')
                ->all();

            $creditIds = CateringCredit::query()
                ->where(function (Builder $query) use ($lockedMemberIds, $ownedBillIds, $paymentIds): void {
                    $query->whereIn('catering_member_id', $lockedMemberIds);

                    if ($paymentIds !== []) {
                        $query->orWhereIn('source_payment_id', $paymentIds);
                    }

                    if ($ownedBillIds !== []) {
                        $query->orWhereIn('source_bill_id', $ownedBillIds);
                    }
                })
                ->orderBy('id')
                ->pluck('id')
                ->all();

            $allocationCandidates = CateringCreditAllocation::query()
                ->where(function (Builder $query) use ($creditIds, $ownedBillIds): void {
                    if ($creditIds !== []) {
                        $query->whereIn('catering_credit_id', $creditIds);
                    } else {
                        $query->whereRaw('1 = 0');
                    }

                    if ($ownedBillIds !== []) {
                        $query->orWhereIn('catering_bill_id', $ownedBillIds);
                    }
                })
                ->orderBy('id')
                ->get(['id', 'catering_credit_id', 'catering_bill_id']);

            $creditIds = $this->normaliseIds([
                ...$creditIds,
                ...$allocationCandidates->pluck('catering_credit_id')->all(),
            ]);

            $creditReferences = CateringCredit::query()
                ->whereKey($creditIds)
                ->get(['id', 'catering_member_id', 'source_payment_id', 'source_bill_id']);

            $paymentIds = $this->normaliseIds([
                ...$paymentIds,
                ...$creditReferences->pluck('source_payment_id')->all(),
            ]);

            $paymentReferences = CateringPayment::query()
                ->whereKey($paymentIds)
                ->get(['id', 'catering_bill_id', 'catering_member_id']);

            $billIdsToLock = $this->normaliseIds([
                ...$ownedBillIds,
                ...$paymentReferences->pluck('catering_bill_id')->all(),
                ...$creditReferences->pluck('source_bill_id')->all(),
                ...$allocationCandidates->pluck('catering_bill_id')->all(),
            ]);

            $bills = CateringBill::query()
                ->whereKey($billIdsToLock)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'catering_member_id']);
            $payments = CateringPayment::query()
                ->whereKey($paymentIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'catering_bill_id', 'catering_member_id']);
            $credits = CateringCredit::query()
                ->whereKey($creditIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'catering_member_id', 'source_payment_id', 'source_bill_id']);
            $allocations = CateringCreditAllocation::query()
                ->whereKey($allocationCandidates->modelKeys())
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'catering_credit_id', 'catering_bill_id']);

            $billOwners = $bills->pluck('catering_member_id', 'id');
            $paymentsById = $payments->keyBy('id');
            $creditsById = $credits->keyBy('id');

            foreach ($payments as $payment) {
                if ((int) $billOwners->get($payment->catering_bill_id) !== $payment->catering_member_id) {
                    throw CateringMemberDeletionException::inconsistentFinancialOwnership();
                }
            }

            foreach ($credits as $credit) {
                $sourcePayment = $paymentsById->get($credit->source_payment_id);

                if (
                    $sourcePayment === null
                    || $sourcePayment->catering_member_id !== $credit->catering_member_id
                    || (int) $billOwners->get($credit->source_bill_id) !== $credit->catering_member_id
                    || $sourcePayment->catering_bill_id !== $credit->source_bill_id
                ) {
                    throw CateringMemberDeletionException::inconsistentFinancialOwnership();
                }
            }

            foreach ($allocations as $allocation) {
                $credit = $creditsById->get($allocation->catering_credit_id);

                if (
                    $credit === null
                    || (int) $billOwners->get($allocation->catering_bill_id) !== $credit->catering_member_id
                ) {
                    throw CateringMemberDeletionException::inconsistentFinancialOwnership();
                }
            }

            CateringCreditAllocation::query()->whereKey($allocations->modelKeys())->delete();
            CateringCredit::query()->whereKey($credits->modelKeys())->delete();
            CateringPayment::query()->whereKey($payments->modelKeys())->delete();
            CateringBill::query()->whereKey($ownedBillIds)->delete();
            CateringAttendance::query()->whereIn('catering_member_id', $lockedMemberIds)->delete();
            CateringEmployeeAssignment::query()->whereIn('catering_member_id', $lockedMemberIds)->delete();
            CateringMemberPeriod::query()->whereIn('catering_member_id', $lockedMemberIds)->delete();
            CateringMember::query()->whereKey($lockedMemberIds)->delete();

            return count($lockedMemberIds);
        }, attempts: 5);
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return array<int, int>
     */
    private function normaliseIds(array $ids): array
    {
        $normalisedIds = collect($ids)
            ->filter(fn (mixed $id): bool => is_numeric($id) && (int) $id > 0)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $normalisedIds;
    }
}
