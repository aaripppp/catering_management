<?php

use App\Livewire\CateringMembers\Index as CateringMemberIndex;
use App\Models\CateringAttendance;
use App\Models\CateringCategory;
use App\Models\CateringEmployeeAssignment;
use App\Models\CateringMember;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function bulkMemberComponent(): Testable
{
    return Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMemberIndex::class);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function bulkMember(array $attributes = []): CateringMember
{
    return CateringMember::factory()->create($attributes);
}

/** @return array<int, int> */
function selectedMemberIds(Testable $component): array
{
    return collect($component->get('selectedIds'))
        ->map(fn (int|string $id): int => (int) $id)
        ->sort()
        ->values()
        ->all();
}

it('binds individual row checkboxes to participant ids', function () {
    $member = bulkMember(['name' => 'Peserta Pilihan']);

    bulkMemberComponent()
        ->assertSeeHtml('wire:model.live="selectedIds"')
        ->assertSeeHtml('value="'.$member->id.'"')
        ->set('selectedIds', [(string) $member->id])
        ->assertSet('selectedIds', [(string) $member->id])
        ->assertSee('1 peserta dipilih');
});

it('selects only the ten participants visible on the current page', function () {
    bulkMember(['name' => 'Existing Member']);
    CateringMember::factory()->count(11)->create();

    $expected = CateringMember::query()
        ->orderByDesc('created_at')
        ->orderByDesc('id')
        ->limit(10)
        ->pluck('id')
        ->sort()
        ->values()
        ->all();

    $component = bulkMemberComponent()->call('selectCurrentPage');

    expect(selectedMemberIds($component))->toBe($expected)
        ->and($component->get('selectedIds'))->toHaveCount(10);
});

it('selects every participant matching the active filters beyond one page', function () {
    CateringMember::factory()->count(14)->create(['name' => 'Target Filter']);
    CateringMember::factory()->count(3)->create(['name' => 'Other']);

    $component = bulkMemberComponent()
        ->set('search', 'Target Filter')
        ->call('selectAllFiltered');

    expect($component->get('selectedIds'))->toHaveCount(14);
});

it('selects only members from the selected jenjang', function () {
    $smp = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $sd = SchoolClass::factory()->create(['name' => '1A', 'level' => '1']);
    $selected = bulkMember(['school_class_id' => $smp->id]);
    bulkMember(['school_class_id' => $sd->id]);

    $component = bulkMemberComponent()
        ->set('jenjang', 'SMP')
        ->call('selectAllFiltered');

    expect(selectedMemberIds($component))->toBe([$selected->id]);
});

it('selects only members from the selected class', function () {
    $first = SchoolClass::factory()->create(['level' => '7']);
    $second = SchoolClass::factory()->create(['level' => '7']);
    $selected = bulkMember(['school_class_id' => $first->id]);
    bulkMember(['school_class_id' => $second->id]);

    $component = bulkMemberComponent()
        ->set('schoolClassId', (string) $first->id)
        ->call('selectAllFiltered');

    expect(selectedMemberIds($component))->toBe([$selected->id]);
});

it('selects only members from the selected category', function () {
    $first = CateringCategory::factory()->create();
    $second = CateringCategory::factory()->create();
    $selected = bulkMember(['catering_category_id' => $first->id]);
    bulkMember(['catering_category_id' => $second->id]);

    $component = bulkMemberComponent()
        ->set('cateringCategoryId', (string) $first->id)
        ->call('selectAllFiltered');

    expect(selectedMemberIds($component))->toBe([$selected->id]);
});

it('selects only members with the selected status', function () {
    $selected = bulkMember(['is_active' => false]);
    bulkMember(['is_active' => true]);

    $component = bulkMemberComponent()
        ->set('status', 'inactive')
        ->call('selectAllFiltered');

    expect(selectedMemberIds($component))->toBe([$selected->id]);
});

it('selects only members matching the search term', function () {
    $selected = bulkMember(['name' => 'Nama Dicari']);
    bulkMember(['name' => 'Nama Lain']);

    $component = bulkMemberComponent()
        ->set('search', 'Dicari')
        ->call('selectAllFiltered');

    expect(selectedMemberIds($component))->toBe([$selected->id]);
});

it('clears selection when any list filter changes', function (string $filter, string $value) {
    $member = bulkMember();

    bulkMemberComponent()
        ->set('selectedIds', [(string) $member->id])
        ->set($filter, $value)
        ->assertSet('selectedIds', []);
})->with([
    'search' => ['search', 'Nama'],
    'jenjang' => ['jenjang', 'SMP'],
    'class' => ['schoolClassId', '999'],
    'category' => ['cateringCategoryId', '999'],
    'status' => ['status', 'active'],
]);

it('clears selection when the paginated page changes', function () {
    CateringMember::factory()->count(11)->create();

    bulkMemberComponent()
        ->call('selectCurrentPage')
        ->assertCount('selectedIds', 10)
        ->call('setPage', 2)
        ->assertSet('paginators.page', 2)
        ->assertSet('selectedIds', [])
        ->assertViewHas('members', fn ($members): bool => $members->perPage() === 10 && $members->count() === 1);
});

it('shows selected count and destructive cleanup warning in the confirmation modal', function () {
    $members = CateringMember::factory()->count(3)->create();

    bulkMemberComponent()
        ->set('selectedIds', $members->modelKeys())
        ->call('confirmBulkDelete')
        ->assertSet('showDeleteModal', true)
        ->assertSet('deletingMemberCount', 3)
        ->assertSee('Hapus 3 Peserta?')
        ->assertSee('data absensi dan penempatan pegawai terkait')
        ->assertSee('Tindakan ini tidak dapat dibatalkan.');
});

it('bulk deletes selected members and their related records while preserving unselected records', function () {
    $selected = bulkMember();
    $unselected = bulkMember();

    $selectedAttendance = CateringAttendance::factory()->for($selected)->create();
    $unselectedAttendance = CateringAttendance::factory()->for($unselected)->create();
    $selectedAssignment = CateringEmployeeAssignment::factory()->general()->create(['catering_member_id' => $selected->id]);
    $unselectedAssignment = CateringEmployeeAssignment::factory()->general()->create(['catering_member_id' => $unselected->id]);

    bulkMemberComponent()
        ->set('selectedIds', [(string) $selected->id])
        ->call('confirmBulkDelete')
        ->call('deleteConfirmed')
        ->assertSet('selectedIds', [])
        ->assertSet('showDeleteModal', false)
        ->assertDispatched('toast', type: 'danger', message: '1 peserta catering berhasil dihapus.');

    expect(CateringMember::query()->whereKey($selected->id)->exists())->toBeFalse()
        ->and(CateringMember::query()->whereKey($unselected->id)->exists())->toBeTrue()
        ->and(CateringAttendance::query()->whereKey($selectedAttendance->id)->exists())->toBeFalse()
        ->and(CateringAttendance::query()->whereKey($unselectedAttendance->id)->exists())->toBeTrue()
        ->and(CateringEmployeeAssignment::query()->whereKey($selectedAssignment->id)->exists())->toBeFalse()
        ->and(CateringEmployeeAssignment::query()->whereKey($unselectedAssignment->id)->exists())->toBeTrue();
});

it('rolls every related delete back when the member delete fails', function () {
    $member = bulkMember();
    $attendance = CateringAttendance::factory()->for($member)->create();
    $assignment = CateringEmployeeAssignment::factory()->general()->create(['catering_member_id' => $member->id]);

    DB::unprepared("CREATE TRIGGER prevent_bulk_member_delete BEFORE DELETE ON catering_members WHEN OLD.id = {$member->id} BEGIN SELECT RAISE(ABORT, 'blocked'); END;");

    try {
        expect(fn () => bulkMemberComponent()
            ->set('selectedIds', [(string) $member->id])
            ->call('confirmBulkDelete')
            ->call('deleteConfirmed'))
            ->toThrow(QueryException::class);
    } finally {
        DB::unprepared('DROP TRIGGER IF EXISTS prevent_bulk_member_delete');
    }

    expect(CateringMember::query()->whereKey($member->id)->exists())->toBeTrue()
        ->and(CateringAttendance::query()->whereKey($attendance->id)->exists())->toBeTrue()
        ->and(CateringEmployeeAssignment::query()->whereKey($assignment->id)->exists())->toBeTrue();
});

it('ignores forged ids outside the active filter and nonexistent ids', function () {
    $selectedClass = SchoolClass::factory()->create(['level' => '7']);
    $otherClass = SchoolClass::factory()->create(['level' => '7']);
    $selected = bulkMember(['school_class_id' => $selectedClass->id]);
    $forged = bulkMember(['school_class_id' => $otherClass->id]);

    bulkMemberComponent()
        ->set('schoolClassId', (string) $selectedClass->id)
        ->set('selectedIds', [$selected->id, $forged->id, 999999])
        ->call('confirmBulkDelete')
        ->assertSet('deletingMemberCount', 1)
        ->call('deleteConfirmed');

    expect(CateringMember::query()->whereKey($selected->id)->exists())->toBeFalse()
        ->and(CateringMember::query()->whereKey($forged->id)->exists())->toBeTrue();
});

it('can explicitly select and delete all members when no filters are active', function () {
    CateringMember::factory()->count(4)->create();

    bulkMemberComponent()
        ->call('selectAllFiltered')
        ->assertCount('selectedIds', 4)
        ->call('confirmBulkDelete')
        ->assertSet('deletingMemberCount', 4)
        ->call('deleteConfirmed')
        ->assertSet('selectedIds', []);

    expect(CateringMember::query()->count())->toBe(0);
});

it('keeps single delete compatible with attendance and employee assignment cleanup', function () {
    $member = bulkMember();
    $attendance = CateringAttendance::factory()->for($member)->create();
    $assignment = CateringEmployeeAssignment::factory()->general()->create(['catering_member_id' => $member->id]);

    bulkMemberComponent()
        ->call('confirmDelete', $member->id)
        ->assertSet('isBulkDelete', false)
        ->call('deleteConfirmed')
        ->assertDispatched('toast', type: 'danger', message: 'Peserta catering berhasil dihapus.');

    expect(CateringMember::query()->whereKey($member->id)->exists())->toBeFalse()
        ->and(CateringAttendance::query()->whereKey($attendance->id)->exists())->toBeFalse()
        ->and(CateringEmployeeAssignment::query()->whereKey($assignment->id)->exists())->toBeFalse();
});

it('uses one set based delete per table for a realistic bulk set', function () {
    $category = CateringCategory::factory()->employee()->create();
    $members = CateringMember::factory()->withoutClass()->count(25)->create([
        'catering_category_id' => $category->id,
    ]);

    foreach ($members as $index => $member) {
        CateringAttendance::factory()->for($member)->create(['attendance_date' => '2026-09-'.str_pad((string) (($index % 28) + 1), 2, '0', STR_PAD_LEFT)]);
        CateringEmployeeAssignment::factory()->general()->create(['catering_member_id' => $member->id]);
    }

    $component = bulkMemberComponent()
        ->set('selectedIds', $members->modelKeys())
        ->call('confirmBulkDelete');

    DB::enableQueryLog();
    DB::flushQueryLog();

    $component->call('deleteConfirmed');

    $deleteQueries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $query): bool => str_starts_with(strtolower($query), 'delete'));

    DB::disableQueryLog();

    expect($deleteQueries->filter(fn (string $query): bool => str_contains($query, '"catering_attendances"')))->toHaveCount(1)
        ->and($deleteQueries->filter(fn (string $query): bool => str_contains($query, '"catering_employee_assignments"')))->toHaveCount(1)
        ->and($deleteQueries->filter(fn (string $query): bool => str_contains($query, '"catering_members"')))->toHaveCount(1)
        ->and(CateringMember::query()->whereKey($members->modelKeys())->exists())->toBeFalse();
});
