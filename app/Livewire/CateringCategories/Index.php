<?php

namespace App\Livewire\CateringCategories;

use App\Enums\CateringParticipantGroup;
use App\Models\CateringCategory;
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

    public string $price_per_day = '';

    public string $participant_group = '';

    public string $description = '';

    public bool $is_active = true;

    // Delete modal state
    public bool $showDeleteModal = false;

    public ?int $deletingId = null;

    public string $deletingName = '';

    public int $deletingMemberCount = 0;

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
        Gate::authorize('create', CateringCategory::class);
        $this->resetForm();
        $this->editingId = null;
        $this->showFormModal = true;
    }

    public function edit(int $id): void
    {
        $category = CateringCategory::findOrFail($id);
        Gate::authorize('update', $category);

        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->price_per_day = (string) $category->price_per_day;
        $this->participant_group = $category->participant_group->value;
        $this->description = (string) $category->description;
        $this->is_active = $category->is_active;
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
            $category = CateringCategory::findOrFail($this->editingId);
            Gate::authorize('update', $category);
        } else {
            Gate::authorize('create', CateringCategory::class);
            $category = new CateringCategory;
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('catering_categories', 'name')->ignore($this->editingId)],
            'price_per_day' => ['required', 'integer', 'min:0', 'max:999999999'],
            'participant_group' => ['required', Rule::enum(CateringParticipantGroup::class)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ]);

        $category->fill([
            ...$validated,
            'price_per_day' => (int) $validated['price_per_day'],
            'participant_group' => CateringParticipantGroup::from($validated['participant_group']),
        ])->save();

        $this->dispatch(
            'toast',
            type: $this->editingId ? 'info' : 'success',
            message: $this->editingId
                ? 'Kategori harga berhasil diperbarui.'
                : 'Kategori harga berhasil ditambahkan.'
        );

        $this->closeFormModal();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->price_per_day = '';
        $this->participant_group = '';
        $this->description = '';
        $this->is_active = true;
        $this->resetValidation();
    }

    // ===== DELETE MODAL =====

    public function confirmDelete(int $id): void
    {
        $category = CateringCategory::withCount('members')->findOrFail($id);
        Gate::authorize('delete', $category);

        $this->deletingId = $category->id;
        $this->deletingName = $category->name;
        $this->deletingMemberCount = $category->members_count;
        $this->showDeleteModal = true;
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->deletingName = '';
        $this->deletingMemberCount = 0;
    }

    public function deleteConfirmed(): void
    {
        if ($this->deletingId !== null) {
            $category = CateringCategory::findOrFail($this->deletingId);
            Gate::authorize('delete', $category);
            $category->delete();
            $this->dispatch(
                'toast',
                type: 'danger',
                message: 'Kategori harga berhasil dihapus.',
            );
        }
        $this->closeDeleteModal();
    }

    public function render(): View
    {
        Gate::authorize('viewAny', CateringCategory::class);

        $categories = CateringCategory::query()
            ->withCount('members')
            ->when($this->search !== '', fn (Builder $query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->status !== '', fn (Builder $query) => $query->where('is_active', $this->status === 'active'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10);

        return view('livewire.catering-categories.index', [
            'categories' => $categories,
            'participantGroupOptions' => CateringParticipantGroup::options(),
        ]);
    }
}
