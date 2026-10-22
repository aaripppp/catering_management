<?php

namespace App\Livewire\CateringUnitAttendance;

use App\Models\CateringEmployeeUnit;
use App\Models\CateringUnitAttendance;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Index extends Component
{
    #[Locked]
    public int $month;

    #[Locked]
    public int $year;

    /** @var array<int, array{id: int, name: string}> */
    #[Locked]
    public array $units = [];

    /** @var array<int, array{date: string, day: int, weekday: string, isWeekend: bool}> */
    #[Locked]
    public array $dates = [];

    /** @var array<int, array<string, string>> */
    public array $quantities = [];

    /** @var array<int, array<string, string>> */
    #[Locked]
    public array $originalQuantities = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', CateringUnitAttendance::class);

        $this->month = now()->month;
        $this->year = now()->year;
        $this->loadMatrix();
    }

    public function changePeriod(string $field, int $value): void
    {
        Gate::authorize('viewAny', CateringUnitAttendance::class);

        if ($this->changes() !== []) {
            $this->dispatch(
                'toast',
                type: 'danger',
                message: 'Simpan atau batalkan perubahan sebelum mengganti periode.',
            );

            return;
        }

        if ($field === 'month') {
            if ($value < 1 || $value > 12) {
                throw ValidationException::withMessages(['month' => 'Bulan tidak valid.']);
            }

            $this->month = $value;
        } elseif ($field === 'year') {
            if ($value < 2000 || $value > 2100) {
                throw ValidationException::withMessages(['year' => 'Tahun tidak valid.']);
            }

            $this->year = $value;
        } else {
            throw ValidationException::withMessages(['period' => 'Periode tidak valid.']);
        }

        $this->loadMatrix();
    }

    public function discardChanges(): void
    {
        Gate::authorize('viewAny', CateringUnitAttendance::class);

        $this->loadMatrix();
        $this->dispatch('unit-attendance-clean');
    }

    public function save(): void
    {
        Gate::authorize('updateAny', CateringUnitAttendance::class);

        $changes = $this->changes();

        if ($changes === []) {
            $this->dispatch('toast', type: 'info', message: 'Tidak ada perubahan untuk disimpan.');
            $this->dispatch('unit-attendance-clean');

            return;
        }

        $rules = [];
        foreach ($changes as $change) {
            $rules['quantities.'.$change['unit_id'].'.'.$change['date']] = ['nullable', 'integer', 'min:0'];
        }
        $this->validate($rules);

        $unitIds = array_values(array_unique(array_column($changes, 'unit_id')));
        $dates = array_values(array_unique(array_column($changes, 'date')));
        $activeUnits = CateringEmployeeUnit::query()
            ->active()
            ->whereKey($unitIds)
            ->get(['id', 'price_per_person'])
            ->keyBy('id');

        if ($activeUnits->count() !== count($unitIds)) {
            $this->loadMatrix();
            $this->addError('matrix', 'Daftar unit aktif berubah. Periksa kembali matriks sebelum menyimpan.');

            return;
        }

        $existingRows = CateringUnitAttendance::query()
            ->whereIn('catering_employee_unit_id', $unitIds)
            ->where('attendance_date', '>=', min($dates))
            ->where('attendance_date', '<', CarbonImmutable::parse(max($dates))->addDay()->toDateString())
            ->get()
            ->keyBy(fn (CateringUnitAttendance $attendance): string => $this->cellKey(
                $attendance->catering_employee_unit_id,
                $attendance->attendance_date->toDateString(),
            ));
        $now = now();
        $upserts = [];
        $deleteIds = [];

        foreach ($changes as $change) {
            $key = $this->cellKey($change['unit_id'], $change['date']);
            $existing = $existingRows->get($key);

            if ($change['value'] === '') {
                if ($existing !== null) {
                    $deleteIds[] = $existing->id;
                }

                continue;
            }

            $upserts[] = [
                'catering_employee_unit_id' => $change['unit_id'],
                'attendance_date' => $existing?->getRawOriginal('attendance_date') ?? $change['date'],
                'quantity' => (int) $change['value'],
                'price_per_person_snapshot' => $existing?->price_per_person_snapshot
                    ?? $activeUnits[$change['unit_id']]->price_per_person,
                'created_at' => $existing?->created_at ?? $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($deleteIds, $upserts): void {
            if ($deleteIds !== []) {
                CateringUnitAttendance::query()->whereKey($deleteIds)->delete();
            }

            if ($upserts !== []) {
                CateringUnitAttendance::query()->upsert(
                    $upserts,
                    ['catering_employee_unit_id', 'attendance_date'],
                    ['quantity', 'updated_at'],
                );
            }
        });

        $savedCount = count($upserts) + count($deleteIds);
        $this->loadMatrix();
        $this->dispatch('unit-attendance-clean');
        $this->dispatch(
            'toast',
            type: 'success',
            message: $savedCount.' perubahan absensi unit berhasil disimpan.',
        );
    }

    public function render(): View
    {
        Gate::authorize('viewAny', CateringUnitAttendance::class);

        return view('livewire.catering-unit-attendance.index', [
            'monthOptions' => $this->monthOptions(),
            'yearOptions' => range(now()->year - 4, now()->year + 3),
        ]);
    }

    private function loadMatrix(): void
    {
        $start = CarbonImmutable::create($this->year, $this->month, 1)->startOfMonth();
        $endExclusive = $start->addMonth();
        $units = CateringEmployeeUnit::query()
            ->active()
            ->ordered()
            ->get(['id', 'name']);
        $unitIds = $units->modelKeys();
        $savedRows = CateringUnitAttendance::query()
            ->select(['catering_employee_unit_id', 'attendance_date', 'quantity'])
            ->whereIn('catering_employee_unit_id', $unitIds)
            ->where('attendance_date', '>=', $start->toDateString())
            ->where('attendance_date', '<', $endExclusive->toDateString())
            ->get();
        $savedByCell = $savedRows->keyBy(fn (CateringUnitAttendance $attendance): string => $this->cellKey(
            $attendance->catering_employee_unit_id,
            $attendance->attendance_date->toDateString(),
        ));

        $this->units = $units
            ->map(fn (CateringEmployeeUnit $unit): array => ['id' => $unit->id, 'name' => $unit->name])
            ->all();
        $this->dates = $this->generateDates($start);
        $this->quantities = [];

        foreach ($this->units as $unit) {
            foreach ($this->dates as $date) {
                $saved = $savedByCell->get($this->cellKey($unit['id'], $date['date']));
                $this->quantities[$unit['id']][$date['date']] = $saved === null
                    ? ''
                    : (string) $saved->quantity;
            }
        }

        $this->originalQuantities = $this->quantities;
        $this->resetValidation();
    }

    /** @return array<int, array{unit_id: int, date: string, value: string}> */
    private function changes(): array
    {
        $changes = [];

        foreach ($this->units as $unit) {
            foreach ($this->dates as $date) {
                $unitId = $unit['id'];
                $dateValue = $date['date'];
                $value = (string) ($this->quantities[$unitId][$dateValue] ?? '');
                $original = (string) ($this->originalQuantities[$unitId][$dateValue] ?? '');

                if ($value !== $original) {
                    $changes[] = ['unit_id' => $unitId, 'date' => $dateValue, 'value' => $value];
                }
            }
        }

        return $changes;
    }

    /** @return array<int, array{date: string, day: int, weekday: string, isWeekend: bool}> */
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

    private function cellKey(int $unitId, string $date): string
    {
        return $unitId.'|'.$date;
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
