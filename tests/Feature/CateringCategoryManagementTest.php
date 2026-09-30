<?php

use App\Enums\CateringParticipantGroup;
use App\Livewire\CateringCategories\Index as CateringCategoryIndex;
use App\Models\CateringCategory;
use App\Models\CateringMember;
use App\Models\User;
use Illuminate\Validation\Rules\Enum as EnumRule;
use Livewire\Livewire;

it('redirects guests away from the catering categories page', function () {
    $this->get(route('catering-categories.index'))->assertRedirect(route('login'));
});

it('forbids wali kelas from the catering categories page', function () {
    $this->actingAs(User::factory()->waliKelas()->create())
        ->get(route('catering-categories.index'))
        ->assertForbidden();
});

it('allows an admin to open the catering categories page', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('catering-categories.index'))
        ->assertOk();
});

it('creates a catering category and stores the price as an integer', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(CateringCategoryIndex::class)
        ->call('create')
        ->set('name', 'Siswa Umum')
        ->set('price_per_day', '17000')
        ->set('participant_group', CateringParticipantGroup::Student->value)
        ->set('description', 'Siswa reguler')
        ->call('save')
        ->assertHasNoErrors();

    $category = CateringCategory::where('name', 'Siswa Umum')->firstOrFail();

    expect($category->price_per_day)->toBe(17000)
        ->and($category->price_per_day)->toBeInt()
        ->and($category->participant_group)->toBe(CateringParticipantGroup::Student)
        ->and($category->description)->toBe('Siswa reguler');
});

it('creates a catering category for the employee participant group', function () {
    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringCategoryIndex::class)
        ->call('create')
        ->set('name', 'Guru')
        ->set('price_per_day', '20000')
        ->set('participant_group', CateringParticipantGroup::Employee->value)
        ->call('save')
        ->assertHasNoErrors();

    expect(CateringCategory::where('name', 'Guru')->firstOrFail()->participant_group)
        ->toBe(CateringParticipantGroup::Employee);
});

it('formats the price as Indonesian rupiah', function () {
    $category = CateringCategory::factory()->create(['price_per_day' => 17000]);

    expect($category->formatted_price_per_day)->toBe('Rp 17.000');
});

it('requires a name, a price, and a participant group when creating a catering category', function () {
    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringCategoryIndex::class)
        ->call('create')
        ->set('name', '')
        ->set('price_per_day', '')
        ->set('participant_group', '')
        ->call('save')
        ->assertHasErrors([
            'name' => 'required',
            'price_per_day' => 'required',
            'participant_group' => 'required',
        ]);
});

it('never falls back to a participant group when none is selected', function () {
    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringCategoryIndex::class)
        ->call('create')
        ->set('name', 'Kategori Tanpa Kelompok')
        ->set('price_per_day', '17000')
        ->call('save')
        ->assertHasErrors(['participant_group' => 'required']);

    expect(CateringCategory::where('name', 'Kategori Tanpa Kelompok')->exists())->toBeFalse();
});

it('rejects an unknown participant group', function () {
    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringCategoryIndex::class)
        ->call('create')
        ->set('name', 'Siswa Umum')
        ->set('price_per_day', '17000')
        ->set('participant_group', 'pengasuh')
        ->call('save')
        ->assertHasErrors(['participant_group' => EnumRule::class]);

    expect(CateringCategory::where('name', 'Siswa Umum')->exists())->toBeFalse();
});

it('rejects a non numeric price', function () {
    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringCategoryIndex::class)
        ->call('create')
        ->set('name', 'Siswa Umum')
        ->set('price_per_day', 'sepuluh ribu')
        ->set('participant_group', CateringParticipantGroup::Student->value)
        ->call('save')
        ->assertHasErrors(['price_per_day' => 'integer']);
});

it('rejects a duplicate catering category name', function () {
    CateringCategory::factory()->create(['name' => 'Siswa Umum']);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringCategoryIndex::class)
        ->call('create')
        ->set('name', 'Siswa Umum')
        ->set('price_per_day', '17000')
        ->set('participant_group', CateringParticipantGroup::Student->value)
        ->call('save')
        ->assertHasErrors(['name' => 'unique']);
});

it('updates a catering category price and participant group', function () {
    $admin = User::factory()->admin()->create();
    $category = CateringCategory::factory()->create(['name' => 'Siswa Umum', 'price_per_day' => 17000]);

    Livewire::actingAs($admin)
        ->test(CateringCategoryIndex::class)
        ->call('edit', $category->id)
        ->set('name', 'Siswa Umum')
        ->set('price_per_day', '18500')
        ->set('participant_group', CateringParticipantGroup::Employee->value)
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('catering_categories', [
        'id' => $category->id,
        'price_per_day' => 18500,
        'participant_group' => CateringParticipantGroup::Employee->value,
    ]);
});

it('prefills the participant group when editing a category', function () {
    $admin = User::factory()->admin()->create();
    $category = CateringCategory::factory()->employee()->create();

    Livewire::actingAs($admin)
        ->test(CateringCategoryIndex::class)
        ->call('edit', $category->id)
        ->assertSet('participant_group', CateringParticipantGroup::Employee->value);
});

it('shows the participant group label in the category list', function () {
    $admin = User::factory()->admin()->create();
    CateringCategory::factory()->create(['name' => 'Kategori Siswa', 'participant_group' => CateringParticipantGroup::Student]);
    CateringCategory::factory()->create(['name' => 'Kategori Pegawai', 'participant_group' => CateringParticipantGroup::Employee]);

    Livewire::actingAs($admin)
        ->test(CateringCategoryIndex::class)
        ->assertSee('Kategori Siswa')
        ->assertSee('Kategori Pegawai')
        ->assertSeeInOrder(['Kategori Siswa', 'Siswa'])
        ->assertSeeInOrder(['Kategori Pegawai', 'Pegawai']);
});

it('deletes a catering category that has no members', function () {
    $admin = User::factory()->admin()->create();
    $category = CateringCategory::factory()->create();

    Livewire::actingAs($admin)
        ->test(CateringCategoryIndex::class)
        ->call('confirmDelete', $category->id)
        ->call('deleteConfirmed');

    $this->assertDatabaseMissing('catering_categories', ['id' => $category->id]);
});

it('deletes a catering category with members (cascade delete on MySQL)', function () {
    // Skip on SQLite - cascade delete only works on MySQL
    if (config('database.default') === 'sqlite') {
        $this->markTestSkipped('Cascade delete only works on MySQL');
    }

    $admin = User::factory()->admin()->create();
    $category = CateringCategory::factory()->create();
    CateringMember::factory()->count(3)->create(['catering_category_id' => $category->id]);

    Livewire::actingAs($admin)
        ->test(CateringCategoryIndex::class)
        ->call('confirmDelete', $category->id)
        ->call('deleteConfirmed');

    // Category should be deleted
    $this->assertDatabaseMissing('catering_categories', ['id' => $category->id]);
    // Related members should also be deleted (cascade)
    $this->assertDatabaseMissing('catering_members', ['catering_category_id' => $category->id]);
});

it('forbids wali kelas from creating a catering category via policy', function () {
    $waliKelas = User::factory()->waliKelas()->create();
    $category = new CateringCategory;

    expect(Gate::allows('create', $category))->toBeFalse();
    expect(Gate::denies('create', $category))->toBeTrue();
});

it('filters the catering category list by search term', function () {
    $admin = User::factory()->admin()->create();
    CateringCategory::factory()->create(['name' => 'Siswa Umum']);
    CateringCategory::factory()->create(['name' => 'Guru']);

    Livewire::actingAs($admin)
        ->test(CateringCategoryIndex::class)
        ->set('search', 'Guru')
        ->assertSee('Guru')
        ->assertDontSee('Siswa Umum');
});

it('filters the catering category list by status', function () {
    $admin = User::factory()->admin()->create();
    CateringCategory::factory()->create(['name' => 'Siswa Umum', 'is_active' => true]);
    CateringCategory::factory()->inactive()->create(['name' => 'Guru']);

    Livewire::actingAs($admin)
        ->test(CateringCategoryIndex::class)
        ->set('status', 'inactive')
        ->assertSee('Guru')
        ->assertDontSee('Siswa Umum');
});
