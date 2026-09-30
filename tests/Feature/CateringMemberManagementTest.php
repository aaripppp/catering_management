<?php

use App\Enums\Gender;
use App\Livewire\CateringMembers\Index as CateringMemberIndex;
use App\Models\CateringCategory;
use App\Models\CateringMember;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

it('redirects guests away from the catering members page', function () {
    $this->get(route('catering-members.index'))->assertRedirect(route('login'));
});

it('forbids wali kelas from the catering members page', function () {
    $this->actingAs(User::factory()->waliKelas()->create())
        ->get(route('catering-members.index'))
        ->assertForbidden();
});

it('allows an admin to open the catering members page', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('catering-members.index'))
        ->assertOk();
});

it('creates a catering member linked to a class and a category', function () {
    $admin = User::factory()->admin()->create();
    $schoolClass = SchoolClass::factory()->create();
    $category = CateringCategory::factory()->create();

    Livewire::actingAs($admin)
        ->test(CateringMemberIndex::class)
        ->call('create')
        ->set('name', 'Ahmad Fauzi')
        ->set('school_class_id', (string) $schoolClass->id)
        ->set('catering_category_id', (string) $category->id)
        ->set('gender', Gender::Male->value)
        ->set('phone', '081234567890')
        ->call('save')
        ->assertHasNoErrors();

    $member = CateringMember::where('name', 'Ahmad Fauzi')->firstOrFail();

    expect($member->school_class_id)->toBe($schoolClass->id)
        ->and($member->catering_category_id)->toBe($category->id)
        ->and($member->gender)->toBe(Gender::Male)
        ->and($member->phone)->toBe('081234567890');
});

it('creates a catering member without a class', function () {
    $admin = User::factory()->admin()->create();
    $category = CateringCategory::factory()->create();

    Livewire::actingAs($admin)
        ->test(CateringMemberIndex::class)
        ->call('create')
        ->set('name', 'Tanpa Kelas')
        ->set('school_class_id', '')
        ->set('catering_category_id', (string) $category->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(CateringMember::where('name', 'Tanpa Kelas')->firstOrFail()->school_class_id)->toBeNull();
});

it('requires a name and a catering category when creating a member', function () {
    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMemberIndex::class)
        ->call('create')
        ->set('name', '')
        ->set('catering_category_id', '')
        ->call('save')
        ->assertHasErrors(['name' => 'required', 'catering_category_id' => 'required']);
});

it('rejects an unknown gender value', function () {
    $category = CateringCategory::factory()->create();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMemberIndex::class)
        ->call('create')
        ->set('name', 'Salah Gender')
        ->set('catering_category_id', (string) $category->id)
        ->set('gender', 'X')
        ->call('save')
        ->assertHasErrors(['gender']);
});

it('updates a catering member', function () {
    $admin = User::factory()->admin()->create();
    $member = CateringMember::factory()->create(['name' => 'Ahmad Fauzi']);
    $category = CateringCategory::factory()->create();

    Livewire::actingAs($admin)
        ->test(CateringMemberIndex::class)
        ->call('edit', ['id' => $member->id])
        ->set('name', 'Ahmad Fauzi Jr')
        ->set('catering_category_id', (string) $category->id)
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('catering_members', [
        'id' => $member->id,
        'name' => 'Ahmad Fauzi Jr',
        'catering_category_id' => $category->id,
    ]);
});

it('deletes a catering member', function () {
    $admin = User::factory()->admin()->create();
    $member = CateringMember::factory()->create();

    Livewire::actingAs($admin)
        ->test(CateringMemberIndex::class)
        ->call('confirmDelete', $member->id)
        ->call('deleteConfirmed');

    $this->assertDatabaseMissing('catering_members', ['id' => $member->id]);
});

it('forbids wali kelas from creating a catering member', function () {
    $waliKelas = User::factory()->waliKelas()->create();
    $this->actingAs($waliKelas);
    expect(Gate::denies('create', CateringMember::class))->toBeTrue();
});

it('forbids wali kelas from deleting a catering member', function () {
    $waliKelas = User::factory()->waliKelas()->create();
    $member = CateringMember::factory()->create();

    Livewire::actingAs($waliKelas)
        ->test(CateringMemberIndex::class)
        ->assertForbidden();

    $this->actingAs($waliKelas);
    expect(Gate::denies('delete', $member))->toBeTrue();

    $this->assertDatabaseHas('catering_members', ['id' => $member->id]);
});

it('filters the catering member list by search term', function () {
    $admin = User::factory()->admin()->create();
    CateringMember::factory()->create(['name' => 'Ahmad Fauzi']);
    CateringMember::factory()->create(['name' => 'Dewi Lestari']);

    Livewire::actingAs($admin)
        ->test(CateringMemberIndex::class)
        ->set('search', 'Dewi')
        ->assertSee('Dewi Lestari')
        ->assertDontSee('Ahmad Fauzi');
});

it('filters the catering member list by status', function () {
    $admin = User::factory()->admin()->create();
    CateringMember::factory()->create(['name' => 'Ahmad Fauzi', 'is_active' => true]);
    CateringMember::factory()->inactive()->create(['name' => 'Dewi Lestari']);

    Livewire::actingAs($admin)
        ->test(CateringMemberIndex::class)
        ->set('status', 'inactive')
        ->assertSee('Dewi Lestari')
        ->assertDontSee('Ahmad Fauzi');
});

it('filters the catering member list by school class', function () {
    $admin = User::factory()->admin()->create();
    $classA = SchoolClass::factory()->create();
    $classB = SchoolClass::factory()->create();
    CateringMember::factory()->create(['name' => 'Ahmad Fauzi', 'school_class_id' => $classA->id]);
    CateringMember::factory()->create(['name' => 'Dewi Lestari', 'school_class_id' => $classB->id]);

    Livewire::actingAs($admin)
        ->test(CateringMemberIndex::class)
        ->set('schoolClassId', (string) $classA->id)
        ->assertSee('Ahmad Fauzi')
        ->assertDontSee('Dewi Lestari');
});

it('orders active class options by canonical level and natural class name', function () {
    foreach ([
        ['name' => '12B-IPS', 'level' => '12'],
        ['name' => '10A', 'level' => '10'],
        ['name' => '1B', 'level' => '1'],
        ['name' => 'KB-2', 'level' => 'KB'],
        ['name' => 'A-1', 'level' => 'TK A'],
        ['name' => 'Daycare A', 'level' => 'Daycare'],
        ['name' => '12A-IPS', 'level' => '12'],
        ['name' => '2A', 'level' => '2'],
        ['name' => 'B-1', 'level' => 'TK B'],
        ['name' => '12B-IPA', 'level' => '12'],
        ['name' => '7A', 'level' => '7'],
        ['name' => 'KB-1', 'level' => 'KB'],
        ['name' => '1A', 'level' => '1'],
        ['name' => '11A', 'level' => '11'],
        ['name' => '12A-IPA', 'level' => '12'],
    ] as $class) {
        SchoolClass::factory()->create($class);
    }

    SchoolClass::factory()->inactive()->create(['name' => 'Inactive 1A', 'level' => '1']);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMemberIndex::class)
        ->assertViewHas('classes', fn ($classes): bool => $classes->pluck('name')->all() === [
            'Daycare A',
            'KB-1',
            'KB-2',
            'A-1',
            'B-1',
            '1A',
            '1B',
            '2A',
            '7A',
            '10A',
            '11A',
            '12A-IPA',
            '12B-IPA',
            '12A-IPS',
            '12B-IPS',
        ]);
});

it('filters catering members by every jenjang', function () {
    $admin = User::factory()->admin()->create();
    $members = [];

    foreach ([
        'Daycare' => ['level' => 'Daycare', 'class' => 'Daycare A'],
        'TK' => ['level' => 'TK A', 'class' => 'A-1'],
        'SD' => ['level' => '4', 'class' => '4A'],
        'SMP' => ['level' => '8', 'class' => '8A'],
        'SMA' => ['level' => '11', 'class' => '11A'],
    ] as $jenjang => $classData) {
        $schoolClass = SchoolClass::factory()->create([
            'name' => $classData['class'],
            'level' => $classData['level'],
        ]);
        $memberName = $jenjang.' Member';
        CateringMember::factory()->create([
            'name' => $memberName,
            'school_class_id' => $schoolClass->id,
        ]);
        $members[$jenjang] = $memberName;
    }

    foreach ($members as $jenjang => $memberName) {
        $component = Livewire::actingAs($admin)
            ->test(CateringMemberIndex::class)
            ->set('jenjang', $jenjang)
            ->assertSee($memberName);

        foreach ($members as $otherJenjang => $otherMemberName) {
            if ($otherJenjang !== $jenjang) {
                $component->assertDontSee($otherMemberName);
            }
        }
    }
});

it('restricts class options by jenjang and resets an incompatible class filter', function () {
    $admin = User::factory()->admin()->create();
    $class7 = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    SchoolClass::factory()->create(['name' => '8A', 'level' => '8']);
    SchoolClass::factory()->create(['name' => 'A-1', 'level' => 'TK A']);

    Livewire::actingAs($admin)
        ->test(CateringMemberIndex::class)
        ->set('schoolClassId', (string) $class7->id)
        ->set('jenjang', 'SMP')
        ->assertSet('schoolClassId', (string) $class7->id)
        ->assertViewHas('classes', fn ($classes): bool => $classes->pluck('name')->all() === ['7A', '8A'])
        ->set('jenjang', 'TK')
        ->assertSet('schoolClassId', '')
        ->assertViewHas('classes', fn ($classes): bool => $classes->pluck('name')->all() === ['A-1']);
});

it('keeps member pagination server-side and eager loads displayed relations', function () {
    $admin = User::factory()->admin()->create();
    $schoolClass = SchoolClass::factory()->create(['level' => '7']);
    $category = CateringCategory::factory()->create();
    CateringMember::factory()->count(11)->create([
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);

    Livewire::actingAs($admin)
        ->test(CateringMemberIndex::class)
        ->assertViewHas('members', function ($members): bool {
            return $members->perPage() === 10
                && $members->count() === 10
                && $members->getCollection()->every(fn (CateringMember $member): bool => $member->relationLoaded('schoolClass')
                    && $member->relationLoaded('cateringCategory'));
        })
        ->call('setPage', 2)
        ->set('jenjang', 'SMP')
        ->assertSet('paginators.page', 1);
});

it('filters the catering member list by catering category', function () {
    $admin = User::factory()->admin()->create();
    $categoryA = CateringCategory::factory()->create();
    $categoryB = CateringCategory::factory()->create();
    CateringMember::factory()->create(['name' => 'Ahmad Fauzi', 'catering_category_id' => $categoryA->id]);
    CateringMember::factory()->create(['name' => 'Dewi Lestari', 'catering_category_id' => $categoryB->id]);

    Livewire::actingAs($admin)
        ->test(CateringMemberIndex::class)
        ->set('cateringCategoryId', (string) $categoryA->id)
        ->assertSee('Ahmad Fauzi')
        ->assertDontSee('Dewi Lestari');
});

it('does not store a price on the member record', function () {
    $member = CateringMember::factory()->create();

    expect(Schema::hasColumn('catering_members', 'price_per_day'))->toBeFalse()
        ->and(Schema::hasColumn('catering_categories', 'price_per_day'))->toBeTrue()
        ->and($member->getAttributes())->not->toHaveKey('price_per_day');
});

it('detaches the class from a member when the class is deleted', function () {
    $member = CateringMember::factory()->create();

    expect($member->school_class_id)->not->toBeNull();

    $member->schoolClass->delete();

    expect($member->fresh()->school_class_id)->toBeNull();
});
