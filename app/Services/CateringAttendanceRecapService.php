<?php

namespace App\Services;

use App\Enums\CateringAttendanceStatus;
use App\Enums\CateringEmployeeAssignmentType;
use App\Enums\CateringParticipantGroup;
use App\Enums\SchoolLevel;
use App\Models\SchoolClass;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CateringAttendanceRecapService
{
    /** @var array<int, string> */
    private const MONTH_NAMES = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    /**
     * @param  array{month: int, year: int, participantGroup: string, jenjang: string, schoolClassId: string, search: string}  $filters
     * @return array<string, int>
     */
    public function summary(array $filters): array
    {
        $query = $this->baseAttendanceQuery($filters)
            ->selectRaw('COUNT(DISTINCT catering_attendances.catering_member_id) as participants')
            ->selectRaw('COUNT(*) as total_records');

        foreach (CateringAttendanceStatus::cases() as $status) {
            $query->selectRaw(
                'SUM(CASE WHEN catering_attendances.status = ? THEN 1 ELSE 0 END) as '.$status->value,
                [$status->value],
            );
        }

        $row = $query->first();
        $summary = [
            'participants' => (int) ($row?->participants ?? 0),
            'total_records' => (int) ($row?->total_records ?? 0),
        ];

        foreach (CateringAttendanceStatus::cases() as $status) {
            $summary[$status->value] = (int) ($row?->{$status->value} ?? 0);
        }

        return $summary;
    }

    /**
     * @param  array{month: int, year: int, participantGroup: string, jenjang: string, schoolClassId: string, search: string}  $filters
     * @return LengthAwarePaginator<int, array<string, int|string|bool|null>>
     */
    public function paginateParticipants(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $paginator = $this->participantAggregateQuery($filters)
            ->orderBy('catering_members.name')
            ->orderBy('catering_members.id')
            ->paginate($perPage);

        $paginator->setCollection($paginator->getCollection()->map($this->mapParticipant(...)));

        return $paginator;
    }

    /**
     * @param  array{month: int, year: int, participantGroup: string, jenjang: string, schoolClassId: string, search: string}  $filters
     * @return Collection<int, array<string, int|string|bool|null>>
     */
    public function participantRows(array $filters): Collection
    {
        return $this->participantAggregateQuery($filters)
            ->orderBy('catering_members.name')
            ->orderBy('catering_members.id')
            ->get()
            ->map($this->mapParticipant(...));
    }

    /**
     * @param  array{month: int, year: int, participantGroup: string, jenjang: string, schoolClassId: string, search: string}  $filters
     * @return array<int, array<string, int|string>>
     */
    public function groupRows(array $filters): array
    {
        $participants = $this->participantRows($filters);
        $classIds = $participants
            ->where('participant_group', CateringParticipantGroup::Student->value)
            ->pluck('school_class_id')
            ->filter()
            ->unique()
            ->values();
        $classOrder = $classIds->isEmpty()
            ? collect()
            : SchoolClass::query()
                ->whereKey($classIds)
                ->orderedForSelection()
                ->pluck('id')
                ->flip();
        $groups = [];

        foreach ($participants as $participant) {
            $isStudent = $participant['participant_group'] === CateringParticipantGroup::Student->value;
            $key = ($isStudent ? 'student:' : 'employee:').$participant['group_label'];

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'label' => $participant['group_label'],
                    'participant_group' => $participant['participant_group'],
                    'participant_count' => 0,
                    'sort_order' => $isStudent
                        ? (int) ($classOrder[$participant['school_class_id']] ?? PHP_INT_MAX - 1)
                        : PHP_INT_MAX,
                ] + array_fill_keys(
                    array_map(fn (CateringAttendanceStatus $status): string => $status->value, CateringAttendanceStatus::cases()),
                    0,
                );
            }

            $groups[$key]['participant_count']++;

            foreach (CateringAttendanceStatus::cases() as $status) {
                $groups[$key][$status->value] += $participant[$status->value];
            }
        }

        uasort($groups, function (array $left, array $right): int {
            if ($left['participant_group'] !== $right['participant_group']) {
                return $left['participant_group'] === CateringParticipantGroup::Student->value ? -1 : 1;
            }

            return [$left['sort_order'], $left['label']] <=> [$right['sort_order'], $right['label']];
        });

        return array_values(array_map(function (array $group): array {
            unset($group['sort_order'], $group['participant_group']);

            return $group;
        }, $groups));
    }

    public function periodLabel(int $month, int $year): string
    {
        return (self::MONTH_NAMES[$month] ?? (string) $month).' '.$year;
    }

    /**
     * @param  array{month: int, year: int, participantGroup: string, jenjang: string, schoolClassId: string, search: string}  $filters
     */
    public function filterLabel(array $filters): string
    {
        $parts = [];
        $group = CateringParticipantGroup::tryFrom($filters['participantGroup']);
        $parts[] = $group?->label() ?? 'Semua Peserta';

        if ($filters['jenjang'] !== '') {
            $parts[] = $filters['jenjang'];
        }

        if ($filters['schoolClassId'] !== '') {
            $parts[] = SchoolClass::query()->whereKey((int) $filters['schoolClassId'])->value('name') ?? '-';
        }

        if ($filters['search'] !== '') {
            $parts[] = 'Pencarian: '.$filters['search'];
        }

        return implode(' · ', $parts);
    }

    /**
     * @param  array{month: int, year: int, participantGroup: string, jenjang: string, schoolClassId: string, search: string}  $filters
     */
    private function baseAttendanceQuery(array $filters): Builder
    {
        $start = CarbonImmutable::create($filters['year'], $filters['month'], 1)->startOfMonth();

        return DB::table('catering_attendances')
            ->join('catering_members', 'catering_members.id', '=', 'catering_attendances.catering_member_id')
            ->join('catering_categories', 'catering_categories.id', '=', 'catering_members.catering_category_id')
            ->leftJoin('school_classes', 'school_classes.id', '=', 'catering_members.school_class_id')
            ->where('catering_attendances.attendance_date', '>=', $start->toDateString())
            ->where('catering_attendances.attendance_date', '<', $start->addMonth()->toDateString())
            ->when(
                in_array($filters['participantGroup'], array_column(CateringParticipantGroup::cases(), 'value'), true),
                fn (Builder $query): Builder => $query->where('catering_categories.participant_group', $filters['participantGroup']),
            )
            ->when(
                $filters['jenjang'] !== '',
                fn (Builder $query): Builder => $query->whereIn(
                    'school_classes.level',
                    SchoolLevel::valuesForEducationLevel($filters['jenjang']),
                ),
            )
            ->when(
                $filters['schoolClassId'] !== '',
                fn (Builder $query): Builder => $query->where('catering_members.school_class_id', (int) $filters['schoolClassId']),
            )
            ->when(
                $filters['search'] !== '',
                fn (Builder $query): Builder => $query->where('catering_members.name', 'like', '%'.$filters['search'].'%'),
            );
    }

    /**
     * @param  array{month: int, year: int, participantGroup: string, jenjang: string, schoolClassId: string, search: string}  $filters
     */
    private function participantAggregateQuery(array $filters): Builder
    {
        $query = $this->baseAttendanceQuery($filters)
            ->leftJoin('catering_employee_assignments', 'catering_employee_assignments.catering_member_id', '=', 'catering_members.id')
            ->leftJoin('school_classes as employee_classes', 'employee_classes.id', '=', 'catering_employee_assignments.school_class_id')
            ->select([
                'catering_members.id as member_id',
                'catering_members.name as member_name',
                'catering_members.is_active',
                'catering_members.school_class_id',
                'catering_categories.participant_group',
                'catering_categories.name as category_name',
                'school_classes.name as school_class_name',
                'school_classes.level as school_level',
                'catering_employee_assignments.assignment_type',
                'catering_employee_assignments.level as assignment_level',
                'catering_employee_assignments.education_level as assignment_education_level',
                'employee_classes.name as assignment_class_name',
            ])
            ->selectRaw('COUNT(*) as total_days');

        foreach (CateringAttendanceStatus::cases() as $status) {
            $query->selectRaw(
                'SUM(CASE WHEN catering_attendances.status = ? THEN 1 ELSE 0 END) as '.$status->value,
                [$status->value],
            );
        }

        return $query->groupBy([
            'catering_members.id',
            'catering_members.name',
            'catering_members.is_active',
            'catering_members.school_class_id',
            'catering_categories.participant_group',
            'catering_categories.name',
            'school_classes.name',
            'school_classes.level',
            'catering_employee_assignments.assignment_type',
            'catering_employee_assignments.level',
            'catering_employee_assignments.education_level',
            'employee_classes.name',
        ]);
    }

    /** @return array<string, int|string|bool|null> */
    private function mapParticipant(object $row): array
    {
        $participant = [
            'member_id' => (int) $row->member_id,
            'member_name' => (string) $row->member_name,
            'is_active' => (bool) $row->is_active,
            'school_class_id' => $row->school_class_id === null ? null : (int) $row->school_class_id,
            'participant_group' => (string) $row->participant_group,
            'group_label' => $row->participant_group === CateringParticipantGroup::Employee->value
                ? $this->employeeGroupLabel($row)
                : ((string) ($row->school_class_name ?? '') ?: '-'),
            'total_days' => (int) $row->total_days,
        ];

        foreach (CateringAttendanceStatus::cases() as $status) {
            $participant[$status->value] = (int) $row->{$status->value};
        }

        return $participant;
    }

    private function employeeGroupLabel(object $row): string
    {
        $type = CateringEmployeeAssignmentType::tryFrom((string) $row->assignment_type);

        return match ($type) {
            CateringEmployeeAssignmentType::SchoolClass => $row->assignment_class_name
                ? 'Kelas · '.$row->assignment_class_name
                : (string) $row->category_name,
            CateringEmployeeAssignmentType::Level => SchoolLevel::tryFrom((string) $row->assignment_level)?->label()
                ? 'Tingkat · '.SchoolLevel::from((string) $row->assignment_level)->label()
                : (string) $row->category_name,
            CateringEmployeeAssignmentType::EducationLevel => $row->assignment_education_level
                ? 'Jenjang · '.$row->assignment_education_level
                : (string) $row->category_name,
            CateringEmployeeAssignmentType::General => 'Umum',
            null => (string) ($row->category_name ?: 'Umum'),
        };
    }
}
