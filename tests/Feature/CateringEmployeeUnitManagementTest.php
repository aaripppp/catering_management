<?php

use App\Livewire\CateringEmployeeUnits\Index as CateringEmployeeUnitIndex;
use App\Livewire\CateringUnitAttendance\Index as CateringUnitAttendanceIndex;
use App\Livewire\CateringUnitBills\Index as CateringUnitBillsIndex;
use App\Models\CateringEmployeeUnit;
use App\Models\CateringUnitAttendance;
use App\Models\CateringUnitBill;
use App\Models\CateringUnitPayment;
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

it('allows administrators and forbids wali kelas from unit management operations', function () {
    $waliKelas = User::factory()->waliKelas()->create();
    $unit = CateringEmployeeUnit::factory()->create();

    $this->actingAs($waliKelas)
        ->get(route('catering-employee-units.index'))
        ->assertForbidden();

    $this->actingAs($waliKelas);
    expect(Gate::denies('create', CateringEmployeeUnit::class))->toBeTrue()
        ->and(Gate::denies('update', $unit))->toBeTrue()
        ->and(Gate::denies('delete', $unit))->toBeTrue();

    Livewire::actingAs($waliKelas)
        ->test(CateringEmployeeUnitIndex::class)
        ->assertForbidden();

    $this->actingAs(User::factory()->admin()->create());
    expect(Gate::allows('create', CateringEmployeeUnit::class))->toBeTrue()
        ->and(Gate::allows('update', $unit))->toBeTrue()
        ->and(Gate::allows('delete', $unit))->toBeTrue();
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

it('allows an admin to create a unit and renders it by sort order', function () {
    CateringEmployeeUnit::factory()->create(['name' => 'Unit Terakhir', 'sort_order' => 20]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringEmployeeUnitIndex::class)
        ->call('create')
        ->assertSet('showFormModal', true)
        ->set('name', 'Perpustakaan')
        ->set('price_per_person', '15000')
        ->set('is_active', true)
        ->set('sort_order', '10')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showFormModal', false)
        ->assertSeeInOrder(['Perpustakaan', 'Unit Terakhir'])
        ->assertDispatched('toast', type: 'success', message: 'Unit catering berhasil ditambahkan.');

    $this->assertDatabaseHas('catering_employee_units', [
        'name' => 'Perpustakaan',
        'price_per_person' => 15000,
        'is_active' => true,
        'sort_order' => 10,
    ]);
});

it('rejects a duplicate unit name during creation', function () {
    CateringEmployeeUnit::factory()->create(['name' => 'Perpustakaan']);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringEmployeeUnitIndex::class)
        ->call('create')
        ->set('name', 'Perpustakaan')
        ->set('price_per_person', '15000')
        ->set('sort_order', '10')
        ->call('save')
        ->assertHasErrors(['name' => 'unique']);

    expect(CateringEmployeeUnit::query()->where('name', 'Perpustakaan')->count())->toBe(1);
});

it('allows an admin to edit every unit field and updates list order', function () {
    $unit = CateringEmployeeUnit::factory()->create([
        'name' => 'SD',
        'price_per_person' => 14000,
        'is_active' => true,
        'sort_order' => 20,
    ]);
    CateringEmployeeUnit::factory()->create(['name' => 'Unit Kedua', 'sort_order' => 10]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringEmployeeUnitIndex::class)
        ->call('edit', $unit->id)
        ->assertSet('name', 'SD')
        ->assertSet('price_per_person', '14000')
        ->set('name', 'SD Utama')
        ->set('price_per_person', '15000')
        ->set('is_active', false)
        ->set('sort_order', '5')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSeeInOrder(['SD Utama', 'Unit Kedua'])
        ->assertDispatched('toast', type: 'success', message: 'Unit catering berhasil diperbarui.');

    $this->assertDatabaseHas('catering_employee_units', [
        'id' => $unit->id,
        'name' => 'SD Utama',
        'price_per_person' => 15000,
        'is_active' => false,
        'sort_order' => 5,
    ]);
});

it('persists a new price when editing an existing unit', function () {
    $unit = CateringEmployeeUnit::factory()->create(['price_per_person' => 13000]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringEmployeeUnitIndex::class)
        ->call('edit', $unit->id)
        ->assertSet('price_per_person', '13000')
        ->set('price_per_person', '14000')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('catering_employee_units', [
        'id' => $unit->id,
        'price_per_person' => 14000,
    ]);
});

it('leaves other unit fields unchanged when only the price is edited', function () {
    $unit = CateringEmployeeUnit::factory()->create([
        'name' => 'SD',
        'price_per_person' => 13000,
        'default_employee_subsidy_rate' => 4000,
        'default_student_subsidy_rate' => 1000,
        'is_active' => false,
        'sort_order' => 7,
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringEmployeeUnitIndex::class)
        ->call('edit', $unit->id)
        ->assertSet('default_employee_subsidy_rate', '4000')
        ->assertSet('default_student_subsidy_rate', '1000')
        ->assertSet('sort_order', '7')
        ->assertSet('is_active', false)
        ->set('price_per_person', '14000')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('catering_employee_units', [
        'id' => $unit->id,
        'name' => 'SD',
        'price_per_person' => 14000,
        'default_employee_subsidy_rate' => 4000,
        'default_student_subsidy_rate' => 1000,
        'is_active' => false,
        'sort_order' => 7,
    ]);
});

it('persists the price and both subsidy rates together', function () {
    $unit = CateringEmployeeUnit::factory()->create([
        'price_per_person' => 13000,
        'default_employee_subsidy_rate' => 4000,
        'default_student_subsidy_rate' => 1000,
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringEmployeeUnitIndex::class)
        ->call('edit', $unit->id)
        ->set('price_per_person', '16000')
        ->set('default_employee_subsidy_rate', '5000')
        ->set('default_student_subsidy_rate', '2000')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('catering_employee_units', [
        'id' => $unit->id,
        'price_per_person' => 16000,
        'default_employee_subsidy_rate' => 5000,
        'default_student_subsidy_rate' => 2000,
    ]);
});

it('rejects an empty price and keeps the stored price', function () {
    $unit = CateringEmployeeUnit::factory()->create(['price_per_person' => 13000]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringEmployeeUnitIndex::class)
        ->call('edit', $unit->id)
        ->set('price_per_person', '')
        ->call('save')
        ->assertHasErrors(['price_per_person' => 'required']);

    $this->assertDatabaseHas('catering_employee_units', [
        'id' => $unit->id,
        'price_per_person' => 13000,
    ]);
});

it('loads the updated price when the edit modal is reopened', function () {
    $unit = CateringEmployeeUnit::factory()->create(['price_per_person' => 13000]);

    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringEmployeeUnitIndex::class)
        ->call('edit', $unit->id)
        ->set('price_per_person', '14000')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showFormModal', false);

    $component->call('edit', $unit->id)
        ->assertSet('showFormModal', true)
        ->assertSet('price_per_person', '14000');
});

it('keeps the modal fields and actions inside the same form and panel', function () {
    $unit = CateringEmployeeUnit::factory()->create(['price_per_person' => 13000]);

    $html = Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringEmployeeUnitIndex::class)
        ->call('edit', $unit->id)
        ->html();

    $document = new DOMDocument;
    $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);

    $priceInput = $xpath->query('//input[@id="unit-catering-price"]')->item(0);
    $saveButton = $xpath->query('//button[@type="submit"]')->item(0);
    $cancelButton = $xpath->query('//button[normalize-space(.)="Batal"]')->item(0);

    expect($priceInput)->not->toBeNull()
        ->and($saveButton)->not->toBeNull()
        ->and($cancelButton)->not->toBeNull();

    $priceForm = $xpath->query('ancestor::form[1]', $priceInput)->item(0);
    $pricePanel = $xpath->query('ancestor::div[contains(@class, "max-w-lg")][1]', $priceInput)->item(0);

    expect($priceForm)->not->toBeNull()
        ->and($xpath->query('ancestor::form[1]', $saveButton)->item(0))->toBe($priceForm)
        ->and($xpath->query('ancestor::form[1]', $cancelButton)->item(0))->toBe($priceForm)
        ->and($xpath->query('ancestor::div[contains(@class, "max-w-lg")][1]', $saveButton)->item(0))->toBe($pricePanel);
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
        ->set('sort_order', '1')
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

it('deletes an unused unit after confirmation', function () {
    $unit = CateringEmployeeUnit::factory()->create(['name' => 'Perpustakaan']);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringEmployeeUnitIndex::class)
        ->call('confirmDelete', $unit->id)
        ->assertSet('showDeleteModal', true)
        ->assertSet('deletingName', 'Perpustakaan')
        ->assertSet('deleteBlocked', false)
        ->call('deleteConfirmed')
        ->assertSet('showDeleteModal', false)
        ->assertDispatched('toast', type: 'danger', message: 'Unit catering berhasil dihapus.');

    $this->assertModelMissing($unit);
});

it('blocks deleting a unit with attendance and preserves the attendance', function () {
    $unit = CateringEmployeeUnit::factory()->create(['name' => 'Perpustakaan']);
    $attendance = CateringUnitAttendance::factory()->for($unit)->create();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringEmployeeUnitIndex::class)
        ->call('confirmDelete', $unit->id)
        ->assertSet('deleteBlocked', false)
        ->assertSee('Data Unit dan seluruh riwayat terkait akan dihapus permanen')
        ->set('deleteConfirmChecked', true)
        ->set('deleteConfirmName', 'Perpustakaan')
        ->call('deleteConfirmed')
        ->assertSet('showDeleteModal', false);

    $this->assertModelMissing($unit);
    $this->assertModelMissing($attendance);
});

it('blocks deleting a unit with a bill and preserves the bill', function () {
    $unit = CateringEmployeeUnit::factory()->create(['name' => 'Perpustakaan']);
    $bill = CateringUnitBill::factory()->for($unit, 'unit')->create();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringEmployeeUnitIndex::class)
        ->call('confirmDelete', $unit->id)
        ->assertSet('deleteBlocked', false)
        ->set('deleteConfirmChecked', true)
        ->set('deleteConfirmName', 'Perpustakaan')
        ->call('deleteConfirmed')
        ->assertSet('showDeleteModal', false);

    $this->assertModelMissing($unit);
    $this->assertModelMissing($bill);
});

it('blocks deleting a unit with payment history and preserves all financial rows', function () {
    $unit = CateringEmployeeUnit::factory()->create(['name' => 'Perpustakaan']);
    $bill = CateringUnitBill::factory()->for($unit, 'unit')->create();
    $payment = CateringUnitPayment::factory()->for($bill)->create();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringEmployeeUnitIndex::class)
        ->call('confirmDelete', $unit->id)
        ->assertSet('deleteBlocked', false)
        ->set('deleteConfirmChecked', true)
        ->set('deleteConfirmName', 'Perpustakaan')
        ->call('deleteConfirmed')
        ->assertSet('showDeleteModal', false);

    $this->assertModelMissing($unit);
    $this->assertModelMissing($bill);
    $this->assertModelMissing($payment);
});

it('uses the shared ordered scope for a custom unit on master attendance and billing pages', function () {
    CateringEmployeeUnit::factory()->create(['name' => 'Daycare', 'sort_order' => 1]);
    CateringEmployeeUnit::factory()->create(['name' => 'TK', 'sort_order' => 3]);
    $admin = User::factory()->admin()->create();
    $expectedOrder = ['Daycare', 'Perpustakaan', 'TK'];

    Livewire::actingAs($admin)
        ->test(CateringEmployeeUnitIndex::class)
        ->call('create')
        ->set('name', 'Perpustakaan')
        ->set('price_per_person', '15000')
        ->set('sort_order', '2')
        ->call('save')
        ->assertSeeInOrder($expectedOrder);

    Livewire::actingAs($admin)
        ->test(CateringUnitAttendanceIndex::class)
        ->assertSet('units', fn (array $units): bool => array_column($units, 'name') === $expectedOrder)
        ->assertSeeInOrder($expectedOrder);

    Livewire::actingAs($admin)
        ->test(CateringUnitBillsIndex::class)
        ->assertViewHas('rows', fn ($rows): bool => $rows->pluck('unit.name')->all() === $expectedOrder)
        ->assertSeeInOrder($expectedOrder);
});

it('shows a create action in the empty state', function () {
    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringEmployeeUnitIndex::class)
        ->assertSee('Belum ada unit catering.')
        ->assertSee('Tambah Unit')
        ->assertDontSee('Jalankan seeder');
});

it('formats a unit price as Indonesian rupiah', function () {
    $unit = CateringEmployeeUnit::factory()->create(['price_per_person' => 13000]);

    expect($unit->formatted_price_per_person)->toBe('Rp 13.000');
});
