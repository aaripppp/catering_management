<?php

namespace App\Services;

use App\Enums\CateringAttendanceStatus;
use App\Enums\CateringEmployeeAssignmentType;
use App\Enums\CateringParticipantGroup;
use App\Enums\SchoolLevel;
use App\Models\CateringAttendance;
use App\Models\SchoolClass;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds the "Kebutuhan Catering per Tanggal" recap.
 *
 * The recap answers one operational question: for a chosen date, how many
 * catering portions must be prepared, and where does each portion sit in the
 * jenjang / level / class hierarchy.
 *
 * Only "ikut" contributes a portion, and only from attendance rows that are
 * already saved. Dashboard entry points initialise missing defaults before
 * requesting the recap. CateringInvoiceService likewise bills saved rows only.
 *
 * The whole recap is produced with a fixed, bounded number of queries and the
 * hierarchy is assembled in memory afterwards, so there is no per class, per
 * level, per jenjang or per participant query.
 */
class CateringRequirementRecapService
{
    /**
     * @return array<string, mixed>
     */
    public static function forDate(CarbonImmutable $date): array
    {
        $date = $date->startOfDay();
        $dateString = $date->toDateString();

        $classes = self::classMap();
        $portions = self::portionRows($dateString);
        $coverage = self::coverage();
        $dayStats = self::dayStats($dateString);

        $recap = self::emptyRecap($date);
        $recap['hasSavedData'] = $dayStats['total'] > 0;
        $recap['savedRows'] = $dayStats['total'];
        $recap['liburRows'] = $dayStats['libur'];
        $recap['tidakIkutRows'] = $dayStats['tidakIkut'];
        $recap['activeParticipants'] = $coverage['participants'];
        $recap['activeStudents'] = $coverage['students'];
        $recap['activeEmployees'] = $coverage['employees'];
        $recap['unassignedEmployees'] = $coverage['unassignedEmployees'];

        self::applyPortions($recap, $portions, $classes);

        $recap['totalPortions'] = self::rollUpTotals($recap);

        return $recap;
    }

    /**
     * All active classes, ordered for display, keyed by id.
     *
     * Read once and reused for every class lookup, which is what keeps the
     * class and level roll ups free of extra queries.
     *
     * @return Collection<int, array{id: int, name: string, level: string, jenjang: string|null}>
     */
    private static function classMap(): Collection
    {
        return SchoolClass::query()
            ->active()
            ->orderedForSelection()
            ->get(['id', 'name', 'level'])
            ->mapWithKeys(fn (SchoolClass $schoolClass): array => [
                $schoolClass->id => [
                    'id' => $schoolClass->id,
                    'name' => $schoolClass->name,
                    'level' => (string) $schoolClass->level,
                    'jenjang' => $schoolClass->jenjang,
                ],
            ]);
    }

    /**
     * One bounded row per saved "ikut" attendance on the date.
     *
     * This is the portion query: it starts from catering_attendances and joins
     * the member, its category participant group and the employee assignment, so
     * inactive participants and non "ikut" statuses are removed in SQL rather
     * than in PHP.
     *
     * @return Collection<int, object>
     */
    private static function portionRows(string $dateString): Collection
    {
        [$start, $end] = self::dayBounds($dateString);

        return DB::table('catering_attendances')
            ->select([
                'catering_attendances.catering_member_id',
                'catering_members.name',
                'catering_members.school_class_id',
                'catering_categories.participant_group',
                'catering_employee_assignments.assignment_type',
                'catering_employee_assignments.school_class_id as assignment_school_class_id',
                'catering_employee_assignments.level as assignment_level',
                'catering_employee_assignments.education_level as assignment_education_level',
            ])
            ->join('catering_members', 'catering_members.id', '=', 'catering_attendances.catering_member_id')
            ->join('catering_categories', 'catering_categories.id', '=', 'catering_members.catering_category_id')
            ->leftJoin(
                'catering_employee_assignments',
                'catering_employee_assignments.catering_member_id',
                '=',
                'catering_members.id',
            )
            ->where('catering_attendances.attendance_date', '>=', $start)
            ->where('catering_attendances.attendance_date', '<', $end)
            ->where('catering_attendances.status', CateringAttendanceStatus::Ikut->value)
            ->where('catering_members.is_active', true)
            ->orderBy('catering_attendances.catering_member_id')
            ->get();
    }

    /**
     * Half open bounds for a single day, matching how the attendance matrix and
     * the invoice service scope a date range.
     *
     * An equality comparison cannot be used: on SQLite the `date` column stores
     * a datetime string, so a row for 2026-09-30 is stored as
     * 2026-09-30 00:00:00. The range form is portable and still reads exactly
     * one calendar day.
     *
     * @return array{0: string, 1: string}
     */
    private static function dayBounds(string $dateString): array
    {
        return [
            $dateString,
            CarbonImmutable::parse($dateString)->addDay()->toDateString(),
        ];
    }

    /**
     * Active participant head counts and the employee placement data health.
     *
     * @return array{participants: int, students: int, employees: int, unassignedEmployees: int}
     */
    private static function coverage(): array
    {
        $rows = DB::table('catering_members')
            ->join('catering_categories', 'catering_categories.id', '=', 'catering_members.catering_category_id')
            ->leftJoin(
                'catering_employee_assignments',
                'catering_employee_assignments.catering_member_id',
                '=',
                'catering_members.id',
            )
            ->where('catering_members.is_active', true)
            ->selectRaw('catering_categories.participant_group as participant_group, COUNT(*) as total')
            ->selectRaw(
                'SUM(CASE WHEN catering_categories.participant_group = ? AND catering_employee_assignments.id IS NULL THEN 1 ELSE 0 END) as unassigned',
                [CateringParticipantGroup::Employee->value],
            )
            ->groupBy('catering_categories.participant_group')
            ->get();

        $coverage = [
            'participants' => 0,
            'students' => 0,
            'employees' => 0,
            'unassignedEmployees' => 0,
        ];

        foreach ($rows as $row) {
            $coverage['participants'] += (int) $row->total;

            if ($row->participant_group === CateringParticipantGroup::Employee->value) {
                $coverage['employees'] += (int) $row->total;
                $coverage['unassignedEmployees'] += (int) $row->unassigned;

                continue;
            }

            $coverage['students'] += (int) $row->total;
        }

        return $coverage;
    }

    /**
     * Whether the date has any saved attendance at all, and how much of it is libur.
     *
     * @return array{total: int, libur: int, tidakIkut: int}
     */
    private static function dayStats(string $dateString): array
    {
        [$start, $end] = self::dayBounds($dateString);

        $stats = CateringAttendance::query()
            ->where('attendance_date', '>=', $start)
            ->where('attendance_date', '<', $end)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as libur',
                [CateringAttendanceStatus::Libur->value],
            )
            ->selectRaw(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as tidak_ikut',
                [CateringAttendanceStatus::TidakIkut->value],
            )
            ->first();

        return [
            'total' => (int) ($stats->total ?? 0),
            'libur' => (int) ($stats->libur ?? 0),
            'tidakIkut' => (int) ($stats->tidak_ikut ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyRecap(CarbonImmutable $date): array
    {
        $jenjangs = [];

        foreach (SchoolLevel::educationLevels() as $jenjang) {
            $levels = [];

            foreach (SchoolLevel::groupedOptions()[$jenjang] ?? [] as $levelValue => $levelLabel) {
                $levels[$levelValue] = [
                    'value' => $levelValue,
                    'label' => $levelLabel,
                    'total' => 0,
                    'employeeTotal' => 0,
                    'levelEmployees' => [],
                    'classes' => [],
                ];
            }

            $jenjangs[$jenjang] = [
                'label' => $jenjang,
                'total' => 0,
                'employeeTotal' => 0,
                'educationEmployees' => [],
                'levels' => $levels,
            ];
        }

        return [
            'date' => $date->toDateString(),
            'dateLabel' => $date->locale('id')->translatedFormat('d F Y'),
            'weekdayLabel' => $date->locale('id')->translatedFormat('l'),
            'badge' => self::badgeFor($date),
            'hasSavedData' => false,
            'savedRows' => 0,
            'liburRows' => 0,
            'tidakIkutRows' => 0,
            'totalPortions' => 0,
            'activeParticipants' => 0,
            'activeStudents' => 0,
            'activeEmployees' => 0,
            'unassignedEmployees' => 0,
            'jenjangs' => $jenjangs,
            'general' => ['label' => 'Pegawai Umum', 'total' => 0, 'participants' => []],
            'unplaced' => ['label' => 'Belum Ditempatkan', 'total' => 0, 'participants' => []],
            'classless' => ['label' => 'Siswa Tanpa Kelas', 'total' => 0, 'participants' => []],
            'details' => [],
            'detailHeadings' => [],
        ];
    }

    private static function badgeFor(CarbonImmutable $date): ?string
    {
        $today = CarbonImmutable::today(config('app.timezone'));

        return match ($date->toDateString()) {
            $today->toDateString() => 'Hari Ini',
            $today->subDay()->toDateString() => 'Kemarin',
            $today->addDay()->toDateString() => 'Besok',
            default => null,
        };
    }

    /**
     * Place every counted portion into exactly one bucket.
     *
     * Each employee lands in a single bucket, so class, level, jenjang and
     * overall totals can never count the same portion twice.
     *
     * @param  array<string, mixed>  $recap
     * @param  Collection<int, object>  $portions
     * @param  Collection<int, array{id: int, name: string, level: string, jenjang: string|null}>  $classes
     */
    private static function applyPortions(array &$recap, Collection $portions, Collection $classes): void
    {
        foreach ($portions as $row) {
            $participant = [
                'id' => (int) $row->catering_member_id,
                'name' => (string) $row->name,
                'type' => $row->participant_group === CateringParticipantGroup::Employee->value
                    ? CateringParticipantGroup::Employee->label()
                    : CateringParticipantGroup::Student->label(),
                'status' => CateringAttendanceStatus::Ikut->label(),
            ];

            if ($row->participant_group !== CateringParticipantGroup::Employee->value) {
                self::placeStudent($recap, $participant, $row, $classes);

                continue;
            }

            self::placeEmployee($recap, $participant, $row, $classes);
        }
    }

    /**
     * @param  array<string, mixed>  $recap
     * @param  array{id: int, name: string, type: string, status: string}  $participant
     * @param  Collection<int, array{id: int, name: string, level: string, jenjang: string|null}>  $classes
     */
    private static function placeStudent(array &$recap, array $participant, object $row, Collection $classes): void
    {
        $class = $row->school_class_id === null ? null : $classes->get((int) $row->school_class_id);

        if ($class === null || $class['jenjang'] === null || ! isset($recap['jenjangs'][$class['jenjang']])) {
            $recap['classless']['participants'][] = $participant;

            return;
        }

        self::pushClass($recap, $participant, $class);
    }

    /**
     * @param  array<string, mixed>  $recap
     * @param  array{id: int, name: string, type: string, status: string}  $participant
     * @param  Collection<int, array{id: int, name: string, level: string, jenjang: string|null}>  $classes
     */
    private static function placeEmployee(array &$recap, array $participant, object $row, Collection $classes): void
    {
        $assignmentType = $row->assignment_type === null
            ? null
            : CateringEmployeeAssignmentType::tryFrom((string) $row->assignment_type);

        match ($assignmentType) {
            CateringEmployeeAssignmentType::SchoolClass => self::placeClassEmployee($recap, $participant, $row, $classes),
            CateringEmployeeAssignmentType::Level => self::placeLevelEmployee($recap, $participant, $row),
            CateringEmployeeAssignmentType::EducationLevel => self::placeEducationEmployee($recap, $participant, $row),
            CateringEmployeeAssignmentType::General => self::placeGeneralEmployee($recap, $participant),
            // No assignment, or a class that no longer resolves. Placement is
            // unknown, so the employee is surfaced instead of being guessed into
            // a class, level or jenjang.
            default => self::placeUnplacedEmployee($recap, $participant),
        };
    }

    /**
     * @param  array<string, mixed>  $recap
     * @param  array{id: int, name: string, type: string, status: string}  $participant
     * @param  Collection<int, array{id: int, name: string, level: string, jenjang: string|null}>  $classes
     */
    private static function placeClassEmployee(array &$recap, array $participant, object $row, Collection $classes): void
    {
        $class = $row->assignment_school_class_id === null
            ? null
            : $classes->get((int) $row->assignment_school_class_id);

        if ($class === null || $class['jenjang'] === null || ! isset($recap['jenjangs'][$class['jenjang']])) {
            $recap['unplaced']['participants'][] = $participant;

            return;
        }

        self::pushClass($recap, $participant, $class);
    }

    /**
     * Level assigned employees are not attached to one class, so they sit beside
     * the class rows of their level and only roll up to level, jenjang and overall.
     *
     * @param  array<string, mixed>  $recap
     * @param  array{id: int, name: string, type: string, status: string}  $participant
     */
    private static function placeLevelEmployee(array &$recap, array $participant, object $row): void
    {
        $levelValue = (string) $row->assignment_level;
        $jenjang = SchoolLevel::tryFrom($levelValue)?->educationLevel();

        if ($jenjang === null || ! isset($recap['jenjangs'][$jenjang]['levels'][$levelValue])) {
            $recap['unplaced']['participants'][] = $participant;

            return;
        }

        $recap['jenjangs'][$jenjang]['levels'][$levelValue]['levelEmployees'][] = $participant;
    }

    /**
     * Education level assigned employees are counted at the jenjang summary.
     *
     * @param  array<string, mixed>  $recap
     * @param  array{id: int, name: string, type: string, status: string}  $participant
     */
    private static function placeEducationEmployee(array &$recap, array $participant, object $row): void
    {
        $jenjang = (string) $row->assignment_education_level;

        if (! isset($recap['jenjangs'][$jenjang])) {
            $recap['unplaced']['participants'][] = $participant;

            return;
        }

        $recap['jenjangs'][$jenjang]['educationEmployees'][] = $participant;
    }

    /**
     * @param  array<string, mixed>  $recap
     * @param  array{id: int, name: string, type: string, status: string}  $participant
     */
    private static function placeGeneralEmployee(array &$recap, array $participant): void
    {
        $recap['general']['participants'][] = $participant;
    }

    /**
     * @param  array<string, mixed>  $recap
     * @param  array{id: int, name: string, type: string, status: string}  $participant
     */
    private static function placeUnplacedEmployee(array &$recap, array $participant): void
    {
        $recap['unplaced']['participants'][] = $participant;
    }

    /**
     * Append a participant to a class bucket, creating the bucket on first use.
     *
     * Classes that hold nobody are never created, so the recap only lists
     * classes that actually need portions on the selected date.
     *
     * @param  array<string, mixed>  $recap
     * @param  array{id: int, name: string, type: string, status: string}  $participant
     * @param  array{id: int, name: string, level: string, jenjang: string|null}  $class
     */
    private static function pushClass(array &$recap, array $participant, array $class): void
    {
        $jenjang = $recap['jenjangs'][$class['jenjang']];
        $levelValue = $class['level'];

        if (! isset($jenjang['levels'][$levelValue])) {
            $jenjang['levels'][$levelValue] = [
                'value' => $levelValue,
                'label' => SchoolLevel::tryFrom($levelValue)?->label() ?? $levelValue,
                'total' => 0,
                'employeeTotal' => 0,
                'levelEmployees' => [],
                'classes' => [],
            ];
        }

        if (! isset($jenjang['levels'][$levelValue]['classes'][$class['id']])) {
            $jenjang['levels'][$levelValue]['classes'][$class['id']] = [
                'id' => $class['id'],
                'name' => $class['name'],
                'level' => $levelValue,
                'students' => 0,
                'employees' => 0,
                'participants' => [],
            ];
        }

        $isStudent = $participant['type'] === CateringParticipantGroup::Student->label();
        $jenjang['levels'][$levelValue]['classes'][$class['id']]['students'] += $isStudent ? 1 : 0;
        $jenjang['levels'][$levelValue]['classes'][$class['id']]['employees'] += $isStudent ? 0 : 1;
        $jenjang['levels'][$levelValue]['classes'][$class['id']]['participants'][] = $participant;

        $recap['jenjangs'][$class['jenjang']] = $jenjang;
    }

    /**
     * Sum every bucket into its parents, then into the overall total.
     *
     * @param  array<string, mixed>  $recap
     */
    private static function rollUpTotals(array &$recap): int
    {
        $overall = 0;

        foreach ($recap['jenjangs'] as $jenjangKey => $jenjang) {
            $jenjangTotal = count($jenjang['educationEmployees']);
            $recap['jenjangs'][$jenjangKey]['employeeTotal'] = $jenjangTotal;

            foreach ($jenjang['levels'] as $levelKey => $level) {
                $levelTotal = count($level['levelEmployees']);
                $recap['jenjangs'][$jenjangKey]['levels'][$levelKey]['employeeTotal'] = $levelTotal;

                foreach ($level['classes'] as $classKey => $class) {
                    $classTotal = count($class['participants']);
                    $recap['jenjangs'][$jenjangKey]['levels'][$levelKey]['classes'][$classKey]['total'] = $classTotal;
                    $levelTotal += $classTotal;
                }

                $recap['jenjangs'][$jenjangKey]['levels'][$levelKey]['total'] = $levelTotal;
                $jenjangTotal += $levelTotal;
            }

            $recap['jenjangs'][$jenjangKey]['total'] = $jenjangTotal;
            $overall += $jenjangTotal;
        }

        foreach (['general', 'unplaced', 'classless'] as $bucket) {
            $recap[$bucket]['total'] = count($recap[$bucket]['participants']);
            $overall += $recap[$bucket]['total'];
        }

        self::buildDetails($recap);

        return $overall;
    }

    /**
     * Flatten the counted participants into addressable detail groups.
     *
     * The names are already in memory, so opening a detail list is a pure array
     * read with no further query.
     *
     * @param  array<string, mixed>  $recap
     */
    private static function buildDetails(array &$recap): void
    {
        $details = [];
        $headings = [];

        foreach ($recap['jenjangs'] as $jenjangKey => $jenjang) {
            foreach ($jenjang['levels'] as $levelKey => $level) {
                foreach ($level['classes'] as $classKey => $class) {
                    $key = 'class:'.$classKey;
                    $details[$key] = $class['participants'];
                    $headings[$key] = [
                        'title' => 'Detail Kelas '.$class['name'],
                        'subtitle' => $jenjang['label'].' · '.$level['label'].' · '.$class['total'].' porsi',
                    ];
                }

                if ($level['levelEmployees'] !== []) {
                    $key = 'level:'.$jenjangKey.':'.$levelKey;
                    $details[$key] = $level['levelEmployees'];
                    $headings[$key] = [
                        'title' => 'Pegawai Tingkat '.$level['label'],
                        'subtitle' => $jenjang['label'].' · '.$level['label'].' · '.$level['employeeTotal'].' porsi',
                    ];
                }
            }

            if ($jenjang['educationEmployees'] !== []) {
                $key = 'jenjang:'.$jenjangKey;
                $details[$key] = $jenjang['educationEmployees'];
                $headings[$key] = [
                    'title' => 'Pegawai Jenjang '.$jenjang['label'],
                    'subtitle' => $jenjang['label'].' · '.$jenjang['employeeTotal'].' porsi',
                ];
            }
        }

        foreach (['general', 'unplaced', 'classless'] as $bucket) {
            if ($recap[$bucket]['participants'] === []) {
                continue;
            }

            $details[$bucket] = $recap[$bucket]['participants'];
            $headings[$bucket] = [
                'title' => $recap[$bucket]['label'],
                'subtitle' => $recap[$bucket]['total'].' porsi',
            ];
        }

        $recap['details'] = $details;
        $recap['detailHeadings'] = $headings;
    }
}
