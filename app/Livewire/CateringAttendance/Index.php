<?php

namespace App\Livewire\CateringAttendance;

use App\Enums\CateringAttendanceStatus;
use App\Enums\CateringParticipantGroup;
use App\Enums\SchoolLevel;
use App\Models\CateringAttendance;
use App\Models\CateringMember;
use App\Models\SchoolClass;
use App\Services\CateringAttendanceInitializerService;
use App\Services\CateringInvoiceService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class Index extends Component
{
    public int $month = 1;

    public int $year = 2026;

    public string $jenjang = '';

    public string $schoolClassId = '';

    public string $participantGroup = CateringParticipantGroup::Student->value;

    /** @var array<int, array{id: int, name: string, level: string}> */
    public array $classOptions = [];

    /** @var array<int, array{id: int, name: string, school_class_id: int}> */
    #[Locked]
    public array $participants = [];

    /** @var array<int, array{date: string, day: int, weekday: string, isWeekend: bool}> */
    #[Locked]
    public array $dates = [];

    /** @var array<int, array<string, string>> */
    public array $attendance = [];

    /**
     * True while a cell or whole date write is in flight.
     *
     * Drives the inline saving indicator. There is no manual save step any more,
     * so this never gates an action; it is purely feedback.
     */
    public bool $saving = false;

    /**
     * Timestamp of the last successful auto-save, used to render "Tersimpan".
     *
     * @var array{saved: bool, at: string|null}
     */
    #[Locked]
    public array $saveState = ['saved' => false, 'at' => null];

    public ?int $editingMemberId = null;

    public ?string $editingDate = null;

    #[Locked]
    public ?int $loadedClassId = null;

    #[Locked]
    public ?string $loadedParticipantGroup = null;

    #[Locked]
    public bool $matrixLoaded = false;

    #[Locked]
    public ?int $loadedMonth = null;

    #[Locked]
    public ?int $loadedYear = null;

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);

        $this->month = now()->month;
        $this->year = now()->year;
    }

    public function changeFilter(string $filter, string|int $value): void
    {
        abort_unless(auth()->check(), 403);

        match ($filter) {
            'month' => $this->changeMonth((int) $value),
            'year' => $this->changeYear((int) $value),
            'jenjang' => $this->changeJenjang((string) $value),
            'schoolClassId' => $this->changeSchoolClass((string) $value),
            'participantGroup' => $this->changeParticipantGroup((string) $value),
            default => throw ValidationException::withMessages([
                'filter' => 'Filter absensi tidak valid.',
            ]),
        };
    }

    /**
     * Load the attendance matrix and initialise any missing records.
     *
     * Opening a valid context ensures every active catering member has one row
     * per day of the selected month before this context is read.
     */
    public function loadMatrix(): void
    {
        abort_unless(auth()->check(), 403);

        $this->validateSelection();
        $group = $this->selectedParticipantGroup();
        $schoolClass = $this->resolveSelectedClass();

        $start = CarbonImmutable::create($this->year, $this->month, 1)->startOfMonth();
        $end = $start->endOfMonth();
        $dates = $this->generateDates($start);

        CateringAttendanceInitializerService::ensureMonth($this->year, $this->month);

        $participants = $this->participantQuery($group, $schoolClass)
            ->get()
            ->map(fn (CateringMember $member): array => [
                'id' => $member->id,
                'name' => $member->name,
                'school_class_id' => $member->school_class_id,
            ])
            ->values()
            ->all();

        $attendanceByMember = [];
        $participantIds = array_column($participants, 'id');

        if ($participantIds !== []) {
            // One query for the whole month, never one per participant or cell.
            $savedAttendance = CateringAttendance::query()
                ->select(['catering_member_id', 'attendance_date', 'status'])
                ->whereIn('catering_member_id', $participantIds)
                ->where('attendance_date', '>=', $start->toDateString())
                ->where('attendance_date', '<', $end->addDay()->toDateString())
                ->get();

            foreach ($savedAttendance as $saved) {
                $attendanceByMember[$saved->catering_member_id][$saved->attendance_date->toDateString()] = $saved->status->value;
            }
        }

        $attendance = [];

        foreach ($participants as $participant) {
            foreach ($dates as $date) {
                $attendance[$participant['id']][$date['date']] = $attendanceByMember[$participant['id']][$date['date']]
                    ?? $this->defaultStatus($date);
            }
        }

        $this->participants = $participants;
        $this->dates = $dates;
        $this->attendance = $attendance;
        $this->loadedClassId = $schoolClass?->id;
        $this->loadedParticipantGroup = $group->value;
        $this->loadedMonth = $this->month;
        $this->loadedYear = $this->year;
        $this->matrixLoaded = true;
        $this->closeStatusMenu();
        $this->resetValidation();
    }

    public function openStatusMenu(int $memberId, string $date): void
    {
        $this->ensureCellBelongsToMatrix($memberId, $date);

        $this->editingMemberId = $memberId;
        $this->editingDate = $date;
    }

    public function closeStatusMenu(): void
    {
        $this->editingMemberId = null;
        $this->editingDate = null;
    }

    /**
     * Change one cell and persist it immediately.
     *
     * Only the member and date that were touched are written, so an edit made by
     * another user on a different cell is never overwritten.
     */
    public function setCellStatus(int $memberId, string $date, string $status): void
    {
        abort_unless(auth()->check(), 403);

        $this->ensureCellBelongsToMatrix($memberId, $date);
        $validated = Validator::make(
            ['status' => $status],
            ['status' => ['required', Rule::enum(CateringAttendanceStatus::class)]],
        )->validate();

        $previous = $this->attendance[$memberId][$date] ?? null;
        $this->closeStatusMenu();

        // Reflect the choice immediately, then roll it back if the write fails.
        $this->attendance[$memberId][$date] = $validated['status'];
        $this->saving = true;

        try {
            $this->writeAttendance([[
                'catering_member_id' => $memberId,
                'attendance_date' => $date,
                'status' => $validated['status'],
            ]]);
        } catch (Throwable $exception) {
            if ($previous !== null) {
                $this->attendance[$memberId][$date] = $previous;
            }

            $this->saving = false;
            $this->reportSaveFailure($exception);

            return;
        }

        $this->saving = false;
        $this->markSaved();
    }

    /**
     * Change one whole date for every participant in the loaded context.
     *
     * Written as a single bulk upsert so the cost does not scale with the number
     * of participants in the class.
     */
    public function setDateStatus(string $date, string $status): void
    {
        abort_unless(auth()->check(), 403);

        $validated = Validator::make(
            ['status' => $status],
            ['status' => ['required', Rule::enum(CateringAttendanceStatus::class)]],
        )->validate();

        if (! in_array($date, array_column($this->dates, 'date'), true)) {
            throw ValidationException::withMessages([
                'date' => 'Tanggal tidak termasuk dalam matriks yang sedang dibuka.',
            ]);
        }

        $previous = [];
        $payload = [];

        foreach ($this->participants as $participant) {
            $memberId = $participant['id'];
            $previous[$memberId] = $this->attendance[$memberId][$date] ?? null;
            $this->attendance[$memberId][$date] = $validated['status'];
            $payload[] = [
                'catering_member_id' => $memberId,
                'attendance_date' => $date,
                'status' => $validated['status'],
            ];
        }

        if ($payload === []) {
            return;
        }

        $this->saving = true;

        try {
            $this->writeAttendance($payload);
        } catch (Throwable $exception) {
            foreach ($previous as $memberId => $previousStatus) {
                if ($previousStatus !== null) {
                    $this->attendance[$memberId][$date] = $previousStatus;
                }
            }

            $this->saving = false;
            $this->reportSaveFailure($exception);

            return;
        }

        $this->saving = false;
        $this->markSaved();
    }

    /**
     * Inline preview URL for one participant invoice.
     *
     * Every cell write is persisted immediately, so the link always reflects
     * saved attendance and never needs a manual save first.
     */
    public function memberInvoiceUrl(int $memberId): ?string
    {
        abort_unless(auth()->check(), 403);

        if (! $this->matrixLoaded) {
            return null;
        }

        if (! in_array($memberId, array_column($this->participants, 'id'), true)) {
            return null;
        }

        return route('catering-attendance.invoice.member', [
            'member' => $memberId,
            'month' => $this->month,
            'year' => $this->year,
            'participantGroup' => $this->participantGroup,
            'schoolClassId' => $this->schoolClassId !== '' ? $this->schoolClassId : null,
        ]);
    }

    /**
     * Inline preview URL for the class summary invoice.
     *
     * Returns null for participant groups without a class context, such as employees.
     */
    public function classInvoiceUrl(): ?string
    {
        abort_unless(auth()->check(), 403);

        $group = CateringParticipantGroup::tryFrom($this->participantGroup);

        if (! $group?->requiresClassSelection() || $this->schoolClassId === '') {
            return null;
        }

        return route('catering-attendance.invoice.class', [
            'schoolClass' => $this->schoolClassId,
            'month' => $this->month,
            'year' => $this->year,
        ]);
    }

    public function downloadAllInvoices(): ?StreamedResponse
    {
        abort_unless(auth()->check(), 403);

        $this->validateSelection();

        $group = $this->selectedParticipantGroup();
        $schoolClass = $this->resolveSelectedClass();
        [$start, $end] = $this->selectedMonthRange();

        $service = $this->cateringInvoiceService();
        $invoices = array_values(array_filter(
            $service->buildBulkInvoices($group, $schoolClass, $start, $end),
            fn (array $invoice): bool => $invoice['savedDays'] > 0,
        ));

        if ($invoices === []) {
            $this->dispatch(
                'toast',
                type: 'warning',
                message: 'Absensi peserta untuk periode ini belum disimpan.',
            );

            return null;
        }

        return $service->bulkZipResponse($invoices, $schoolClass?->name ?? $group->label(), $start);
    }

    public function render(): View
    {
        abort_unless(auth()->check(), 403);

        $selectedGroup = CateringParticipantGroup::tryFrom($this->participantGroup);

        return view('livewire.catering-attendance.index', [
            'monthOptions' => $this->monthOptions(),
            'yearOptions' => range(now()->year - 3, now()->year + 3),
            'jenjangOptions' => SchoolLevel::educationLevels(),
            'participantGroupOptions' => CateringParticipantGroup::options(),
            'hasValidParticipantGroup' => $selectedGroup !== null,
            'requiresClassSelection' => $selectedGroup?->requiresClassSelection() ?? false,
            'statusOptions' => CateringAttendanceStatus::cases(),
            'summary' => $this->summary(),
        ]);
    }

    /**
     * The selected month as a bounded start/end range.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function selectedMonthRange(): array
    {
        $start = CarbonImmutable::create($this->year, $this->month, 1)->startOfMonth();

        return [$start, $start->endOfMonth()];
    }

    private function cateringInvoiceService(): CateringInvoiceService
    {
        return app(CateringInvoiceService::class);
    }

    /**
     * Resolve the participant group chosen on the filter bar.
     */
    private function selectedParticipantGroup(): CateringParticipantGroup
    {
        return CateringParticipantGroup::from($this->participantGroup);
    }

    /**
     * Resolve the selected class, which is only meaningful for class based groups.
     */
    private function resolveSelectedClass(): ?SchoolClass
    {
        if (! $this->selectedParticipantGroup()->requiresClassSelection()) {
            return null;
        }

        $schoolClass = SchoolClass::query()
            ->select(['id', 'name', 'level'])
            ->active()
            ->forJenjang($this->jenjang)
            ->whereKey((int) $this->schoolClassId)
            ->first();

        if ($schoolClass === null) {
            throw ValidationException::withMessages([
                'schoolClassId' => 'Kelas aktif tidak ditemukan pada jenjang yang dipilih.',
            ]);
        }

        return $schoolClass;
    }

    /**
     * Build the participant query for a group, filtered in the database by the
     * category's participant_group column.
     *
     * @return Builder<CateringMember>
     */
    private function participantQuery(CateringParticipantGroup $group, ?SchoolClass $schoolClass): Builder
    {
        return CateringMember::query()
            ->select(['id', 'name', 'school_class_id'])
            ->participantGroup($group)
            ->when(
                $schoolClass !== null,
                fn (Builder $query): Builder => $query->where('school_class_id', $schoolClass->id),
            )
            ->active()
            ->orderBy('name')
            ->orderBy('id');
    }

    private function changeMonth(int $month): void
    {
        $this->month = $month;
        $this->validateOnly('month', ['month' => ['required', 'integer', 'between:1,12']]);
        $this->reloadSelectedMatrix();
    }

    private function changeYear(int $year): void
    {
        $this->year = $year;
        $this->validateOnly('year', ['year' => ['required', 'integer', 'between:2000,2100']]);
        $this->reloadSelectedMatrix();
    }

    private function changeParticipantGroup(string $participantGroup): void
    {
        $this->participantGroup = $participantGroup;

        if (CateringParticipantGroup::tryFrom($participantGroup) === null) {
            $this->clearMatrix();
        }

        $this->validateOnly('participantGroup', [
            'participantGroup' => ['required', Rule::enum(CateringParticipantGroup::class)],
        ]);

        $this->reloadSelectedMatrix();
    }

    private function changeJenjang(string $jenjang): void
    {
        $this->jenjang = $jenjang;

        if ($jenjang === '') {
            $this->classOptions = [];
            $this->schoolClassId = '';
            $this->clearMatrix();
            $this->resetValidation('jenjang');

            return;
        }

        $this->validateOnly('jenjang', [
            'jenjang' => ['required', Rule::in(SchoolLevel::educationLevels())],
        ]);
        $this->refreshClassOptions();

        if (! in_array((int) $this->schoolClassId, array_column($this->classOptions, 'id'), true)) {
            $this->schoolClassId = '';
            $this->clearMatrix();

            return;
        }

        $this->reloadSelectedMatrix();
    }

    private function changeSchoolClass(string $schoolClassId): void
    {
        $this->schoolClassId = $schoolClassId;
        $this->reloadSelectedMatrix();
    }

    private function refreshClassOptions(): void
    {
        $this->classOptions = SchoolClass::query()
            ->select(['id', 'name', 'level'])
            ->active()
            ->forJenjang($this->jenjang)
            ->orderedForSelection()
            ->get()
            ->map(fn (SchoolClass $schoolClass): array => [
                'id' => $schoolClass->id,
                'name' => $schoolClass->name,
                'level' => $schoolClass->level,
            ])
            ->values()
            ->all();
    }

    private function reloadSelectedMatrix(): void
    {
        $this->resetSaveState();

        $group = CateringParticipantGroup::tryFrom($this->participantGroup);

        if ($group === null || ($group->requiresClassSelection() && $this->schoolClassId === '')) {
            $this->clearMatrix();

            return;
        }

        $this->loadMatrix();
    }

    private function clearMatrix(): void
    {
        $this->participants = [];
        $this->dates = [];
        $this->attendance = [];
        $this->loadedClassId = null;
        $this->loadedParticipantGroup = null;
        $this->loadedMonth = null;
        $this->loadedYear = null;
        $this->matrixLoaded = false;
        $this->resetSaveState();
        $this->closeStatusMenu();
    }

    private function validateSelection(): void
    {
        $group = CateringParticipantGroup::tryFrom($this->participantGroup);

        $this->validate(array_merge([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'participantGroup' => ['required', Rule::enum(CateringParticipantGroup::class)],
        ], $group?->requiresClassSelection() ?? true
            ? [
                'jenjang' => ['required', Rule::in(SchoolLevel::educationLevels())],
                'schoolClassId' => ['required', 'integer'],
            ]
            : []));
    }

    private function ensureCellBelongsToMatrix(int $memberId, string $date): void
    {
        $participantIds = array_column($this->participants, 'id');
        $matrixDates = array_column($this->dates, 'date');

        if (! in_array($memberId, $participantIds, true) || ! in_array($date, $matrixDates, true)) {
            throw ValidationException::withMessages([
                'attendance' => 'Sel absensi tidak termasuk dalam matriks yang sedang dibuka.',
            ]);
        }
    }

    /**
     * @return array<int, array{date: string, day: int, weekday: string, isWeekend: bool}>
     */
    private function generateDates(CarbonImmutable $start): array
    {
        $weekdayLabels = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
        $dates = [];

        for ($day = 1; $day <= $start->daysInMonth; $day++) {
            $date = $start->setDay($day);
            $dates[] = [
                'date' => $date->toDateString(),
                'day' => $day,
                'weekday' => $weekdayLabels[$date->dayOfWeek],
                'isWeekend' => $date->isWeekend(),
            ];
        }

        return $dates;
    }

    /**
     * @param  array{isWeekend: bool}  $date
     */
    private function defaultStatus(array $date): string
    {
        return $date['isWeekend']
            ? CateringAttendanceStatus::Libur->value
            : CateringAttendanceStatus::Ikut->value;
    }

    /**
     * Persist the given attendance rows in as few queries as possible.
     *
     * @param  array<int, array{catering_member_id: int, attendance_date: string, status: string}>  $rows
     */
    private function writeAttendance(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $now = now();

        DB::transaction(function () use ($rows, $now): void {
            foreach (array_chunk($rows, 500) as $chunk) {
                $payload = array_map(fn (array $row): array => [
                    'catering_member_id' => $row['catering_member_id'],
                    'attendance_date' => $this->attendanceDateForStorage($row['attendance_date']),
                    'status' => $row['status'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk);

                CateringAttendance::query()->upsert(
                    $payload,
                    ['catering_member_id', 'attendance_date'],
                    ['status', 'updated_at'],
                );
            }
        });
    }

    /**
     * Format a matrix date the way the attendance model stores it.
     *
     * Using the model's own date format keeps writes in step with rows created
     * through Eloquent, which is what makes the unique key match on drivers that
     * store a full timestamp rather than a plain date.
     */
    private function attendanceDateForStorage(string $date): string
    {
        return CarbonImmutable::parse($date)
            ->startOfDay()
            ->format((new CateringAttendance)->getDateFormat());
    }

    private function markSaved(): void
    {
        $this->saveState = [
            'saved' => true,
            'at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Drop the saved indicator when the matrix context changes.
     *
     * "Tersimpan" describes the matrix currently on screen, so it must not
     * survive a filter change into a context that has never been written to.
     */
    private function resetSaveState(): void
    {
        $this->saving = false;
        $this->saveState = ['saved' => false, 'at' => null];
    }

    /**
     * Surface a failed auto-save instead of letting the UI claim it succeeded.
     */
    private function reportSaveFailure(Throwable $exception): void
    {
        report($exception);

        $this->saveState = ['saved' => false, 'at' => null];

        $this->dispatch(
            'toast',
            type: 'error',
            message: 'Absensi gagal disimpan. Perubahan dikembalikan, silakan coba lagi.',
        );
    }

    /** @return array<int, string> */
    private function monthOptions(): array
    {
        return [
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
    }

    /** @return array{participants: int, ikut: int, sakit: int, izin: int, alfa: int, tidak_ikut: int, ujian: int, event_unit: int, puasa: int, libur: int} */
    private function summary(): array
    {
        $summary = [
            'participants' => count($this->participants),
            'ikut' => 0,
            'sakit' => 0,
            'izin' => 0,
            'alfa' => 0,
            'tidak_ikut' => 0,
            'ujian' => 0,
            'event_unit' => 0,
            'puasa' => 0,
            'libur' => 0,
        ];

        foreach ($this->attendance as $statuses) {
            foreach ($statuses as $status) {
                if (array_key_exists($status, $summary)) {
                    $summary[$status]++;
                }
            }
        }

        return $summary;
    }
}
