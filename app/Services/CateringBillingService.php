<?php

namespace App\Services;

use App\Enums\CateringAttendanceStatus;
use App\Enums\CateringBillStatus;
use App\Enums\CateringParticipantGroup;
use App\Enums\SchoolLevel;
use App\Models\CateringAttendance;
use App\Models\CateringBill;
use App\Models\CateringMember;
use App\Models\SchoolClass;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CateringBillingService
{
    public function __construct(private CateringFinancialReconciliationService $financialReconciliation) {}

    /**
     * Create missing monthly snapshots and resync existing snapshots from attendance.
     *
     * @return array{created: int, regenerated: int, skipped: int, credit_applied: int}
     */
    public function generate(
        int $year,
        int $month,
        CateringParticipantGroup $group,
        ?SchoolClass $schoolClass = null,
        ?string $jenjang = null,
        ?User $actor = null,
    ): array {
        $start = CarbonImmutable::create($year, $month, 1)->startOfMonth();
        $endExclusive = $start->addMonth();
        $startStr = $start->toDateString();
        $endStr = $start->endOfMonth()->toDateString();

        $existingBills = CateringBill::query()
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->where('participant_group', $group)
            ->when($schoolClass !== null, fn (Builder $query): Builder => $query->where('school_class_id', $schoolClass->id))
            ->when(
                $schoolClass === null && $jenjang !== null && $jenjang !== '',
                fn (Builder $query): Builder => $query->whereIn('school_level', SchoolLevel::valuesForEducationLevel($jenjang)),
            )
            ->get()
            ->keyBy('catering_member_id');

        $eligibleMembers = CateringMember::query()
            ->select([
                'id',
                'name',
                'school_class_id',
                'catering_category_id',
                'guardian_name',
                'guardian_phone',
            ])
            ->with([
                'cateringCategory:id,name,price_per_day,participant_group',
                'schoolClass:id,name,level',
            ])
            ->participantGroup($group)
            ->active()
            ->whereHas('periods', function ($query) use ($startStr, $endStr): void {
                $query->whereDate('start_date', '<=', $endStr)
                    ->where(function ($q) use ($startStr): void {
                        $q->whereNull('end_date')
                            ->orWhereDate('end_date', '>=', $startStr);
                    });
            })
            ->when($schoolClass !== null, fn (Builder $query): Builder => $query->where('school_class_id', $schoolClass->id))
            ->when(
                $schoolClass === null && $jenjang !== null && $jenjang !== '',
                fn (Builder $query): Builder => $query->whereHas(
                    'schoolClass',
                    fn (Builder $classQuery): Builder => $classQuery->forJenjang($jenjang),
                ),
            )
            ->whereHas('attendances', fn (Builder $query): Builder => $query
                ->where('attendance_date', '>=', $startStr)
                ->where('attendance_date', '<', $endExclusive->toDateString()))
            ->orderBy('id')
            ->get();

        $missingExistingMemberIds = $existingBills->keys()->diff($eligibleMembers->modelKeys());
        $existingMembers = $missingExistingMemberIds->isEmpty()
            ? collect()
            : CateringMember::query()
                ->select([
                    'id',
                    'name',
                    'school_class_id',
                    'catering_category_id',
                    'guardian_name',
                    'guardian_phone',
                ])
                ->with([
                    'cateringCategory:id,name,price_per_day,participant_group',
                    'schoolClass:id,name,level',
                ])
                ->whereKey($missingExistingMemberIds)
                ->get();
        $members = $eligibleMembers
            ->concat($existingMembers)
            ->sortBy('id')
            ->values();

        if ($members->isEmpty()) {
            return ['created' => 0, 'regenerated' => 0, 'skipped' => 0, 'credit_applied' => 0];
        }

        $counts = $this->attendanceCounts(
            $members->pluck('id')->all(),
            $start,
            $endExclusive,
        );

        return DB::transaction(function () use ($members, $counts, $year, $month, $group, $actor): array {
            $memberIds = $members->pluck('id')->all();
            CateringMember::query()
                ->whereKey($memberIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);
            $existingBills = CateringBill::query()
                ->where('period_year', $year)
                ->where('period_month', $month)
                ->whereIn('catering_member_id', $memberIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('catering_member_id');

            $now = now();
            $snapshots = [];
            $created = 0;
            $regenerated = 0;
            $skipped = 0;

            foreach ($members as $member) {
                $existing = $existingBills->get($member->id);
                $memberCounts = $counts[$member->id] ?? $this->emptyCounts();
                $snapshots[] = $this->snapshot($member, $memberCounts, $year, $month, $group, $actor, $existing, $now);

                $existing === null ? $created++ : $regenerated++;
            }

            if ($snapshots === []) {
                return compact('created', 'regenerated', 'skipped') + ['credit_applied' => 0];
            }

            CateringBill::query()->upsert(
                $snapshots,
                ['catering_member_id', 'period_month', 'period_year'],
                array_values(array_diff(array_keys($snapshots[0]), [
                    'catering_member_id',
                    'period_month',
                    'period_year',
                    'created_at',
                ])),
            );

            $creditApplied = $this->financialReconciliation->reconcileMembers($memberIds);

            return compact('created', 'regenerated', 'skipped') + ['credit_applied' => $creditApplied];
        }, attempts: 5);
    }

    /**
     * Resynchronize existing bills at and after a changed membership boundary.
     */
    public function resynchronizeExistingBillsFrom(
        int $memberId,
        CarbonImmutable $from,
        ?User $actor = null,
    ): int {
        return DB::transaction(function () use ($memberId, $from, $actor): int {
            $member = CateringMember::query()
                ->with([
                    'cateringCategory:id,name,price_per_day,participant_group',
                    'schoolClass:id,name,level',
                ])
                ->lockForUpdate()
                ->findOrFail($memberId);

            $boundary = ($from->year * 100) + $from->month;
            $bills = CateringBill::query()
                ->where('catering_member_id', $memberId)
                ->whereRaw('(period_year * 100 + period_month) >= ?', [$boundary])
                ->orderBy('period_year')
                ->orderBy('period_month')
                ->lockForUpdate()
                ->get();

            if ($bills->isEmpty()) {
                return 0;
            }

            $firstPeriod = CarbonImmutable::create($bills->first()->period_year, $bills->first()->period_month, 1);
            $lastPeriod = CarbonImmutable::create($bills->last()->period_year, $bills->last()->period_month, 1);
            $countsByMonth = CateringAttendance::query()
                ->coveredByMembership()
                ->where('catering_member_id', $memberId)
                ->whereBetween('attendance_date', [
                    $firstPeriod->toDateString(),
                    $lastPeriod->endOfMonth()->toDateString(),
                ])
                ->get(['attendance_date', 'status'])
                ->groupBy(fn (CateringAttendance $attendance): string => $attendance->attendance_date->format('Y-n'))
                ->map(function ($rows): array {
                    $counts = $this->emptyCounts();
                    $counts['saved'] = $rows->count();

                    foreach ($rows->groupBy(fn (CateringAttendance $attendance): string => $attendance->status->value) as $status => $statusRows) {
                        $counts[$status] = $statusRows->count();
                    }

                    return $counts;
                });

            $now = now();
            $snapshots = [];
            foreach ($bills as $bill) {
                $key = $bill->period_year.'-'.$bill->period_month;
                $group = $bill->participant_group;
                $snapshots[] = $this->snapshot(
                    $member,
                    $countsByMonth->get($key, $this->emptyCounts()),
                    $bill->period_year,
                    $bill->period_month,
                    $group,
                    $actor,
                    $bill,
                    $now,
                );
            }

            CateringBill::query()->upsert(
                $snapshots,
                ['catering_member_id', 'period_month', 'period_year'],
                array_values(array_diff(array_keys($snapshots[0]), [
                    'catering_member_id',
                    'period_month',
                    'period_year',
                    'created_at',
                ])),
            );

            $this->financialReconciliation->reconcileMembers([$memberId]);

            return $bills->count();
        }, attempts: 5);
    }

    /**
     * @param  array<int, int>  $memberIds
     * @return array<int, array<string, int>>
     */
    private function attendanceCounts(array $memberIds, CarbonImmutable $start, CarbonImmutable $endExclusive): array
    {
        if ($memberIds === []) {
            return [];
        }

        $att = CateringAttendance::query()
            ->coveredByMembership()
            ->select('catering_member_id', 'status')
            ->whereIn('catering_member_id', $memberIds)
            ->where('attendance_date', '>=', $start->toDateString())
            ->where('attendance_date', '<', $endExclusive->toDateString())
            ->get()
            ->groupBy('catering_member_id');

        $counts = [];
        foreach ($memberIds as $memberId) {
            $rows = $att->get($memberId) ?? collect();
            if ($rows->isEmpty()) {
                $counts[$memberId] = $this->emptyCounts();

                continue;
            }
            $byStatus = $rows->groupBy(fn (CateringAttendance $attendance): string => $attendance->status->value);
            $counts[$memberId] = ['saved' => $rows->count()];
            foreach (CateringAttendanceStatus::cases() as $status) {
                $counts[$memberId][$status->value] = (int) ($byStatus->get($status->value)?->count() ?? 0);
            }
        }

        return $counts;
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, mixed>
     */
    private function snapshot(
        CateringMember $member,
        array $counts,
        int $year,
        int $month,
        CateringParticipantGroup $group,
        ?User $actor,
        ?CateringBill $existing,
        mixed $now,
    ): array {
        $category = $member->cateringCategory;
        $pricePerDay = (int) ($category?->price_per_day ?? 0);

        return [
            'catering_member_id' => $member->id,
            'school_class_id' => $member->school_class_id,
            'catering_category_id' => $member->catering_category_id,
            'generated_by' => $actor?->id,
            'period_month' => $month,
            'period_year' => $year,
            'member_name' => $member->name,
            'participant_group' => $group->value,
            'school_class_name' => $member->schoolClass?->name,
            'school_level' => $member->schoolClass?->level,
            'guardian_name' => $member->guardian_name,
            'guardian_phone' => $member->guardian_phone,
            'category_name' => $category?->name ?? '-',
            'price_per_day' => $pricePerDay,
            'saved_days' => $counts['saved'],
            'active_days' => $counts[CateringAttendanceStatus::Ikut->value],
            'sakit_days' => $counts[CateringAttendanceStatus::Sakit->value],
            'izin_days' => $counts[CateringAttendanceStatus::Izin->value],
            'alfa_days' => $counts[CateringAttendanceStatus::Alfa->value],
            'off_days' => $counts[CateringAttendanceStatus::TidakIkut->value],
            'ujian_days' => $counts[CateringAttendanceStatus::Ujian->value],
            'event_unit_days' => $counts[CateringAttendanceStatus::EventUnit->value],
            'puasa_days' => $counts[CateringAttendanceStatus::Puasa->value],
            'libur_days' => $counts[CateringAttendanceStatus::Libur->value],
            'gross_amount' => $counts[CateringAttendanceStatus::Ikut->value] * $pricePerDay,
            'payment_status' => $existing?->payment_status->value ?? CateringBillStatus::Unpaid->value,
            'generated_at' => $now,
            'created_at' => $existing?->created_at ?? $now,
            'updated_at' => $now,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function emptyCounts(): array
    {
        $counts = ['saved' => 0];
        foreach (CateringAttendanceStatus::cases() as $status) {
            $counts[$status->value] = 0;
        }

        return $counts;
    }
}
