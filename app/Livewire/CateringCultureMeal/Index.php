<?php

namespace App\Livewire\CateringCultureMeal;

use App\Models\CateringCultureMealIncome;
use App\Models\SchoolClass;
use App\Services\CateringCultureMealIncomeService;
use App\Services\CateringCultureMealMonitoringService;
use App\Services\CateringCultureMealSettingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;
use Livewire\Component;

class Index extends Component
{
    public int $month;

    public int $year;

    // Manual income form modal state.
    public bool $showFormModal = false;

    public ?int $editingId = null;

    public string $income_date = '';

    public string $school_class_id = '';

    public string $amount = '';

    public string $note = '';

    // Manual income delete modal state.
    public bool $showDeleteModal = false;

    public ?int $deletingId = null;

    public string $deletingLabel = '';

    // Reference price modal state.
    public bool $showPriceModal = false;

    public string $culture_meal_price = '';

    public function mount(): void
    {
        Gate::authorize('viewCateringCultureMeal');

        $this->month = now()->month;
        $this->year = now()->year;
    }

    public function createIncome(): void
    {
        Gate::authorize('createCateringCultureMeal');

        $this->resetIncomeForm();
        $this->editingId = null;
        $this->income_date = now()->toDateString();
        $this->showFormModal = true;
    }

    public function editIncome(int $id): void
    {
        $income = CateringCultureMealIncome::query()->findOrFail($id);
        Gate::authorize('updateCateringCultureMeal');

        $this->editingId = $income->id;
        $this->income_date = $income->income_date->toDateString();
        $this->school_class_id = $income->school_class_id === null ? '' : (string) $income->school_class_id;
        $this->amount = (string) $income->amount;
        $this->note = (string) $income->note;
        $this->showFormModal = true;
    }

    public function closeFormModal(): void
    {
        $this->showFormModal = false;
        $this->resetIncomeForm();
    }

    public function saveIncome(CateringCultureMealIncomeService $incomeService): void
    {
        if ($this->editingId !== null) {
            Gate::authorize('updateCateringCultureMeal');
            $income = CateringCultureMealIncome::query()->findOrFail($this->editingId);
        } else {
            Gate::authorize('createCateringCultureMeal');
            $income = null;
        }

        $validated = $this->validate([
            'income_date' => ['required', 'date'],
            'school_class_id' => ['required', 'integer', 'exists:school_classes,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $data = [
            'income_date' => $validated['income_date'],
            'school_class_id' => (int) $validated['school_class_id'],
            'amount' => (int) $validated['amount'],
            'note' => $validated['note'] !== '' ? $validated['note'] : null,
        ];

        if ($income !== null) {
            $incomeService->update($income, $data);
        } else {
            $incomeService->create($data, auth()->user());
        }

        $this->dispatch(
            'toast',
            type: $this->editingId !== null ? 'info' : 'success',
            message: $this->editingId !== null
                ? 'Pemasukan manual berhasil diperbarui.'
                : 'Pemasukan manual berhasil ditambahkan.',
        );

        $this->closeFormModal();
    }

    public function confirmDeleteIncome(int $id): void
    {
        $income = CateringCultureMealIncome::query()->findOrFail($id);
        Gate::authorize('deleteCateringCultureMeal');

        $this->deletingId = $income->id;
        $this->deletingLabel = $income->school_class_name.' · '.$income->formatted_amount;
        $this->showDeleteModal = true;
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->deletingLabel = '';
    }

    public function deleteConfirmed(CateringCultureMealIncomeService $incomeService): void
    {
        if ($this->deletingId !== null) {
            Gate::authorize('deleteCateringCultureMeal');
            $income = CateringCultureMealIncome::query()->findOrFail($this->deletingId);
            $incomeService->delete($income);

            $this->dispatch(
                'toast',
                type: 'danger',
                message: 'Pemasukan manual berhasil dihapus.',
            );
        }

        $this->closeDeleteModal();
    }

    public function editCultureMealPrice(CateringCultureMealSettingService $settingService): void
    {
        Gate::authorize('updateCateringCultureMeal');

        $this->culture_meal_price = (string) $settingService->getCultureMealPrice();
        $this->resetValidation('culture_meal_price');
        $this->showPriceModal = true;
    }

    public function closePriceModal(): void
    {
        $this->showPriceModal = false;
        $this->culture_meal_price = '';
        $this->resetValidation('culture_meal_price');
    }

    public function saveCultureMealPrice(CateringCultureMealSettingService $settingService): void
    {
        Gate::authorize('updateCateringCultureMeal');

        $validated = $this->validate([
            'culture_meal_price' => ['required', 'integer', 'min:1'],
        ]);

        $settingService->updateCultureMealPrice((int) $validated['culture_meal_price']);

        $this->dispatch(
            'toast',
            type: 'success',
            message: 'Harga Budaya Makan berhasil diperbarui.',
        );

        $this->closePriceModal();
    }

    public function render(
        CateringCultureMealMonitoringService $monitoringService,
        CateringCultureMealIncomeService $incomeService,
        CateringCultureMealSettingService $settingService,
    ): View {
        Gate::authorize('viewCateringCultureMeal');

        $this->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
        ]);

        $monitoring = $monitoringService->monitor($this->month, $this->year);
        $cultureMealPrice = $settingService->getCultureMealPrice();
        $totalRecordedBill = $monitoring['total'] * $cultureMealPrice;

        return view('livewire.catering-culture-meal.index', [
            'total' => $monitoring['total'],
            'days' => $monitoring['days'],
            'incomeRows' => $incomeService->monthlyRows($this->month, $this->year),
            'manualTotal' => $incomeService->monthlyTotal($this->month, $this->year),
            'totalRecordedBill' => $totalRecordedBill,
            'totalRecordedBillLabel' => 'Rp '.Number::format($totalRecordedBill, 0, null, 'id'),
            'cultureMealPriceLabel' => 'Rp '.Number::format($cultureMealPrice, 0, null, 'id'),
            'classOptions' => $this->classOptions(),
            'periodLabel' => $this->periodLabel(),
            'monthOptions' => $this->monthOptions(),
            'yearOptions' => range(now()->year - 4, now()->year + 3),
        ]);
    }

    /** @return array<int, array{id: int, name: string}> */
    private function classOptions(): array
    {
        return SchoolClass::query()
            ->select(['id', 'name'])
            ->orderedForSelection()
            ->get()
            ->map(fn (SchoolClass $schoolClass): array => [
                'id' => $schoolClass->id,
                'name' => $schoolClass->name,
            ])
            ->all();
    }

    private function resetIncomeForm(): void
    {
        $this->editingId = null;
        $this->income_date = '';
        $this->school_class_id = '';
        $this->amount = '';
        $this->note = '';
        $this->resetValidation();
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

    private function periodLabel(): string
    {
        return ($this->monthOptions()[$this->month] ?? (string) $this->month).' '.$this->year;
    }
}
