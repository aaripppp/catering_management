<?php

namespace App\Livewire\SchoolClasses;

use App\Enums\SchoolLevel;
use App\Models\SchoolClass;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    // Form modal state
    public bool $showFormModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $level = '';

    public bool $is_active = true;

    // Delete modal state
    public bool $showDeleteModal = false;

    public ?int $deletingId = null;

    public string $deletingName = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    // ===== FORM MODAL =====

    public function create(): void
    {
        Gate::authorize('create', SchoolClass::class);
        $this->resetForm();
        $this->editingId = null;
        $this->showFormModal = true;
    }

    public function edit(int $id): void
    {
        $schoolClass = SchoolClass::findOrFail($id);
        Gate::authorize('update', $schoolClass);

        $this->editingId = $schoolClass->id;
        $this->name = $schoolClass->name;
        $this->level = (string) $schoolClass->level;
        $this->is_active = $schoolClass->is_active;
        $this->resetValidation();
        $this->showFormModal = true;
    }

    public function closeFormModal(): void
    {
        $this->showFormModal = false;
        $this->resetForm();
    }

    public function save(): void
    {
        if ($this->editingId) {
            $schoolClass = SchoolClass::findOrFail($this->editingId);
            Gate::authorize('update', $schoolClass);
        } else {
            Gate::authorize('create', SchoolClass::class);
            $schoolClass = new SchoolClass;
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('school_classes', 'name')->ignore($this->editingId)],
            'level' => ['required', Rule::enum(SchoolLevel::class)],
            'is_active' => ['boolean'],
        ]);

        $schoolClass->fill($validated)->save();

        $this->dispatch(
            'toast',
            type: $this->editingId ? 'info' : 'success',
            message: $this->editingId
                ? 'Kelas berhasil diperbarui.'
                : 'Kelas berhasil ditambahkan.'
        );

        $this->closeFormModal();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->level = '';
        $this->is_active = true;
        $this->resetValidation();
    }

    // ===== DELETE MODAL =====

    public function confirmDelete(int $schoolClassId): void
    {
        $schoolClass = SchoolClass::withCount('members')->findOrFail($schoolClassId);

        if (! Gate::allows('delete', $schoolClass)) {
            $this->addError('delete', 'Anda tidak memiliki izin untuk menghapus kelas ini.');

            return;
        }

        $this->deletingId = $schoolClass->id;
        $this->deletingName = $schoolClass->name;
        $this->showDeleteModal = true;
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->deletingName = '';
    }

    public function deleteConfirmed(): void
    {
        if ($this->deletingId !== null) {
            $schoolClass = SchoolClass::withCount('members')->findOrFail($this->deletingId);

            if (Gate::allows('delete', $schoolClass)) {
                $schoolClass->delete();
                $this->dispatch(
                    'toast',
                    type: 'danger',
                    message: 'Kelas berhasil dihapus.',
                );
            } else {
                $this->addError('delete', 'Kelas tidak dapat dihapus karena masih memiliki peserta.');
            }
        }

        $this->closeDeleteModal();
    }

    public function render(): View
    {
        Gate::authorize('viewAny', SchoolClass::class);

        $classes = SchoolClass::query()
            ->withCount('members')
            ->when($this->search !== '', fn (Builder $query) => $query->where(function (Builder $query): void {
                $query->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('level', 'like', '%'.$this->search.'%');
            }))
            ->when($this->status !== '', fn (Builder $query) => $query->where('is_active', $this->status === 'active'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10);

        return view('livewire.school-classes.index', [
            'classes' => $classes,
            'levelOptions' => SchoolLevel::groupedOptions(),
        ]);
    }
}
