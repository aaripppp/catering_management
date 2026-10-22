<?php

namespace App\Services;

use App\Models\CateringAttendance;
use App\Models\CateringBill;
use App\Models\CateringCredit;
use App\Models\CateringCreditAllocation;
use App\Models\CateringMember;
use App\Models\CateringPayment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CateringBillResetService
{
    /**
     * @return array{
     *     bill_count: int,
     *     attendance_count: int,
     *     payment_count: int,
     *     generated_credit_count: int,
     *     allocation_count: int,
     *     financial_dependency_count: int,
     *     can_reset: bool
     * }
     */
    public function preview(int $year, int $month): array
    {
        $billIds = $this->periodBills($year, $month)->select('id');
        $sourceCreditIds = CateringCredit::query()
            ->whereIn('source_bill_id', clone $billIds)
            ->select('id');
        $paymentCount = CateringPayment::query()
            ->whereIn('catering_bill_id', clone $billIds)
            ->count();
        $generatedCreditCount = CateringCredit::query()
            ->whereIn('source_bill_id', clone $billIds)
            ->count();
        $allocationCount = CateringCreditAllocation::query()
            ->where(function (Builder $query) use ($billIds, $sourceCreditIds): void {
                $query->whereIn('catering_bill_id', clone $billIds)
                    ->orWhereIn('catering_credit_id', clone $sourceCreditIds);
            })
            ->count();

        return $this->summary(
            year: $year,
            month: $month,
            billCount: $this->periodBills($year, $month)->count(),
            paymentCount: $paymentCount,
            generatedCreditCount: $generatedCreditCount,
            allocationCount: $allocationCount,
        );
    }

    /**
     * @return array{
     *     bill_count: int,
     *     attendance_count: int,
     *     payment_count: int,
     *     generated_credit_count: int,
     *     allocation_count: int,
     *     financial_dependency_count: int,
     *     can_reset: bool,
     *     deleted_count: int
     * }
     */
    public function reset(int $year, int $month): array
    {
        return DB::transaction(function () use ($year, $month): array {
            CateringMember::query()
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            $billIds = $this->periodBills($year, $month)
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id');

            if ($billIds->isEmpty()) {
                return $this->summary($year, $month, 0, 0, 0, 0) + ['deleted_count' => 0];
            }

            $paymentIds = CateringPayment::query()
                ->whereIn('catering_bill_id', $billIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id');
            $creditIds = CateringCredit::query()
                ->whereIn('source_bill_id', $billIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id');
            $allocationIds = CateringCreditAllocation::query()
                ->where(function (Builder $query) use ($billIds, $creditIds): void {
                    $query->whereIn('catering_bill_id', $billIds)
                        ->when(
                            $creditIds->isNotEmpty(),
                            fn (Builder $query): Builder => $query->orWhereIn('catering_credit_id', $creditIds),
                        );
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id');
            $summary = $this->summary(
                year: $year,
                month: $month,
                billCount: $billIds->count(),
                paymentCount: $paymentIds->count(),
                generatedCreditCount: $creditIds->count(),
                allocationCount: $allocationIds->count(),
            );

            if (! $summary['can_reset']) {
                return $summary + ['deleted_count' => 0];
            }

            $deletedCount = $this->periodBills($year, $month)->delete();

            return $summary + ['deleted_count' => $deletedCount];
        }, attempts: 5);
    }

    /**
     * @return array{
     *     bill_count: int,
     *     attendance_count: int,
     *     payment_count: int,
     *     generated_credit_count: int,
     *     allocation_count: int,
     *     financial_dependency_count: int,
     *     can_reset: bool
     * }
     */
    private function summary(
        int $year,
        int $month,
        int $billCount,
        int $paymentCount,
        int $generatedCreditCount,
        int $allocationCount,
    ): array {
        $start = CarbonImmutable::create($year, $month, 1)->startOfMonth();
        $financialDependencyCount = $paymentCount + $generatedCreditCount + $allocationCount;

        return [
            'bill_count' => $billCount,
            'attendance_count' => CateringAttendance::query()
                ->where('attendance_date', '>=', $start->toDateString())
                ->where('attendance_date', '<', $start->addMonth()->toDateString())
                ->count(),
            'payment_count' => $paymentCount,
            'generated_credit_count' => $generatedCreditCount,
            'allocation_count' => $allocationCount,
            'financial_dependency_count' => $financialDependencyCount,
            'can_reset' => $financialDependencyCount === 0,
        ];
    }

    /** @return Builder<CateringBill> */
    private function periodBills(int $year, int $month): Builder
    {
        return CateringBill::query()
            ->where('period_year', $year)
            ->where('period_month', $month);
    }
}
