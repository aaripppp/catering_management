<?php

namespace App\Services;

use App\Enums\CateringAttendanceStatus;
use App\Enums\CateringBillStatus;
use App\Enums\CateringParticipantGroup;
use App\Enums\SchoolLevel;
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
            ->when($schoolClass !== null, fn (Builder $query): Builder => $query->where('school_class_id', $schoolClass->id))
            ->when(
                $schoolClass === null && $jenjang !== null && $jenjang !== '',
                fn (Builder $query): Builder => $query->whereHas(
                    'schoolClass',
                    fn (Builder $classQuery): Builder => $classQuery->forJenjang($jenjang),
                ),
            )
            ->whereHas('attendances', fn (Builder $query): Builder => $query
                ->where('attendance_date', '>=', $start->toDateString())
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
                $category = $member->cateringCategory;
                $pricePerDay = $existing?->price_per_day ?? (int) ($category?->price_per_day ?? 0);

                $snapshots[] = [
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
                    'saved_days' => $memberCounts['saved'],
                    'active_days' => $memberCounts[CateringAttendanceStatus::Ikut->value],
                    'sakit_days' => $memberCounts[CateringAttendanceStatus::Sakit->value],
                    'izin_days' => $memberCounts[CateringAttendanceStatus::Izin->value],
                    'alfa_days' => $memberCounts[CateringAttendanceStatus::Alfa->value],
                    'off_days' => $memberCounts[CateringAttendanceStatus::TidakIkut->value],
                    'ujian_days' => $memberCounts[CateringAttendanceStatus::Ujian->value],
                    'event_unit_days' => $memberCounts[CateringAttendanceStatus::EventUnit->value],
                    'puasa_days' => $memberCounts[CateringAttendanceStatus::Puasa->value],
                    'libur_days' => $memberCounts[CateringAttendanceStatus::Libur->value],
                    'gross_amount' => $memberCounts[CateringAttendanceStatus::Ikut->value] * $pricePerDay,
                    'payment_status' => $existing?->payment_status->value ?? CateringBillStatus::Unpaid->value,
                    'generated_at' => $now,
                    'created_at' => $existing?->created_at ?? $now,
                    'updated_at' => $now,
                ];

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
     * @param  array<int, int>  $memberIds
     * @return array<int, array<string, int>>
     */
    private function attendanceCounts(array $memberIds, CarbonImmutable $start, CarbonImmutable $endExclusive): array
    {
        $query = DB::table('catering_attendances')
            ->select('catering_member_id')
            ->selectRaw('COUNT(*) as saved')
            ->whereIn('catering_member_id', $memberIds)
            ->where('attendance_date', '>=', $start->toDateString())
            ->where('attendance_date', '<', $endExclusive->toDateString())
            ->groupBy('catering_member_id');

        foreach (CateringAttendanceStatus::cases() as $status) {
            $query->selectRaw(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as '.$status->value,
                [$status->value],
            );
        }

        return $query->get()
            ->mapWithKeys(function (object $row): array {
                $values = ['saved' => (int) $row->saved];

                foreach (CateringAttendanceStatus::cases() as $status) {
                    $values[$status->value] = (int) $row->{$status->value};
                }

                return [(int) $row->catering_member_id => $values];
            })
            ->all();
    }

    /** @return array<string, int> */
    private function emptyCounts(): array
    {
        return ['saved' => 0] + array_fill_keys(
            array_map(fn (CateringAttendanceStatus $status): string => $status->value, CateringAttendanceStatus::cases()),
            0,
        );
    }
}
