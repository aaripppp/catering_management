<?php

namespace App\Livewire\CateringAttendanceRecap;

use App\Enums\CateringAttendanceStatus;
use App\Enums\CateringParticipantGroup;
use App\Enums\SchoolLevel;
use App\Models\SchoolClass;
use App\Services\CateringAttendanceRecapExportService;
use App\Services\CateringAttendanceRecapService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class Index extends Component
{
    use WithPagination;

    public int $month;

    public int $year;

    public string $participantGroup = '';

    public string $jenjang = '';

    public string $schoolClassId = '';

    public string $search = '';

    public string $tab = 'summary';

    public function mount(): void
    {
        $this->month = now()->month;
        $this->year = now()->year;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['month', 'year', 'participantGroup', 'jenjang', 'schoolClassId', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function updatedParticipantGroup(): void
    {
        if ($this->participantGroup !== CateringParticipantGroup::Student->value) {
            $this->jenjang = '';
            $this->schoolClassId = '';
        }
    }

    public function updatedJenjang(): void
    {
        $this->schoolClassId = '';
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['summary', 'participants', 'groups'], true) ? $tab : 'summary';
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->month = now()->month;
        $this->year = now()->year;
        $this->participantGroup = '';
        $this->jenjang = '';
        $this->schoolClassId = '';
        $this->search = '';
        $this->resetPage();
        $this->resetValidation();
    }

    public function downloadExcel(CateringAttendanceRecapExportService $exportService): BinaryFileResponse
    {
        $filters = $this->validatedFilters();
        $path = $exportService->createExcel($filters);

        return response()
            ->download($path, $exportService->filename($filters, 'xlsx'), [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    public function render(CateringAttendanceRecapService $recapService): View
    {
        abort_unless(auth()->check(), 403);
        $filters = $this->filters();

        return view('livewire.catering-attendance-recap.index', [
            'summary' => $this->tab === 'summary' ? $recapService->summary($filters) : null,
            'participants' => $this->tab === 'participants' ? $recapService->paginateParticipants($filters) : null,
            'groups' => $this->tab === 'groups' ? $recapService->groupRows($filters) : null,
            'periodLabel' => $recapService->periodLabel($this->month, $this->year),
            'classOptions' => $this->classOptions(),
            'monthOptions' => $this->monthOptions(),
            'yearOptions' => range(now()->year - 4, now()->year + 3),
            'participantGroupOptions' => ['' => 'Semua'] + CateringParticipantGroup::options(),
            'jenjangOptions' => SchoolLevel::educationLevels(),
            'statusOptions' => CateringAttendanceStatus::cases(),
            'pdfPreviewUrl' => route('catering-attendance-recap.preview', $filters),
        ]);
    }

    /** @return array{month: int, year: int, participantGroup: string, jenjang: string, schoolClassId: string, search: string} */
    private function filters(): array
    {
        return [
            'month' => $this->month,
            'year' => $this->year,
            'participantGroup' => $this->participantGroup,
            'jenjang' => $this->jenjang,
            'schoolClassId' => $this->schoolClassId,
            'search' => trim($this->search),
        ];
    }

    /** @return array{month: int, year: int, participantGroup: string, jenjang: string, schoolClassId: string, search: string} */
    private function validatedFilters(): array
    {
        $this->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'participantGroup' => ['nullable', Rule::enum(CateringParticipantGroup::class)],
            'jenjang' => ['nullable', Rule::in(SchoolLevel::educationLevels())],
            'schoolClassId' => ['nullable', 'integer', Rule::exists('school_classes', 'id')],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->filters();
    }

    /** @return array<int, array{id: int, name: string}> */
    private function classOptions(): array
    {
        if ($this->participantGroup !== CateringParticipantGroup::Student->value) {
            return [];
        }

        return SchoolClass::query()
            ->select(['id', 'name', 'level'])
            ->when($this->jenjang !== '', fn (Builder $query): Builder => $query->forJenjang($this->jenjang))
            ->orderedForSelection()
            ->get()
            ->map(fn (SchoolClass $schoolClass): array => ['id' => $schoolClass->id, 'name' => $schoolClass->name])
            ->all();
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
}
