<?php

namespace App\Livewire\CateringMonthlyReport;

use App\Enums\CateringParticipantGroup;
use App\Enums\SchoolLevel;
use App\Models\CateringBill;
use App\Models\SchoolClass;
use App\Services\CateringMonthlyReportExportService;
use App\Services\CateringMonthlyReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
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

    public string $tab = 'summary';

    public function mount(): void
    {
        Gate::authorize('viewAny', CateringBill::class);

        $this->month = now()->month;
        $this->year = now()->year;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['month', 'year', 'participantGroup', 'jenjang', 'schoolClassId'], true)) {
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
        $this->tab = in_array($tab, ['summary', 'groups', 'details'], true) ? $tab : 'summary';
        $this->resetPage();
    }

    public function downloadExcel(CateringMonthlyReportExportService $exportService): BinaryFileResponse
    {
        Gate::authorize('viewAny', CateringBill::class);
        $filters = $this->validatedFilters();
        $path = $exportService->createExcel($filters);

        return response()
            ->download($path, $exportService->filename($filters, 'xlsx'), [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    public function render(CateringMonthlyReportService $reportService): View
    {
        Gate::authorize('viewAny', CateringBill::class);
        $filters = $this->filters();

        return view('livewire.catering-monthly-report.index', [
            'summary' => $reportService->summary($filters),
            'groups' => $this->tab === 'groups' ? $reportService->groupRows($filters) : null,
            'details' => $this->tab === 'details' ? $reportService->paginateDetails($filters) : null,
            'periodLabel' => $reportService->periodLabel($this->month, $this->year),
            'pdfPreviewUrl' => route('catering-monthly-report.preview', $filters),
            'classOptions' => $this->classOptions(),
            'monthOptions' => $this->monthOptions(),
            'yearOptions' => range(now()->year - 4, now()->year + 3),
            'participantGroupOptions' => ['' => 'Semua'] + CateringParticipantGroup::options(),
            'jenjangOptions' => SchoolLevel::educationLevels(),
            'groupTabLabel' => $this->participantGroup === CateringParticipantGroup::Employee->value
                ? 'Rekap Per Kelompok'
                : 'Rekap Per Kelas',
        ]);
    }

    /** @return array{month: int, year: int, participantGroup: string, jenjang: string, schoolClassId: string} */
    private function filters(): array
    {
        return [
            'month' => $this->month,
            'year' => $this->year,
            'participantGroup' => $this->participantGroup,
            'jenjang' => $this->jenjang,
            'schoolClassId' => $this->schoolClassId,
        ];
    }

    /** @return array{month: int, year: int, participantGroup: string, jenjang: string, schoolClassId: string} */
    private function validatedFilters(): array
    {
        $this->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'participantGroup' => ['nullable', Rule::enum(CateringParticipantGroup::class)],
            'jenjang' => ['nullable', Rule::in(SchoolLevel::educationLevels())],
            'schoolClassId' => ['nullable', 'integer', Rule::exists('school_classes', 'id')],
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
