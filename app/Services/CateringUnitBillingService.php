<?php

namespace App\Services;

use App\Enums\CateringUnitBillSyncStatus;
use App\Models\CateringEmployeeUnit;
use App\Models\CateringUnitAttendance;
use App\Models\CateringUnitBill;
use App\Models\CateringUnitBillDay;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CateringUnitBillingService
{
    /**
     * @param array{
     *   employee_subsidy_enabled?: bool,
     *   employee_subsidy_rate?: int,
     *   student_subsidy_enabled?: bool,
     *   student_subsidy_qty?: int,
     *   student_subsidy_rate?: int
     * } $subsidy
     */
    public function updateSubsidy(CateringUnitBill $bill, array $subsidy): CateringUnitBill
    {
        $bill = CateringUnitBill::query()->lockForUpdate()->findOrFail($bill->id);

        $employeeEnabled = (bool) ($subsidy['employee_subsidy_enabled'] ?? false);
        $employeeRate = max(0, (int) ($subsidy['employee_subsidy_rate'] ?? 0));
        $employeeQty = (int) $bill->total_quantity;
        $employeeAmount = $employeeEnabled ? ($employeeQty * $employeeRate) : 0;

        $studentEnabled = (bool) ($subsidy['student_subsidy_enabled'] ?? false);
        $studentQty = max(0, (int) ($subsidy['student_subsidy_qty'] ?? 0));
        $studentRate = max(0, (int) ($subsidy['student_subsidy_rate'] ?? 0));
        $studentAmount = $studentEnabled ? ($studentQty * $studentRate) : 0;

        $totalSubsidy = $employeeAmount + $studentAmount;
        $netAmount = max(0, (int) $bill->gross_amount - $totalSubsidy);

        $bill->update([
            'employee_subsidy_enabled' => $employeeEnabled,
            'employee_subsidy_qty' => $employeeQty,
            'employee_subsidy_rate' => $employeeRate,
            'employee_subsidy_amount' => $employeeAmount,
            'student_subsidy_enabled' => $studentEnabled,
            'student_subsidy_qty' => $studentQty,
            'student_subsidy_rate' => $studentRate,
            'student_subsidy_amount' => $studentAmount,
            'total_subsidy_amount' => $totalSubsidy,
            'net_amount' => $netAmount,
        ]);

        return $bill->fresh();
    }

    public function ensureNetAmount(CateringUnitBill $bill): CateringUnitBill
    {
        return $this->recomputeNetForSync($bill);
    }

    private function recomputeNetForSync(CateringUnitBill $bill): CateringUnitBill
    {
        $employeeEnabled = (bool) $bill->employee_subsidy_enabled;
        $employeeQty = $employeeEnabled ? (int) $bill->total_quantity : (int) ($bill->employee_subsidy_qty ?: 0);
        $employeeRate = max(0, (int) $bill->employee_subsidy_rate);
        $employeeAmount = $employeeEnabled ? ($employeeQty * $employeeRate) : 0;

        $studentEnabled = (bool) $bill->student_subsidy_enabled;
        $studentQty = max(0, (int) $bill->student_subsidy_qty);
        $studentRate = max(0, (int) $bill->student_subsidy_rate);
        $studentAmount = ($studentEnabled && $studentQty > 0) ? ($studentQty * $studentRate) : 0;

        $totalSubsidy = $employeeAmount + $studentAmount;
        $netAmount = max(0, (int) $bill->gross_amount - $totalSubsidy);

        $needsUpdate = (int) $bill->employee_subsidy_qty !== $employeeQty
            || (int) $bill->employee_subsidy_amount !== $employeeAmount
            || (int) $bill->student_subsidy_amount !== $studentAmount
            || (int) $bill->total_subsidy_amount !== $totalSubsidy
            || (int) $bill->net_amount !== $netAmount;

        if ($needsUpdate) {
            $bill->employee_subsidy_qty = $employeeQty;
            $bill->employee_subsidy_amount = $employeeAmount;
            $bill->student_subsidy_amount = $studentAmount;
            $bill->total_subsidy_amount = $totalSubsidy;
            $bill->net_amount = $netAmount;
            $bill->save();

            return $bill;
        }

        return $bill;
    }

    /** @return Collection<int, CateringUnitBill> */
    public function syncPeriod(int $month, int $year, ?int $generatedBy = null): Collection
    {
        return $this->synchronize($month, $year, $generatedBy);
    }

    public function syncUnitPeriod(
        int $unitId,
        int $month,
        int $year,
        ?int $generatedBy = null,
    ): ?CateringUnitBill {
        return $this->synchronize($month, $year, $generatedBy, [$unitId])->first();
    }

    public function syncStatusForBill(CateringUnitBill $bill): CateringUnitBillSyncStatus
    {
        $row = $this->periodStatus($bill->period_month, $bill->period_year)
            ->first(fn (array $row): bool => $row['unit']->id === $bill->catering_employee_unit_id);

        if ($row === null || $row['bill']?->id !== $bill->id) {
            return CateringUnitBillSyncStatus::Unsynced;
        }

        return $row['status'];
    }

    /**
     * @return Collection<int, array{
     *     unit: CateringEmployeeUnit,
     *     bill: CateringUnitBill|null,
     *     status: CateringUnitBillSyncStatus,
     *     source_row_count: int,
     *     source_total_quantity: int,
     *     source_gross_amount: int,
     *     source_price_per_person_snapshot: int|null
     * }>
     */
    public function periodStatus(int $month, int $year): Collection
    {
        [$start, $endExclusive] = $this->periodBounds($month, $year);
        $sourceRows = CateringUnitAttendance::query()
            ->where('attendance_date', '>=', $start)
            ->where('attendance_date', '<', $endExclusive)
            ->orderBy('attendance_date')
            ->get(['catering_employee_unit_id', 'attendance_date', 'quantity', 'price_per_person_snapshot'])
            ->groupBy('catering_employee_unit_id');
        $bills = CateringUnitBill::query()
            ->with(['days' => fn ($query) => $query->orderBy('attendance_date')])
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->get()
            ->keyBy('catering_employee_unit_id');
        $unitIds = $sourceRows->keys()
            ->merge($bills->keys())
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        return CateringEmployeeUnit::query()
            ->where(function ($query) use ($unitIds): void {
                $query->where('is_active', true);

                if ($unitIds->isNotEmpty()) {
                    $query->orWhereKey($unitIds);
                }
            })
            ->ordered()
            ->get()
            ->map(function (CateringEmployeeUnit $unit) use ($sourceRows, $bills): array {
                $rows = $sourceRows->get($unit->id, collect());
                $bill = $bills->get($unit->id);
                $sourceLines = $this->sourceLines($rows);
                $sourceTotalQuantity = (int) $rows->sum('quantity');
                $sourceGrossAmount = (int) collect($sourceLines)->sum('subtotal');
                $sourcePrice = $this->commonPrice($rows);

                return [
                    'unit' => $unit,
                    'bill' => $bill,
                    'status' => $bill === null
                        ? ($rows->isEmpty()
                            ? CateringUnitBillSyncStatus::NoData
                            : CateringUnitBillSyncStatus::Unsynced)
                        : ($this->billMatchesSource(
                            $bill,
                            $unit,
                            $sourceLines,
                            $sourceTotalQuantity,
                            $sourceGrossAmount,
                            $sourcePrice,
                        ) ? CateringUnitBillSyncStatus::Synced : CateringUnitBillSyncStatus::Stale),
                    'source_row_count' => $rows->count(),
                    'source_total_quantity' => $sourceTotalQuantity,
                    'source_gross_amount' => $sourceGrossAmount,
                    'source_price_per_person_snapshot' => $sourcePrice,
                ];
            });
    }

    /**
     * @param  array<int, int>|null  $onlyUnitIds
     * @return Collection<int, CateringUnitBill>
     */
    private function synchronize(
        int $month,
        int $year,
        ?int $generatedBy,
        ?array $onlyUnitIds = null,
    ): Collection {
        [$start, $endExclusive] = $this->periodBounds($month, $year);

        return DB::transaction(function () use ($month, $year, $generatedBy, $onlyUnitIds, $start, $endExclusive): Collection {
            $sourceUnitIds = CateringUnitAttendance::query()
                ->when($onlyUnitIds !== null, fn ($query) => $query->whereIn('catering_employee_unit_id', $onlyUnitIds))
                ->where('attendance_date', '>=', $start)
                ->where('attendance_date', '<', $endExclusive)
                ->distinct()
                ->pluck('catering_employee_unit_id');
            $existingBillUnitIds = CateringUnitBill::query()
                ->when($onlyUnitIds !== null, fn ($query) => $query->whereIn('catering_employee_unit_id', $onlyUnitIds))
                ->where('period_month', $month)
                ->where('period_year', $year)
                ->pluck('catering_employee_unit_id');
            $unitIds = $sourceUnitIds
                ->merge($existingBillUnitIds)
                ->map(fn (mixed $id): int => (int) $id)
                ->unique()
                ->sort()
                ->values();

            if ($unitIds->isEmpty()) {
                return collect();
            }

            $units = CateringEmployeeUnit::query()
                ->whereKey($unitIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'name'])
                ->keyBy('id');
            $sourceRows = CateringUnitAttendance::query()
                ->whereIn('catering_employee_unit_id', $unitIds)
                ->where('attendance_date', '>=', $start)
                ->where('attendance_date', '<', $endExclusive)
                ->orderBy('attendance_date')
                ->lockForUpdate()
                ->get(['catering_employee_unit_id', 'attendance_date', 'quantity', 'price_per_person_snapshot'])
                ->groupBy('catering_employee_unit_id');
            $existingBills = CateringUnitBill::query()
                ->whereIn('catering_employee_unit_id', $unitIds)
                ->where('period_month', $month)
                ->where('period_year', $year)
                ->lockForUpdate()
                ->get()
                ->keyBy('catering_employee_unit_id');
            $now = now();
            $headers = [];
            $linesByUnit = [];

            foreach ($unitIds as $unitId) {
                $unit = $units->get($unitId);
                $existingBill = $existingBills->get($unitId);
                $rows = $sourceRows->get($unitId, collect());

                if ($unit === null || ($rows->isEmpty() && $existingBill === null)) {
                    continue;
                }

                $lines = $this->sourceLines($rows);
                $grossAmount = (int) collect($lines)->sum('subtotal');
                $header = [
                    'catering_employee_unit_id' => $unitId,
                    'generated_by' => $generatedBy,
                    'period_month' => $month,
                    'period_year' => $year,
                    'unit_name' => $unit->name,
                    'total_quantity' => (int) $rows->sum('quantity'),
                    'price_per_person_snapshot' => $this->commonPrice($rows),
                    'gross_amount' => $grossAmount,
                    'generated_at' => $now,
                    'created_at' => $existingBill?->created_at ?? $now,
                    'updated_at' => $now,
                    'employee_subsidy_enabled' => $existingBill ? (bool) $existingBill->employee_subsidy_enabled : false,
                    'employee_subsidy_qty' => $existingBill ? (int) $existingBill->employee_subsidy_qty : 0,
                    'employee_subsidy_rate' => $existingBill ? (int) $existingBill->employee_subsidy_rate : 0,
                    'employee_subsidy_amount' => $existingBill ? (int) $existingBill->employee_subsidy_amount : 0,
                    'student_subsidy_enabled' => $existingBill ? (bool) $existingBill->student_subsidy_enabled : false,
                    'student_subsidy_qty' => $existingBill ? (int) $existingBill->student_subsidy_qty : 0,
                    'student_subsidy_rate' => $existingBill ? (int) $existingBill->student_subsidy_rate : 0,
                    'student_subsidy_amount' => $existingBill ? (int) $existingBill->student_subsidy_amount : 0,
                    'total_subsidy_amount' => $existingBill ? (int) $existingBill->total_subsidy_amount : 0,
                    'net_amount' => $existingBill ? (int) $existingBill->net_amount : $grossAmount,
                ];

                $headers[] = $header;
                $linesByUnit[$unitId] = $lines;
            }

            if ($headers === []) {
                return collect();
            }

            CateringUnitBill::query()->upsert(
                $headers,
                ['catering_employee_unit_id', 'period_month', 'period_year'],
                [
                    'generated_by',
                    'unit_name',
                    'total_quantity',
                    'price_per_person_snapshot',
                    'gross_amount',
                    'generated_at',
                    'updated_at',
                ],
            );

            $bills = CateringUnitBill::query()
                ->whereIn('catering_employee_unit_id', array_keys($linesByUnit))
                ->where('period_month', $month)
                ->where('period_year', $year)
                ->get()
                ->keyBy('catering_employee_unit_id');
            $billIds = $bills->modelKeys();
            $daySnapshots = [];

            foreach ($linesByUnit as $unitId => $lines) {
                $bill = $bills[$unitId];

                foreach ($lines as $line) {
                    $daySnapshots[] = [
                        'catering_unit_bill_id' => $bill->id,
                        ...$line,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            $this->replaceBillDays($billIds, $daySnapshots);

            $billsCollection = CateringUnitBill::query()
                ->whereKey($billIds)
                ->orderBy('catering_employee_unit_id')
                ->lockForUpdate()
                ->get();

            $updates = [];
            foreach ($billsCollection as $bill) {
                $employeeEnabled = (bool) $bill->employee_subsidy_enabled;
                $employeeQty = $employeeEnabled ? (int) $bill->total_quantity : (int) ($bill->employee_subsidy_qty ?? 0);
                $employeeRate = max(0, (int) $bill->employee_subsidy_rate);
                $employeeAmount = $employeeEnabled ? ($employeeQty * $employeeRate) : 0;

                $studentEnabled = (bool) $bill->student_subsidy_enabled;
                $studentQty = max(0, (int) $bill->student_subsidy_qty);
                $studentRate = max(0, (int) $bill->student_subsidy_rate);
                $studentAmount = ($studentEnabled && $studentQty > 0) ? ($studentQty * $studentRate) : 0;

                $totalSubsidy = $employeeAmount + $studentAmount;
                $netAmount = max(0, (int) $bill->gross_amount - $totalSubsidy);

                if ((int) $bill->employee_subsidy_qty !== $employeeQty
                    || (int) $bill->employee_subsidy_amount !== $employeeAmount
                    || (int) $bill->student_subsidy_amount !== $studentAmount
                    || (int) $bill->total_subsidy_amount !== $totalSubsidy
                    || (int) $bill->net_amount !== $netAmount) {
                    $updates[] = [
                        'id' => $bill->id,
                        'employee_subsidy_qty' => $employeeQty,
                        'employee_subsidy_amount' => $employeeAmount,
                        'student_subsidy_amount' => $studentAmount,
                        'total_subsidy_amount' => $totalSubsidy,
                        'net_amount' => $netAmount,
                    ];
                }
            }

            if ($updates !== []) {
                $now = now();
                foreach ($updates as $update) {
                    CateringUnitBill::query()->whereKey($update['id'])->update(array_merge($update, ['updated_at' => $now]));
                }
            }

            return CateringUnitBill::query()
                ->with(['days' => fn ($query) => $query->orderBy('attendance_date')])
                ->whereKey($billIds)
                ->orderBy('catering_employee_unit_id')
                ->get();
        }, attempts: 5);
    }

    /**
     * @param  Collection<int, CateringUnitAttendance>  $rows
     * @return array<int, array{attendance_date: string, quantity: int, price_per_person_snapshot: int, subtotal: int}>
     */
    private function sourceLines(Collection $rows): array
    {
        return $rows
            ->sortBy(fn (CateringUnitAttendance $attendance): string => $attendance->attendance_date->toDateString())
            ->map(fn (CateringUnitAttendance $attendance): array => [
                'attendance_date' => $attendance->attendance_date->toDateString(),
                'quantity' => $attendance->quantity,
                'price_per_person_snapshot' => $attendance->price_per_person_snapshot,
                'subtotal' => $attendance->quantity * $attendance->price_per_person_snapshot,
            ])
            ->values()
            ->all();
    }

    /** @param Collection<int, CateringUnitAttendance> $rows */
    private function commonPrice(Collection $rows): ?int
    {
        $prices = $rows->pluck('price_per_person_snapshot')->unique()->values();

        return $prices->count() === 1 ? (int) $prices->first() : null;
    }

    /**
     * @param  array<int, array{attendance_date: string, quantity: int, price_per_person_snapshot: int, subtotal: int}>  $sourceLines
     */
    private function billMatchesSource(
        CateringUnitBill $bill,
        CateringEmployeeUnit $unit,
        array $sourceLines,
        int $sourceTotalQuantity,
        int $sourceGrossAmount,
        ?int $sourcePrice,
    ): bool {
        $billLines = $bill->days
            ->sortBy(fn (CateringUnitBillDay $day): string => $day->attendance_date->toDateString())
            ->map(fn (CateringUnitBillDay $day): array => [
                'attendance_date' => $day->attendance_date->toDateString(),
                'quantity' => $day->quantity,
                'price_per_person_snapshot' => $day->price_per_person_snapshot,
                'subtotal' => $day->subtotal,
            ])
            ->values()
            ->all();

        return $bill->unit_name === $unit->name
            && $bill->total_quantity === $sourceTotalQuantity
            && $bill->gross_amount === $sourceGrossAmount
            && $bill->price_per_person_snapshot === $sourcePrice
            && $billLines === $sourceLines;
    }

    /**
     * @param  array<int, int>  $billIds
     * @param  array<int, array<string, mixed>>  $daySnapshots
     */
    protected function replaceBillDays(array $billIds, array $daySnapshots): void
    {
        CateringUnitBillDay::query()->whereIn('catering_unit_bill_id', $billIds)->delete();

        if ($daySnapshots !== []) {
            CateringUnitBillDay::query()->insert($daySnapshots);
        }
    }

    /** @return array{0: string, 1: string} */
    private function periodBounds(int $month, int $year): array
    {
        if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
            throw new InvalidArgumentException('The unit billing period must be between January 2000 and December 2100.');
        }

        $start = CarbonImmutable::create($year, $month, 1)->startOfMonth();

        return [$start->toDateString(), $start->addMonth()->toDateString()];
    }
}
