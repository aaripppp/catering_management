<?php

use App\Enums\SchoolLevel;
use App\Livewire\SchoolClasses\Index as SchoolClassIndex;
use App\Models\CateringMember;
use App\Models\SchoolClass;
use App\Models\User;
use Livewire\Livewire;

it('redirects guests away from the school classes page', function () {
    $this->get(route('school-classes.index'))->assertRedirect(route('login'));
});

it('forbids wali kelas from the school classes page', function () {
    $this->actingAs(User::factory()->waliKelas()->create())
        ->get(route('school-classes.index'))
        ->assertForbidden();
});

it('allows an admin to open the school classes page', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('school-classes.index'))
        ->assertOk();
});

it('creates a school class', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(SchoolClassIndex::class)
        ->call('create')
        ->set('name', 'VII A')
        ->set('level', '7')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched(
            'toast',
            type: 'success',
            message: 'Kelas berhasil ditambahkan.',
        );

    $this->assertDatabaseHas('school_classes', ['name' => 'VII A', 'level' => '7']);
});

it('accepts every canonical school level', function () {
    $admin = User::factory()->admin()->create();

    foreach (SchoolLevel::cases() as $index => $level) {
        Livewire::actingAs($admin)
            ->test(SchoolClassIndex::class)
            ->call('create')
            ->set('name', 'Canonical Class '.$index)
            ->set('level', $level->value)
            ->call('save')
            ->assertHasNoErrors(['level']);
    }
});

it('rejects an arbitrary school level', function () {
    Livewire::actingAs(User::factory()->admin()->create())
        ->test(SchoolClassIndex::class)
        ->call('create')
        ->set('name', 'Invalid Level Class')
        ->set('level', 'VII')
        ->call('save')
        ->assertHasErrors(['level']);

    $this->assertDatabaseMissing('school_classes', ['name' => 'Invalid Level Class']);
});

it('requires a name when creating a school class', function () {
    Livewire::actingAs(User::factory()->admin()->create())
        ->test(SchoolClassIndex::class)
        ->call('create')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);
});

it('rejects a duplicate school class name', function () {
    SchoolClass::factory()->create(['name' => 'VII A']);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(SchoolClassIndex::class)
        ->call('create')
        ->set('name', 'VII A')
        ->call('save')
        ->assertHasErrors(['name' => 'unique']);
});

it('updates a school class', function () {
    $admin = User::factory()->admin()->create();
    $schoolClass = SchoolClass::factory()->create(['name' => 'XII A IPA', 'level' => '12']);

    Livewire::actingAs($admin)
        ->test(SchoolClassIndex::class)
        ->call('edit', $schoolClass->id)
        ->assertSet('level', '12')
        ->set('name', 'XI A IPA')
        ->set('level', '11')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched(
            'toast',
            type: 'info',
            message: 'Kelas berhasil diperbarui.',
        );

    $this->assertDatabaseHas('school_classes', [
        'id' => $schoolClass->id,
        'name' => 'XI A IPA',
        'level' => '11',
    ]);
});

it('derives jenjang from canonical school levels', function () {
    expect((new SchoolClass(['level' => 'Daycare']))->jenjang)->toBe('Daycare')
        ->and((new SchoolClass(['level' => 'TK A']))->jenjang)->toBe('TK')
        ->and((new SchoolClass(['level' => '5']))->jenjang)->toBe('SD')
        ->and((new SchoolClass(['level' => '7']))->jenjang)->toBe('SMP')
        ->and((new SchoolClass(['level' => '12']))->jenjang)->toBe('SMA');
});

it('deletes a school class that has no members', function () {
    $admin = User::factory()->admin()->create();
    $schoolClass = SchoolClass::factory()->create();

    Livewire::actingAs($admin)
        ->test(SchoolClassIndex::class)
        ->call('confirmDelete', $schoolClass->id)
        ->call('deleteConfirmed')
        ->assertDispatched(
            'toast',
            type: 'danger',
            message: 'Kelas berhasil dihapus.',
        );

    $this->assertDatabaseMissing('school_classes', ['id' => $schoolClass->id]);
});

it('refuses to delete a school class that still has members', function () {
    $admin = User::factory()->admin()->create();
    $schoolClass = SchoolClass::factory()->create();
    CateringMember::factory()->create(['school_class_id' => $schoolClass->id]);

    $this->actingAs($admin);
    expect(Gate::denies('delete', $schoolClass))->toBeTrue();

    $this->assertDatabaseHas('school_classes', ['id' => $schoolClass->id]);
});

it('forbids wali kelas from deleting a school class', function () {
    $waliKelas = User::factory()->waliKelas()->create();
    $schoolClass = SchoolClass::factory()->create();

    Livewire::actingAs($waliKelas)
        ->test(SchoolClassIndex::class)
        ->assertForbidden();

    $this->actingAs($waliKelas);
    expect(Gate::denies('delete', $schoolClass))->toBeTrue();

    $this->assertDatabaseHas('school_classes', ['id' => $schoolClass->id]);
});

it('filters the school class list by search term', function () {
    $admin = User::factory()->admin()->create();
    SchoolClass::factory()->create(['name' => 'VII A']);
    SchoolClass::factory()->create(['name' => 'IX B']);

    Livewire::actingAs($admin)
        ->test(SchoolClassIndex::class)
        ->set('search', 'VII')
        ->assertSee('VII A')
        ->assertDontSee('IX B');
});

it('filters the school class list by canonical level', function () {
    $admin = User::factory()->admin()->create();
    SchoolClass::factory()->create(['name' => 'Alpha Class', 'level' => '7']);
    SchoolClass::factory()->create(['name' => 'Beta Class', 'level' => '8']);

    Livewire::actingAs($admin)
        ->test(SchoolClassIndex::class)
        ->set('search', '7')
        ->assertSee('Alpha Class')
        ->assertDontSee('Beta Class');
});

it('filters the school class list by status', function () {
    $admin = User::factory()->admin()->create();
    SchoolClass::factory()->create(['name' => 'VII A', 'is_active' => true]);
    SchoolClass::factory()->inactive()->create(['name' => 'IX B']);

    Livewire::actingAs($admin)
        ->test(SchoolClassIndex::class)
        ->set('status', 'inactive')
        ->assertSee('IX B')
        ->assertDontSee('VII A');
});

it('paginates school classes by ten records', function () {
    $admin = User::factory()->admin()->create();
    SchoolClass::factory()->count(11)->create();

    Livewire::actingAs($admin)
        ->test(SchoolClassIndex::class)
        ->assertViewHas('classes', fn ($classes): bool => $classes->perPage() === 10 && $classes->count() === 10);
});

it('normalizes unambiguous legacy school levels', function () {
    SchoolClass::factory()->create(['name' => 'VII A', 'level' => 'VII']);
    SchoolClass::factory()->create(['name' => 'VIII A', 'level' => 'VIII']);
    SchoolClass::factory()->create(['name' => 'IX A', 'level' => 'IX']);
    SchoolClass::factory()->create(['name' => 'TK A', 'level' => 'TK']);
    SchoolClass::factory()->create(['name' => 'TK B', 'level' => 'TK']);
    SchoolClass::factory()->create(['name' => 'Daycare A', 'level' => 'Daycare']);

    $this->artisan('school-classes:normalize-levels')->assertSuccessful();

    expect(SchoolClass::where('name', 'VII A')->value('level'))->toBe('7')
        ->and(SchoolClass::where('name', 'VIII A')->value('level'))->toBe('8')
        ->and(SchoolClass::where('name', 'IX A')->value('level'))->toBe('9')
        ->and(SchoolClass::where('name', 'TK A')->value('level'))->toBe('TK A')
        ->and(SchoolClass::where('name', 'TK B')->value('level'))->toBe('TK B')
        ->and(SchoolClass::where('name', 'Daycare A')->value('level'))->toBe('Daycare');
});

it('does not guess an ambiguous legacy TK level', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => 'TK C', 'level' => 'TK']);

    $this->artisan('school-classes:normalize-levels')->assertFailed();

    expect($schoolClass->fresh()->level)->toBe('TK');
});
