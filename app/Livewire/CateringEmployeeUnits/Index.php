<?php

namespace App\Livewire\CateringEmployeeUnits;

use App\Models\CateringEmployeeUnit;
use App\Models\CateringUnitAttendance;
use App\Models\CateringUnitBill;
use App\Models\CateringUnitBillDay;
use App\Models\CateringUnitPayment;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Index extends Component
{
    public bool $showFormModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $price_per_person = '';

    public string $default_employee_subsidy_rate = '0';

    public string $default_student_subsidy_rate = '0';

    public bool $is_active = true;

    public string $sort_order = '';

    public bool $showDeleteModal = false;

    public ?int $deletingId = null;

    public string $deletingName = '';

    public bool $deleteBlocked = false;

    public int $deleteAttendanceCount = 0;

    public int $deleteBillCount = 0;

    public int $deletePaymentCount = 0;

    public bool $deleteConfirmChecked = false;

    public string $deleteConfirmName = '';

    public function create(): void
    {
        Gate::authorize('create', CateringEmployeeUnit::class);
        $this->resetForm();
        $this->showFormModal = true;
    }

    public function edit(int $unitId): void
    {
        $unit = CateringEmployeeUnit::query()->findOrFail($unitId);
        Gate::authorize('update', $unit);

        $this->editingId = $unit->id;
        $this->name = $unit->name;
        $this->price_per_person = (string) $unit->price_per_person;
        $this->default_employee_subsidy_rate = (string) ($unit->default_employee_subsidy_rate ?? 0);
        $this->default_student_subsidy_rate = (string) ($unit->default_student_subsidy_rate ?? 0);
        $this->is_active = $unit->is_active;
        $this->sort_order = (string) $unit->sort_order;
        $this->resetValidation();
        $this->showFormModal = true;
    }

    public function closeFormModal(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        $isEditing = $this->editingId !== null;

        if ($isEditing) {
            $unit = CateringEmployeeUnit::query()->findOrFail($this->editingId);
            Gate::authorize('update', $unit);
        } else {
            Gate::authorize('create', CateringEmployeeUnit::class);
            $unit = new CateringEmployeeUnit;
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('catering_employee_units', 'name')->ignore($this->editingId)],
            'price_per_person' => ['required', 'integer', 'min:0'],
            'default_employee_subsidy_rate' => ['nullable', 'integer', 'min:0'],
            'default_student_subsidy_rate' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'sort_order' => ['required', 'integer', 'min:1'],
        ]);

        $unit->fill([
            'name' => $validated['name'],
            'price_per_person' => (int) $validated['price_per_person'],
            'default_employee_subsidy_rate' => (int) ($validated['default_employee_subsidy_rate'] ?? 0),
            'default_student_subsidy_rate' => (int) ($validated['default_student_subsidy_rate'] ?? 0),
            'is_active' => $validated['is_active'],
            'sort_order' => (int) $validated['sort_order'],
        ])->save();

        $this->dispatch(
            'toast',
            type: 'success',
            message: $isEditing
                ? 'Unit catering berhasil diperbarui.'
                : 'Unit catering berhasil ditambahkan.',
        );
        $this->resetForm();
    }

    public function confirmDelete(int $unitId): void
    {
        $unit = CateringEmployeeUnit::query()->findOrFail($unitId);
        Gate::authorize('delete', $unit);

        $billIds = CateringUnitBill::query()->where('catering_employee_unit_id', $unit->id)->pluck('id');

        $this->deletingId = $unit->id;
        $this->deletingName = $unit->name;
        $this->deleteBlocked = false;
        $this->deleteAttendanceCount = CateringUnitAttendance::query()->where('catering_employee_unit_id', $unit->id)->count();
        $this->deleteBillCount = $billIds->count();
        $this->deletePaymentCount = $billIds->isEmpty() ? 0 : CateringUnitPayment::query()->whereIn('catering_unit_bill_id', $billIds)->count();
        $this->deleteConfirmChecked = false;
        $this->deleteConfirmName = '';
        $this->resetValidation('delete');
        $this->showDeleteModal = true;
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->deletingName = '';
        $this->deleteBlocked = false;
        $this->deleteAttendanceCount = 0;
        $this->deleteBillCount = 0;
        $this->deletePaymentCount = 0;
        $this->deleteConfirmChecked = false;
        $this->deleteConfirmName = '';
        $this->resetValidation('delete');
    }

    public function getDeleteRequiresStrongConfirmationProperty(): bool
    {
        return $this->deleteAttendanceCount > 0 || $this->deleteBillCount > 0 || $this->deletePaymentCount > 0;
    }

    public function getDeleteCanProceedProperty(): bool
    {
        if (! $this->deleteRequiresStrongConfirmation) {
            return true;
        }

        return $this->deleteConfirmChecked && $this->deleteConfirmName === $this->deletingName;
    }

    public function deleteConfirmed(): void
    {
        if ($this->deletingId === null) {
            return;
        }

        $unit = CateringEmployeeUnit::query()->findOrFail($this->deletingId);
        Gate::authorize('delete', $unit);

        if ($this->deleteRequiresStrongConfirmation) {
            if (! $this->deleteConfirmChecked || $this->deleteConfirmName !== $this->deletingName) {
                $this->addError('delete', 'Konfirmasi penghapusan tidak valid.');

                return;
            }
        }

        DB::transaction(function () use ($unit): void {
            $billIds = CateringUnitBill::query()->where('catering_employee_unit_id', $unit->id)->pluck('id');

            if ($billIds->isNotEmpty()) {
                CateringUnitPayment::query()->whereIn('catering_unit_bill_id', $billIds)->delete();
                CateringUnitBillDay::query()->whereIn('catering_unit_bill_id', $billIds)->delete();
                CateringUnitBill::query()->whereIn('id', $billIds)->delete();
            }

            CateringUnitAttendance::query()->where('catering_employee_unit_id', $unit->id)->delete();
            $unit->delete();
        });

        $this->dispatch('toast', type: 'danger', message: 'Unit catering berhasil dihapus.');
        $this->closeDeleteModal();
    }

    public function render(): View
    {
        Gate::authorize('viewAny', CateringEmployeeUnit::class);

        return view('livewire.catering-employee-units.index', [
            'units' => CateringEmployeeUnit::query()->ordered()->get(),
        ]);
    }

    private function resetForm(): void
    {
        $this->showFormModal = false;
        $this->editingId = null;
        $this->name = '';
        $this->price_per_person = '';
        $this->default_employee_subsidy_rate = '0';
        $this->default_student_subsidy_rate = '0';
        $this->is_active = true;
        $this->sort_order = '';
        $this->resetValidation();
    }

    private function hasDependencies(CateringEmployeeUnit $unit): bool
    {
        return $unit->unitAttendances()->exists() || $unit->unitBills()->exists();
    }
}
