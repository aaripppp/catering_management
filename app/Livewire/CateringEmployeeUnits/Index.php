<?php

namespace App\Livewire\CateringEmployeeUnits;

use App\Models\CateringEmployeeUnit;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Index extends Component
{
    public bool $showEditModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $price_per_person = '';

    public bool $is_active = true;

    public function edit(int $unitId): void
    {
        $unit = CateringEmployeeUnit::query()->findOrFail($unitId);
        Gate::authorize('update', $unit);

        $this->editingId = $unit->id;
        $this->name = $unit->name;
        $this->price_per_person = (string) $unit->price_per_person;
        $this->is_active = $unit->is_active;
        $this->resetValidation();
        $this->showEditModal = true;
    }

    public function closeEditModal(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        $unit = CateringEmployeeUnit::query()->findOrFail($this->editingId);
        Gate::authorize('update', $unit);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('catering_employee_units', 'name')->ignore($unit->id)],
            'price_per_person' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $unit->update([
            'name' => $validated['name'],
            'price_per_person' => (int) $validated['price_per_person'],
            'is_active' => $validated['is_active'],
        ]);

        $this->dispatch('toast', type: 'success', message: 'Unit catering berhasil diperbarui.');
        $this->resetForm();
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
        $this->showEditModal = false;
        $this->editingId = null;
        $this->name = '';
        $this->price_per_person = '';
        $this->is_active = true;
        $this->resetValidation();
    }
}
