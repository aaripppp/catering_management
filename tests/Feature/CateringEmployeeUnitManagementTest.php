<?php

use App\Livewire\CateringEmployeeUnits\Index as CateringEmployeeUnitIndex;
use App\Models\CateringEmployeeUnit;
use App\Models\User;
use Database\Seeders\CateringEmployeeUnitSeeder;
use Livewire\Livewire;

it('seeds exactly the nine units in stable order without resetting edits', function () {
    $this->seed(CateringEmployeeUnitSeeder::class);

    expect(CateringEmployeeUnit::query()->ordered()->pluck('name')->all())->toBe([
        'Daycare',
        'TK',
        'SD',
        'SMP',
        'SMA',
        'TU',
        'Annur Mart',
        'Yayasan',
        'SDM',
    ])->and(CateringEmployeeUnit::query()->ordered()->pluck('sort_order', 'name')->all())->toBe([
        'Daycare' => 1,
        'TK' => 2,
        'SD' => 3,
        'SMP' => 4,
        'SMA' => 5,
        'TU' => 6,
        'Annur Mart' => 7,
        'Yayasan' => 8,
        'SDM' => 9,
    ]);

    expect(CateringEmployeeUnit::query()->count())->toBe(9)
        ->and(CateringEmployeeUnit::query()->where('name', 'SD')->value('price_per_person'))->toBe(14000)
        ->and(CateringEmployeeUnit::query()->where('name', '!=', 'SD')->where('price_per_person', '!=', 13000)->exists())->toBeFalse();

    CateringEmployeeUnit::query()->where('name', 'SD')->update(['price_per_person' => 15000]);
    $this->seed(CateringEmployeeUnitSeeder::class);

    expect(CateringEmployeeUnit::query()->count())->toBe(9)
        ->and(CateringEmployeeUnit::query()->where('name', 'SD')->value('price_per_person'))->toBe(15000)
        ->and(CateringEmployeeUnit::query()->ordered()->pluck('name')->all())->toBe([
            'Daycare',
            'TK',
            'SD',
            'SMP',
            'SMA',
            'TU',
            'Annur Mart',
            'Yayasan',
            'SDM',
        ]);
});

it('redirects guests away from the unit catering page', function () {
    $this->get(route('catering-employee-units.index'))->assertRedirect(route('login'));
});

it('forbids wali kelas from the unit catering page and update operation', function () {
    $waliKelas = User::factory()->waliKelas()->create();
    $unit = CateringEmployeeUnit::factory()->create();

    $this->actingAs($waliKelas)
        ->get(route('catering-employee-units.index'))
        ->assertForbidden();

    $this->actingAs($waliKelas);
    expect(Gate::denies('update', $unit))->toBeTrue();

    Livewire::actingAs($waliKelas)
        ->test(CateringEmployeeUnitIndex::class)
        ->assertForbidden();
});

it('allows an admin to view all units with formatted prices', function () {
    $this->seed(CateringEmployeeUnitSeeder::class);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('catering-employee-units.index'))
        ->assertOk()
        ->assertSee('Unit Catering')
        ->assertSeeInOrder(['Daycare', 'TK', 'SD', 'SMP', 'SMA', 'TU', 'Annur Mart', 'Yayasan', 'SDM'])
        ->assertSee('Rp 13.000')
        ->assertSee('Rp 14.000');
});

it('allows an admin to edit a unit name and price', function () {
    $unit = CateringEmployeeUnit::factory()->create([
        'name' => 'SD',
        'price_per_person' => 14000,
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringEmployeeUnitIndex::class)
        ->call('edit', $unit->id)
        ->assertSet('name', 'SD')
        ->assertSet('price_per_person', '14000')
        ->set('name', 'SD Utama')
        ->set('price_per_person', '15000')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast', type: 'success', message: 'Unit catering berhasil diperbarui.');

    $this->assertDatabaseHas('catering_employee_units', [
        'id' => $unit->id,
        'name' => 'SD Utama',
        'price_per_person' => 15000,
    ]);
});

it('allows an admin to deactivate and reactivate a unit', function () {
    $unit = CateringEmployeeUnit::factory()->create(['is_active' => true]);
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(CateringEmployeeUnitIndex::class)
        ->call('edit', $unit->id)
        ->set('is_active', false)
        ->call('save')
        ->assertHasNoErrors();

    expect($unit->fresh()->is_active)->toBeFalse();

    Livewire::actingAs($admin)
        ->test(CateringEmployeeUnitIndex::class)
        ->call('edit', $unit->id)
        ->set('is_active', true)
        ->call('save')
        ->assertHasNoErrors();

    expect($unit->fresh()->is_active)->toBeTrue();
});

it('rejects invalid prices and duplicate names', function (mixed $price, string $rule) {
    $existing = CateringEmployeeUnit::factory()->create(['name' => 'Daycare']);
    $unit = CateringEmployeeUnit::factory()->create(['name' => 'SD']);

    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringEmployeeUnitIndex::class)
        ->call('edit', $unit->id)
        ->set('price_per_person', $price)
        ->call('save')
        ->assertHasErrors(['price_per_person' => $rule]);

    $component
        ->set('price_per_person', '14000')
        ->set('name', $existing->name)
        ->call('save')
        ->assertHasErrors(['name' => 'unique']);

    $this->assertDatabaseHas('catering_employee_units', [
        'id' => $unit->id,
        'name' => 'SD',
    ]);
})->with([
    'negative price' => ['-1', 'min'],
    'non integer price' => ['13000.5', 'integer'],
]);

it('formats a unit price as Indonesian rupiah', function () {
    $unit = CateringEmployeeUnit::factory()->create(['price_per_person' => 13000]);

    expect($unit->formatted_price_per_person)->toBe('Rp 13.000');
});
