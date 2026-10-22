<?php

namespace App\Livewire\CateringMembers;

use App\Enums\CateringEmployeeAssignmentType;
use App\Enums\CateringParticipantGroup;
use App\Enums\Gender;
use App\Enums\SchoolLevel;
use App\Exceptions\CateringMemberDeletionException;
use App\Models\CateringCategory;
use App\Models\CateringMember;
use App\Models\CateringMemberPeriod;
use App\Models\SchoolClass;
use App\Services\CateringBillingService;
use App\Services\CateringMemberDeletionService;
use App\Services\CateringMembershipService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public string $jenjang = '';

    public string $schoolClassId = '';

    public string $cateringCategoryId = '';

    /** @var array<int, int|string> */
    public array $selectedIds = [];

    // Form modal state
    public bool $showFormModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $school_class_id = '';

    public string $catering_category_id = '';

    public string $gender = '';

    public string $phone = '';

    public string $guardian_name = '';

    public string $guardian_phone = '';

    public string $notes = '';

    public bool $is_active = true;

    /**
     * Participation start date for a brand new participant. It becomes the
     * opening catering_member_periods row. Editing never touches membership,
     * so this field is only shown and used while creating a member.
     */
    public string $mulai_catering = '';

    // Employee placement. Only used when the chosen category declares the
    // employee participant group; students keep using school_class_id.
    public string $employee_assignment_type = '';

    public string $employee_assignment_school_class_id = '';

    public string $employee_assignment_level = '';

    public string $employee_assignment_education_level = '';

    // Delete modal state
    public bool $showDeleteModal = false;

    public ?int $deletingId = null;

    public string $deletingName = '';

    public int $deletingMemberCount = 0;

    public bool $isBulkDelete = false;

    // Membership (participation date) modals. These are kept separate from the
    // master is_active flag: a member can be active but not currently
    // participating, or participating while archived.
    public bool $showStopModal = false;

    public ?int $stoppingId = null;

    public string $stoppingName = '';

    public string $stoppingStart = '';

    public string $tanggal_terakhir = '';

    public bool $showRejoinModal = false;

    public ?int $rejoiningId = null;

    public string $rejoiningName = '';

    public string $mulai_catering_lagi = '';

    public bool $showHistoryModal = false;

    public ?int $historyId = null;

    public string $historyName = '';

    /** @var array<int, array{start: string, end: ?string}> */
    public array $historyPeriods = [];

    public function updatedSearch(): void
    {
        $this->clearSelection();
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->clearSelection();
        $this->resetPage();
    }

    public function updatedJenjang(): void
    {
        $this->clearSelection();

        if (
            $this->jenjang !== ''
            && $this->schoolClassId !== ''
            && ! SchoolClass::query()
                ->active()
                ->forJenjang($this->jenjang)
                ->whereKey($this->schoolClassId)
                ->exists()
        ) {
            $this->schoolClassId = '';
        }

        $this->resetPage();
    }

    public function updatedSchoolClassId(): void
    {
        $this->clearSelection();
        $this->resetPage();
    }

    public function updatedCateringCategoryId(): void
    {
        $this->clearSelection();
        $this->resetPage();
    }

    public function updatedPaginators(int|string $page, string $pageName): void
    {
        $this->clearSelection();
    }

    /**
     * Route property updates that need the exact property name.
     *
     * Livewire derives the specific hook name with Str::studly(), so the form's
     * `catering_category_id` and the list filter's `cateringCategoryId` both
     * resolve to `updatedCateringCategoryId`. The generic hook lets us tell
     * the two apart without renaming either field.
     */
    public function updated(string $property, mixed $value): void
    {
        if ($property === 'catering_category_id') {
            $this->resetEmployeeAssignment();
            $this->resetValidation();
        }
    }

    /**
     * Switching the placement type must drop the targets that no longer apply,
     * so a stale class or level is never carried into the next save.
     */
    public function updatedEmployeeAssignmentType(string $value): void
    {
        $type = CateringEmployeeAssignmentType::tryFrom($value);

        if (! $type?->targetsSchoolClass()) {
            $this->employee_assignment_school_class_id = '';
        }

        if (! $type?->targetsLevel()) {
            $this->employee_assignment_level = '';
        }

        if (! $type?->targetsEducationLevel()) {
            $this->employee_assignment_education_level = '';
        }

        $this->resetValidation();
    }

    // ===== FORM MODAL =====

    public function create(): void
    {
        Gate::authorize('create', CateringMember::class);
        $this->resetForm();
        $this->editingId = null;
        $this->showFormModal = true;
    }

    public function edit(int|array $params): void
    {
        $cateringMemberId = is_array($params) ? ($params['id'] ?? 0) : $params;
        $cateringMember = CateringMember::findOrFail($cateringMemberId);

        Gate::authorize('update', $cateringMember);

        $this->editingId = $cateringMember->id;
        $this->name = $cateringMember->name;
        $this->school_class_id = (string) $cateringMember->school_class_id;
        $this->catering_category_id = (string) $cateringMember->catering_category_id;
        $this->gender = $cateringMember->gender?->value ?? '';
        $this->phone = (string) $cateringMember->phone;
        $this->guardian_name = (string) $cateringMember->guardian_name;
        $this->guardian_phone = (string) $cateringMember->guardian_phone;
        $this->notes = (string) $cateringMember->notes;
        $this->is_active = $cateringMember->is_active;
        $this->loadEmployeeAssignment($cateringMember);
        $this->resetValidation();
        $this->showFormModal = true;
    }

    public function closeFormModal(): void
    {
        $this->showFormModal = false;
        $this->resetForm();
    }

    public function save(CateringMembershipService $membershipService): void
    {
        $cateringMember = $this->editingId
            ? CateringMember::findOrFail($this->editingId)
            : new CateringMember;

        Gate::authorize($this->editingId ? 'update' : 'create', $cateringMember);

        $isEmployee = $this->cateringCategoryGroup() === CateringParticipantGroup::Employee;

        $validated = $this->validate($this->rules($isEmployee));

        $mulaiCatering = (string) ($validated['mulai_catering'] ?? '');
        unset($validated['mulai_catering']);
        $isNewMember = $this->editingId === null;

        // The member row and its opening membership period are one unit of work:
        // a failure while opening the period must not leave a half-created
        // participant behind.
        DB::transaction(function () use ($cateringMember, $validated, $isEmployee, $isNewMember, $mulaiCatering, $membershipService): void {
            $cateringMember->fill([
                ...$validated,
                'school_class_id' => $validated['school_class_id'] !== '' ? (int) $validated['school_class_id'] : null,
                'catering_category_id' => (int) $validated['catering_category_id'],
                'gender' => $validated['gender'] !== '' ? $validated['gender'] : null,
            ])->save();

            $this->syncEmployeeAssignment($cateringMember, $isEmployee, $validated);

            if ($isNewMember && $mulaiCatering !== '') {
                $membershipService->start($cateringMember->id, CarbonImmutable::parse($mulaiCatering));
            }
        });

        $this->dispatch(
            'toast',
            type: $this->editingId ? 'info' : 'success',
            message: $this->editingId
                ? 'Peserta catering berhasil diperbarui.'
                : 'Peserta catering berhasil ditambahkan.'
        );

        $this->closeFormModal();
    }

    /**
     * Validation rules for the participant form.
     *
     * Employee placement is only meaningful for the employee participant group,
     * so students are held to `prohibited` and every target column is tied to
     * the chosen assignment type. That rejects conflicting targets and leaves no
     * room for a stale class or level after a type switch.
     *
     * @return array<string, array<int, mixed>>
     */
    private function rules(bool $isEmployee): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'school_class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
            'catering_category_id' => ['required', 'integer', 'exists:catering_categories,id'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'phone' => ['nullable', 'string', 'max:30'],
            'guardian_name' => ['nullable', 'string', 'max:255'],
            'guardian_phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
            // Membership only starts at creation; edits never touch periods.
            'mulai_catering' => $this->editingId === null
                ? ['required', 'date']
                : ['nullable'],
            'employee_assignment_type' => $isEmployee
                ? ['required', Rule::enum(CateringEmployeeAssignmentType::class)]
                : ['prohibited'],
            'employee_assignment_school_class_id' => [
                'nullable',
                'integer',
                Rule::exists('school_classes', 'id')->where(
                    fn (QueryBuilder $query) => $query->where('is_active', true)
                ),
                'prohibited_unless:employee_assignment_type,class',
                'required_if:employee_assignment_type,class',
            ],
            'employee_assignment_level' => [
                'nullable',
                Rule::in($this->canonicalLevelValues()),
                'prohibited_unless:employee_assignment_type,level',
                'required_if:employee_assignment_type,level',
            ],
            'employee_assignment_education_level' => [
                'nullable',
                Rule::in(SchoolLevel::educationLevels()),
                'prohibited_unless:employee_assignment_type,education_level',
                'required_if:employee_assignment_type,education_level',
            ],
        ];
    }

    /**
     * Persist the employee placement, or clear it when the participant is not
     * an employee.
     *
     * A student never carries a placement, so switching the category from
     * employee to student drops the row instead of leaving a dead assignment
     * behind for recap to pick up.
     *
     * @param  array<string, mixed>  $validated
     */
    private function syncEmployeeAssignment(
        CateringMember $cateringMember,
        bool $isEmployee,
        array $validated,
    ): void {
        if (! $isEmployee) {
            $cateringMember->employeeAssignment()->delete();

            return;
        }

        $type = CateringEmployeeAssignmentType::from($validated['employee_assignment_type']);

        $cateringMember->employeeAssignment()->updateOrCreate(
            ['catering_member_id' => $cateringMember->id],
            [
                'assignment_type' => $type,
                'school_class_id' => $type->targetsSchoolClass()
                    ? (int) $validated['employee_assignment_school_class_id']
                    : null,
                'level' => $type->targetsLevel() ? $validated['employee_assignment_level'] : null,
                'education_level' => $type->targetsEducationLevel()
                    ? $validated['employee_assignment_education_level']
                    : null,
            ],
        );
    }

    /**
     * Read the participant group of the currently selected category.
     *
     * The category's participant_group column is the source of truth, so
     * category names are never inspected.
     */
    private function cateringCategoryGroup(): ?CateringParticipantGroup
    {
        if ($this->catering_category_id === '') {
            return null;
        }

        $group = CateringCategory::query()
            ->whereKey($this->catering_category_id)
            ->value('participant_group');

        // Eloquent's value() reads through the cast, so the column arrives as
        // the enum already.
        return $group instanceof CateringParticipantGroup
            ? $group
            : (is_string($group) ? CateringParticipantGroup::tryFrom($group) : null);
    }

    /**
     * Load an existing placement into the form fields.
     */
    private function loadEmployeeAssignment(CateringMember $cateringMember): void
    {
        $assignment = $cateringMember->employeeAssignment;

        $this->employee_assignment_type = $assignment?->assignment_type?->value ?? '';
        $this->employee_assignment_school_class_id = (string) ($assignment?->school_class_id ?? '');
        $this->employee_assignment_level = (string) ($assignment?->level ?? '');
        $this->employee_assignment_education_level = (string) ($assignment?->education_level ?? '');
    }

    private function resetEmployeeAssignment(): void
    {
        $this->employee_assignment_type = '';
        $this->employee_assignment_school_class_id = '';
        $this->employee_assignment_level = '';
        $this->employee_assignment_education_level = '';
    }

    /**
     * Every canonical level value, derived from the single SchoolLevel source
     * so the form can never drift from the levels classes actually use.
     *
     * groupedOptions() keys each group by the level value, and PHP coerces
     * numeric-looking keys such as "1" to integers, so the keys are cast back
     * to string rather than merged (merging would reindex and lose them).
     *
     * @return array<int, string>
     */
    private function canonicalLevelValues(): array
    {
        $values = [];

        foreach (SchoolLevel::groupedOptions() as $levels) {
            foreach (array_keys($levels) as $level) {
                $values[] = (string) $level;
            }
        }

        return $values;
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->school_class_id = '';
        $this->catering_category_id = '';
        $this->gender = '';
        $this->phone = '';
        $this->guardian_name = '';
        $this->guardian_phone = '';
        $this->notes = '';
        $this->is_active = true;
        // New participants choose their own start date; default to today so the
        // admin can adjust it without ever inheriting created_at or 2026-09-01.
        $this->mulai_catering = CarbonImmutable::now()->toDateString();
        $this->resetEmployeeAssignment();
        $this->resetValidation();
    }

    // ===== DELETE MODAL =====

    public function selectCurrentPage(): void
    {
        Gate::authorize('viewAny', CateringMember::class);

        $this->selectedIds = $this->filteredMembersQuery()
            ->forPage($this->getPage(), 10)
            ->pluck('id')
            ->map(fn (int $id): string => (string) $id)
            ->all();
    }

    public function selectAllFiltered(): void
    {
        Gate::authorize('viewAny', CateringMember::class);

        $this->selectedIds = $this->filteredMembersQuery()
            ->pluck('id')
            ->map(fn (int $id): string => (string) $id)
            ->all();
    }

    public function clearSelection(): void
    {
        $this->selectedIds = [];

        if ($this->isBulkDelete) {
            $this->closeDeleteModal();
        }
    }

    public function confirmBulkDelete(): void
    {
        Gate::authorize('viewAny', CateringMember::class);

        $ids = $this->filteredMembersQuery()
            ->whereKey($this->normalisedSelectedIds())
            ->pluck('id')
            ->all();

        $this->selectedIds = array_map(strval(...), $ids);

        if ($ids === []) {
            $this->addError('delete', 'Tidak ada peserta valid yang dipilih.');

            return;
        }

        $this->resetErrorBag('delete');
        $this->deletingId = null;
        $this->deletingName = '';
        $this->deletingMemberCount = count($ids);
        $this->isBulkDelete = true;
        $this->showDeleteModal = true;
    }

    public function confirmDelete(int $cateringMemberId): void
    {
        $cateringMember = CateringMember::findOrFail($cateringMemberId);

        Gate::authorize('delete', $cateringMember);

        $this->resetErrorBag('delete');
        $this->deletingId = $cateringMember->id;
        $this->deletingName = $cateringMember->name;
        $this->deletingMemberCount = 1;
        $this->isBulkDelete = false;
        $this->showDeleteModal = true;
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->deletingName = '';
        $this->deletingMemberCount = 0;
        $this->isBulkDelete = false;
    }

    public function deleteConfirmed(CateringMemberDeletionService $deletionService): void
    {
        $wasBulkDelete = $this->isBulkDelete;
        $requestedIds = $this->isBulkDelete
            ? $this->normalisedSelectedIds()
            : array_filter([$this->deletingId]);

        try {
            $deletedCount = $this->deleteMembers($requestedIds, $this->isBulkDelete, $deletionService);
        } catch (CateringMemberDeletionException $exception) {
            $this->addError('delete', $exception->getMessage());

            return;
        }

        if ($deletedCount > 0) {
            $this->dispatch(
                'toast',
                type: 'danger',
                message: $wasBulkDelete
                    ? $deletedCount.' peserta catering berhasil dihapus.'
                    : 'Peserta catering berhasil dihapus.',
            );
        }

        $this->closeDeleteModal();
        $this->selectedIds = [];
        $this->resetPage();
    }

    // ===== MEMBERSHIP (PARTICIPATION PERIODS) =====

    public function openStopModal(int $cateringMemberId): void
    {
        $cateringMember = CateringMember::findOrFail($cateringMemberId);
        Gate::authorize('update', $cateringMember);

        $openPeriod = $cateringMember->periods()->whereNull('end_date')->first();

        if ($openPeriod === null) {
            $this->addError('membership', 'Peserta tidak memiliki periode keikutsertaan yang sedang berjalan.');

            return;
        }

        $this->resetErrorBag('membership');
        $this->stoppingId = $cateringMember->id;
        $this->stoppingName = $cateringMember->name;
        $this->stoppingStart = $openPeriod->start_date->locale('id')->translatedFormat('d F Y');
        $this->tanggal_terakhir = '';
        $this->showStopModal = true;
    }

    public function closeStopModal(): void
    {
        $this->showStopModal = false;
        $this->stoppingId = null;
        $this->stoppingName = '';
        $this->stoppingStart = '';
        $this->tanggal_terakhir = '';
        $this->resetValidation();
    }

    public function stopCatering(
        CateringMembershipService $membershipService,
        CateringBillingService $billingService,
    ): void {
        if ($this->stoppingId === null) {
            return;
        }

        $cateringMember = CateringMember::findOrFail($this->stoppingId);
        Gate::authorize('update', $cateringMember);

        $validated = $this->validate([
            'tanggal_terakhir' => ['required', 'date'],
        ], [
            'tanggal_terakhir.required' => 'Tanggal terakhir ikut catering wajib diisi.',
        ]);

        try {
            $endDate = CarbonImmutable::parse($validated['tanggal_terakhir']);
            DB::transaction(function () use ($membershipService, $billingService, $cateringMember, $endDate): void {
                $membershipService->stop($cateringMember->id, $endDate);
                $billingService->resynchronizeExistingBillsFrom($cateringMember->id, $endDate, auth()->user());
            });
        } catch (InvalidArgumentException $exception) {
            $this->addError('membership', $exception->getMessage());

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Keikutsertaan '.$cateringMember->name.' dihentikan.');
        $this->closeStopModal();
    }

    public function openRejoinModal(int $cateringMemberId): void
    {
        $cateringMember = CateringMember::findOrFail($cateringMemberId);
        Gate::authorize('update', $cateringMember);

        if ($cateringMember->periods()->whereNull('end_date')->exists()) {
            $this->addError('membership', 'Peserta masih memiliki periode keikutsertaan yang sedang berjalan.');

            return;
        }

        $this->resetErrorBag('membership');
        $this->rejoiningId = $cateringMember->id;
        $this->rejoiningName = $cateringMember->name;
        $this->mulai_catering_lagi = CarbonImmutable::now()->toDateString();
        $this->showRejoinModal = true;
    }

    public function closeRejoinModal(): void
    {
        $this->showRejoinModal = false;
        $this->rejoiningId = null;
        $this->rejoiningName = '';
        $this->mulai_catering_lagi = '';
        $this->resetValidation();
    }

    public function rejoinCatering(
        CateringMembershipService $membershipService,
        CateringBillingService $billingService,
    ): void {
        if ($this->rejoiningId === null) {
            return;
        }

        $cateringMember = CateringMember::findOrFail($this->rejoiningId);
        Gate::authorize('update', $cateringMember);

        $validated = $this->validate([
            'mulai_catering_lagi' => ['required', 'date'],
        ], [
            'mulai_catering_lagi.required' => 'Tanggal mulai catering lagi wajib diisi.',
        ]);

        try {
            $startDate = CarbonImmutable::parse($validated['mulai_catering_lagi']);
            DB::transaction(function () use ($membershipService, $billingService, $cateringMember, $startDate): void {
                $membershipService->rejoin($cateringMember->id, $startDate);
                $billingService->resynchronizeExistingBillsFrom($cateringMember->id, $startDate, auth()->user());
            });
        } catch (InvalidArgumentException $exception) {
            $this->addError('membership', $exception->getMessage());

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Keikutsertaan '.$cateringMember->name.' dimulai kembali.');
        $this->closeRejoinModal();
    }

    public function openHistoryModal(int $cateringMemberId): void
    {
        $cateringMember = CateringMember::findOrFail($cateringMemberId);
        Gate::authorize('view', $cateringMember);

        $this->historyId = $cateringMember->id;
        $this->historyName = $cateringMember->name;
        $this->historyPeriods = $cateringMember->periods()
            ->orderByDesc('start_date')
            ->get()
            ->map(fn (CateringMemberPeriod $period): array => [
                'start' => $period->start_date->locale('id')->translatedFormat('d F Y'),
                'end' => $period->end_date?->locale('id')->translatedFormat('d F Y'),
            ])
            ->all();
        $this->showHistoryModal = true;
    }

    public function closeHistoryModal(): void
    {
        $this->showHistoryModal = false;
        $this->historyId = null;
        $this->historyName = '';
        $this->historyPeriods = [];
    }

    public function render(): View
    {
        Gate::authorize('viewAny', CateringMember::class);

        $members = $this->filteredMembersQuery()
            ->select([
                'id',
                'name',
                'school_class_id',
                'catering_category_id',
                'gender',
                'phone',
                'guardian_name',
                'guardian_phone',
                'is_active',
                'created_at',
            ])
            ->with([
                'schoolClass:id,name',
                'cateringCategory:id,name',
                'employeeAssignment:id,catering_member_id,assignment_type,school_class_id,level,education_level',
                'employeeAssignment.schoolClass:id,name',
                'periods:id,catering_member_id,start_date,end_date',
            ])
            ->paginate(10);

        return view('livewire.catering-members.index', [
            'members' => $members,
            'classes' => SchoolClass::query()
                ->select(['id', 'name', 'level'])
                ->active()
                ->when($this->jenjang !== '', fn (Builder $query) => $query->forJenjang($this->jenjang))
                ->orderedForSelection()
                ->get(),
            'assignmentClasses' => SchoolClass::query()
                ->select(['id', 'name'])
                ->active()
                ->orderedForSelection()
                ->get(),
            'categories' => CateringCategory::query()
                ->select(['id', 'name', 'price_per_day', 'participant_group'])
                ->orderBy('name')
                ->get(),
            'genders' => Gender::cases(),
            'jenjangOptions' => SchoolLevel::educationLevels(),
            'assignmentTypes' => CateringEmployeeAssignmentType::options(),
            'levelOptions' => SchoolLevel::groupedOptions(),
            'educationLevelOptions' => SchoolLevel::educationLevels(),
            'selectedCategoryIsEmployee' => $this->selectedCategoryIsEmployee(),
            'selectedAssignmentType' => CateringEmployeeAssignmentType::tryFrom($this->employee_assignment_type),
        ]);
    }

    /**
     * Whether the form should show the employee placement section.
     *
     * Resolved from the already loaded category list so the render does not
     * add a lookup query per request.
     */
    private function selectedCategoryIsEmployee(): bool
    {
        if ($this->catering_category_id === '') {
            return false;
        }

        $category = CateringCategory::query()
            ->select(['id', 'participant_group'])
            ->whereKey($this->catering_category_id)
            ->first();

        return $category?->participant_group === CateringParticipantGroup::Employee;
    }

    /** @return Builder<CateringMember> */
    private function filteredMembersQuery(): Builder
    {
        return CateringMember::query()
            ->when($this->search !== '', fn (Builder $query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->status !== '', fn (Builder $query) => $query->where('is_active', $this->status === 'active'))
            ->when($this->jenjang !== '', fn (Builder $query) => $query->whereHas(
                'schoolClass',
                fn (Builder $schoolClassQuery) => $schoolClassQuery->forJenjang($this->jenjang),
            ))
            ->when($this->schoolClassId !== '', fn (Builder $query) => $query->where('school_class_id', $this->schoolClassId))
            ->when($this->cateringCategoryId !== '', fn (Builder $query) => $query->where('catering_category_id', $this->cateringCategoryId))
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /** @return array<int, int> */
    private function normalisedSelectedIds(): array
    {
        return collect($this->selectedIds)
            ->filter(fn (mixed $id): bool => is_numeric($id) && (int) $id > 0)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $requestedIds
     */
    private function deleteMembers(
        array $requestedIds,
        bool $applyCurrentFilters,
        CateringMemberDeletionService $deletionService,
    ): int {
        if ($requestedIds === []) {
            return 0;
        }

        $query = $applyCurrentFilters
            ? $this->filteredMembersQuery()
            : CateringMember::query();

        $members = $query
            ->whereKey($requestedIds)
            ->get(['id']);

        foreach ($members as $member) {
            Gate::authorize('delete', $member);
        }

        $ids = $members->modelKeys();

        if ($ids === []) {
            return 0;
        }

        return $deletionService->deleteMany($ids);
    }
}
