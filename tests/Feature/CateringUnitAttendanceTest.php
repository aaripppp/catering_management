<?php

use App\Livewire\CateringUnitAttendance\Index as CateringUnitAttendanceIndex;
use App\Models\CateringEmployeeUnit;
use App\Models\CateringUnitAttendance;
use App\Models\User;
use Database\Seeders\CateringEmployeeUnitSeeder;
use Livewire\Livewire;

it('limits the unit attendance page to administrators', function () {
    $this->get(route('catering-unit-attendance.index'))->assertRedirect(route('login'));

    $waliKelas = User::factory()->waliKelas()->create();

    $this->actingAs($waliKelas)
        ->get(route('catering-unit-attendance.index'))
        ->assertForbidden();

    Livewire::actingAs($waliKelas)
        ->test(CateringUnitAttendanceIndex::class)
        ->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('catering-unit-attendance.index'))
        ->assertOk()
        ->assertSee('Absensi Unit');
});

it('shows active units in canonical order and excludes inactive units', function () {
    $this->seed(CateringEmployeeUnitSeeder::class);
    CateringEmployeeUnit::factory()->inactive()->create(['name' => 'Unit Nonaktif', 'sort_order' => 0]);
    $expectedOrder = ['Daycare', 'TK', 'SD', 'SMP', 'SMA', 'TU', 'Annur Mart', 'Yayasan', 'SDM'];

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringUnitAttendanceIndex::class)
        ->assertSet('units', fn (array $units): bool => array_column($units, 'name') === $expectedOrder)
        ->assertSeeInOrder($expectedOrder)
        ->assertDontSee('Unit Nonaktif');
});

it('builds the correct number of date columns for the selected month', function () {
    CateringEmployeeUnit::factory()->create();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringUnitAttendanceIndex::class)
        ->call('changePeriod', 'year', 2028)
        ->call('changePeriod', 'month', 2)
        ->assertCount('dates', 29)
        ->assertSet('dates.28.date', '2028-02-29');
});

it('creates attendance including zero with the current unit price snapshot', function () {
    $unit = CateringEmployeeUnit::factory()->create(['price_per_person' => 13000]);
    $admin = User::factory()->admin()->create();
    $date = now()->startOfMonth()->toDateString();

    Livewire::actingAs($admin)
        ->test(CateringUnitAttendanceIndex::class)
        ->set("quantities.{$unit->id}.{$date}", '0')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('catering_unit_attendances', [
        'catering_employee_unit_id' => $unit->id,
        'attendance_date' => $date,
        'quantity' => 0,
        'price_per_person_snapshot' => 13000,
    ]);
});

it('preserves the price snapshot when quantity changes and deletes a row when cleared', function () {
    $unit = CateringEmployeeUnit::factory()->create(['price_per_person' => 15000]);
    $date = now()->startOfMonth()->toDateString();
    $attendance = CateringUnitAttendance::factory()->create([
        'catering_employee_unit_id' => $unit->id,
        'attendance_date' => $date,
        'quantity' => 4,
        'price_per_person_snapshot' => 13000,
    ]);
    $admin = User::factory()->admin()->create();

    $component = Livewire::actingAs($admin)
        ->test(CateringUnitAttendanceIndex::class)
        ->set("quantities.{$unit->id}.{$date}", '7')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast', type: 'success', message: '1 perubahan absensi unit berhasil disimpan.');

    expect($attendance->fresh())
        ->quantity->toBe(7)
        ->price_per_person_snapshot->toBe(13000);

    $component
        ->set("quantities.{$unit->id}.{$date}", '')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('catering_unit_attendances', ['id' => $attendance->id]);
});

it('validates only changed quantities', function (string $quantity, string $rule) {
    $unit = CateringEmployeeUnit::factory()->create();
    $date = now()->startOfMonth()->toDateString();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringUnitAttendanceIndex::class)
        ->set("quantities.{$unit->id}.{$date}", $quantity)
        ->call('save')
        ->assertHasErrors(["quantities.{$unit->id}.{$date}" => $rule]);

    expect(CateringUnitAttendance::query()->exists())->toBeFalse();
})->with([
    'negative quantity' => ['-1', 'min'],
    'decimal quantity' => ['1.5', 'integer'],
]);

it('refuses a server-side period change while matrix values are dirty', function () {
    $unit = CateringEmployeeUnit::factory()->create();
    $admin = User::factory()->admin()->create();
    $originalMonth = now()->month;
    $newMonth = $originalMonth === 12 ? 11 : $originalMonth + 1;
    $date = now()->startOfMonth()->toDateString();

    Livewire::actingAs($admin)
        ->test(CateringUnitAttendanceIndex::class)
        ->set("quantities.{$unit->id}.{$date}", '3')
        ->call('changePeriod', 'month', $newMonth)
        ->assertSet('month', $originalMonth)
        ->assertSet("quantities.{$unit->id}.{$date}", '3')
        ->assertDispatched('toast', type: 'danger', message: 'Simpan atau batalkan perubahan sebelum mengganti periode.');
});
