<?php

use App\Enums\CateringUnitBillSyncStatus;
use App\Livewire\CateringUnitBills\Index as CateringUnitBillsIndex;
use App\Models\CateringEmployeeUnit;
use App\Models\CateringUnitAttendance;
use App\Models\CateringUnitBill;
use App\Models\User;
use App\Services\CateringUnitBillingService;
use App\Services\CateringUnitInvoiceService;
use Database\Seeders\CateringEmployeeUnitSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function createUnitBillingPageAttendance(
    CateringEmployeeUnit $unit,
    string $date,
    int $quantity,
    int $price,
): CateringUnitAttendance {
    return CateringUnitAttendance::factory()->create([
        'catering_employee_unit_id' => $unit->id,
        'attendance_date' => $date,
        'quantity' => $quantity,
        'price_per_person_snapshot' => $price,
    ]);
}

function openUnitBillingPage(User $admin, int $month = 10, int $year = 2026): Testable
{
    return Livewire::actingAs($admin)
        ->test(CateringUnitBillsIndex::class)
        ->set('month', $month)
        ->set('year', $year);
}

/**
 * The combined invoice URL contains a query string whose "&" Blade escapes to
 * "&amp;", so assert against the escaped form the DOM actually contains.
 */
function combinedInvoiceUrl(int $month = 10, int $year = 2026): string
{
    return e(route('catering-unit-bills.invoice.all', ['month' => $month, 'year' => $year]));
}

it('limits the unit billing page and synchronization to administrators', function () {
    $this->get(route('catering-unit-bills.index'))->assertRedirect(route('login'));
    $waliKelas = User::factory()->waliKelas()->create();

    $this->actingAs($waliKelas)
        ->get(route('catering-unit-bills.index'))
        ->assertForbidden();

    Livewire::actingAs($waliKelas)
        ->test(CateringUnitBillsIndex::class)
        ->assertForbidden();

    $this->actingAs($waliKelas);
    expect(Gate::denies('sync', CateringUnitBill::class))->toBeTrue();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('catering-unit-bills.index'))
        ->assertOk()
        ->assertSee('Tagihan Unit');
});

it('renders an empty financial summary and active units without creating bills', function () {
    CateringEmployeeUnit::factory()->create(['name' => 'Daycare']);

    openUnitBillingPage(User::factory()->admin()->create())
        ->assertSee('Jumlah Unit')
        ->assertSee('Total Qty')
        ->assertSee('Total Tagihan')
        ->assertSee('Daycare')
        ->assertSee('Belum Ada Data')
        ->assertSee('Rp 0');

    expect(CateringUnitBill::query()->exists())->toBeFalse();
});

it('renders synced stale no-data and zero bills in canonical unit order', function () {
    $this->seed(CateringEmployeeUnitSeeder::class);
    $units = CateringEmployeeUnit::query()->get()->keyBy('name');
    $service = app(CateringUnitBillingService::class);
    $daycareAttendance = createUnitBillingPageAttendance($units['Daycare'], '2026-10-01', 1, 13000);
    $tkAttendance = createUnitBillingPageAttendance($units['TK'], '2026-10-01', 2, 13000);
    createUnitBillingPageAttendance($units['SMP'], '2026-10-01', 0, 13000);
    $service->syncUnitPeriod($units['Daycare']->id, 10, 2026);
    $service->syncUnitPeriod($units['TK']->id, 10, 2026);
    $service->syncUnitPeriod($units['SMP']->id, 10, 2026);
    $tkAttendance->update(['quantity' => 3]);
    $expectedOrder = ['Daycare', 'TK', 'SD', 'SMP', 'SMA', 'TU', 'Annur Mart', 'Yayasan', 'SDM'];

    openUnitBillingPage(User::factory()->admin()->create())
        ->assertSeeInOrder($expectedOrder)
        ->assertSee('Sinkron')
        ->assertSee('Perlu Sinkronisasi')
        ->assertSee('Belum Ada Data')
        ->assertSee('Rp 0');

    expect($daycareAttendance->fresh()->quantity)->toBe(1)
        ->and($service->periodStatus(10, 2026)->pluck('unit.name')->all())->toBe($expectedOrder);
});

it('shows unsynchronized source and synchronizes it without a page reload', function () {
    $unit = CateringEmployeeUnit::factory()->create(['name' => 'TU']);
    createUnitBillingPageAttendance($unit, '2026-10-01', 10, 13000);
    $admin = User::factory()->admin()->create();

    openUnitBillingPage($admin)
        ->assertSee('Belum Disinkronkan')
        ->assertSee('Sumber absensi tersedia')
        ->call('sync')
        ->assertHasNoErrors()
        ->assertSee('Sinkron')
        ->assertSee('Rp 130.000')
        ->assertDispatched('toast', type: 'success', message: '1 tagihan unit berhasil disinkronkan.');

    $this->assertDatabaseHas('catering_unit_bills', [
        'catering_employee_unit_id' => $unit->id,
        'generated_by' => $admin->id,
        'total_quantity' => 10,
        'gross_amount' => 130000,
    ]);
});

it('keeps stale bill values visible until explicit synchronization', function () {
    $unit = CateringEmployeeUnit::factory()->create();
    $attendance = createUnitBillingPageAttendance($unit, '2026-10-01', 10, 13000);
    $service = app(CateringUnitBillingService::class);
    $service->syncPeriod(10, 2026);
    $attendance->update(['quantity' => 8]);

    openUnitBillingPage(User::factory()->admin()->create())
        ->assertSee('Perlu Sinkronisasi')
        ->assertSee('Rp 130.000')
        ->assertDontSee('Rp 104.000')
        ->call('sync')
        ->assertSee('Sinkron')
        ->assertSee('Rp 104.000')
        ->assertDontSee('Perlu Sinkronisasi');
});

it('retains and zeroes a stale bill after all source rows are deleted', function () {
    $unit = CateringEmployeeUnit::factory()->create(['name' => 'Yayasan']);
    $attendance = createUnitBillingPageAttendance($unit, '2026-10-01', 10, 13000);
    $bill = app(CateringUnitBillingService::class)->syncPeriod(10, 2026)->first();
    $attendance->delete();

    openUnitBillingPage(User::factory()->admin()->create())
        ->assertSee('Perlu Sinkronisasi')
        ->assertSee('Rp 130.000')
        ->call('sync')
        ->assertSee('Sinkron')
        ->assertSee('Rp 0');

    expect($bill->fresh())
        ->total_quantity->toBe(0)
        ->gross_amount->toBe(0);
    expect($bill->fresh()->days)->toHaveCount(0);
});

it('displays a synchronized confirmed-zero bill as zero rather than missing data', function () {
    $unit = CateringEmployeeUnit::factory()->create(['name' => 'SDM']);
    createUnitBillingPageAttendance($unit, '2026-10-01', 0, 13000);
    app(CateringUnitBillingService::class)->syncPeriod(10, 2026);

    openUnitBillingPage(User::factory()->admin()->create())
        ->assertSee('SDM')
        ->assertSee('Rp 0')
        ->assertSee('Sinkron')
        ->assertDontSee('Belum Ada Data');
});

it('shows mixed rates safely and calculates their synchronized gross amount', function () {
    $unit = CateringEmployeeUnit::factory()->create(['name' => 'SD']);
    createUnitBillingPageAttendance($unit, '2026-10-01', 10, 13000);
    createUnitBillingPageAttendance($unit, '2026-10-02', 10, 14000);
    app(CateringUnitBillingService::class)->syncPeriod(10, 2026);

    openUnitBillingPage(User::factory()->admin()->create())
        ->assertSee('Tarif Campuran')
        ->assertSee('Rp 270.000')
        ->assertDontSee('Rp 150.000');
});

it('displays the historical attendance rate after the unit master price changes', function () {
    $unit = CateringEmployeeUnit::factory()->create([
        'name' => 'SMP',
        'price_per_person' => 13000,
    ]);
    createUnitBillingPageAttendance($unit, '2026-10-01', 10, 13000);
    $unit->update(['price_per_person' => 15000]);
    app(CateringUnitBillingService::class)->syncPeriod(10, 2026);

    openUnitBillingPage(User::factory()->admin()->create())
        ->assertSee('Rp 13.000')
        ->assertSee('Rp 130.000')
        ->assertDontSee('Rp 15.000');
});

it('keeps inactive units visible when the selected period has historical billing', function () {
    $unit = CateringEmployeeUnit::factory()->create(['name' => 'Unit Lama']);
    createUnitBillingPageAttendance($unit, '2026-10-01', 10, 13000);
    app(CateringUnitBillingService::class)->syncPeriod(10, 2026);
    $unit->update(['is_active' => false]);

    openUnitBillingPage(User::factory()->admin()->create())
        ->assertSee('Unit Lama')
        ->assertSee('Rp 130.000')
        ->assertSee('Sinkron');
});

it('builds summary cards only from synchronized bill snapshots', function () {
    $billedUnit = CateringEmployeeUnit::factory()->create(['name' => 'Sudah Ditagih']);
    $unsyncedUnit = CateringEmployeeUnit::factory()->create(['name' => 'Belum Ditagih']);
    createUnitBillingPageAttendance($billedUnit, '2026-10-01', 10, 13000);
    app(CateringUnitBillingService::class)->syncUnitPeriod($billedUnit->id, 10, 2026);
    createUnitBillingPageAttendance($unsyncedUnit, '2026-10-01', 99, 13000);

    openUnitBillingPage(User::factory()->admin()->create())
        ->assertSeeInOrder(['Jumlah Unit', '1', 'Total Qty', '10', 'Total Tagihan', 'Rp 130.000'])
        ->assertSee('Belum Disinkronkan')
        ->assertDontSee('Rp 1.287.000');
});

it('synchronizes unsynced stale and existing bills for the selected period only', function () {
    $unsyncedUnit = CateringEmployeeUnit::factory()->create();
    $staleUnit = CateringEmployeeUnit::factory()->create();
    $syncedUnit = CateringEmployeeUnit::factory()->create();
    $noDataUnit = CateringEmployeeUnit::factory()->create();
    createUnitBillingPageAttendance($unsyncedUnit, '2026-10-01', 5, 13000);
    $staleAttendance = createUnitBillingPageAttendance($staleUnit, '2026-10-01', 10, 13000);
    createUnitBillingPageAttendance($syncedUnit, '2026-10-01', 7, 13000);
    $service = app(CateringUnitBillingService::class);
    $service->syncUnitPeriod($staleUnit->id, 10, 2026);
    $service->syncUnitPeriod($syncedUnit->id, 10, 2026);
    $staleAttendance->update(['quantity' => 8]);

    openUnitBillingPage(User::factory()->admin()->create())
        ->assertSee('Belum Disinkronkan')
        ->assertSee('Perlu Sinkronisasi')
        ->call('sync')
        ->assertDontSee('Belum Disinkronkan')
        ->assertDontSee('Perlu Sinkronisasi')
        ->assertSee('Belum Ada Data');

    expect(CateringUnitBill::query()->count())->toBe(3)
        ->and($service->periodStatus(10, 2026)
            ->reject(fn (array $row): bool => $row['unit']->is($noDataUnit))
            ->every(fn (array $row): bool => $row['status'] === CateringUnitBillSyncStatus::Synced))->toBeTrue();
});

it('renders unit billing financial columns with bounded queries', function () {
    $units = CateringEmployeeUnit::factory()->count(20)->create();

    foreach ($units as $unit) {
        createUnitBillingPageAttendance($unit, '2026-10-01', 5, 13000);
    }

    app(CateringUnitBillingService::class)->syncPeriod(10, 2026);
    $admin = User::factory()->admin()->create();
    DB::flushQueryLog();
    DB::enableQueryLog();
    $component = openUnitBillingPage($admin);
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    $component
        ->assertSee('Total Terbayar')
        ->assertSee('Total Tunggakan')
        ->assertSee('Total Lebih Bayar')
        ->assertSee('Belum Bayar')
        ->assertSee('Bayar')
        ->assertDontSee('Kredit')
        ->assertDontSee('Outstanding');

    expect($queryCount)->toBeLessThanOrEqual(20);
});

it('shows a per-unit invoice action only for a synchronized row and points it to the bill route', function () {
    $unit = CateringEmployeeUnit::factory()->create(['name' => 'Daycare']);
    createUnitBillingPageAttendance($unit, '2026-10-01', 10, 13000);
    $bill = app(CateringUnitBillingService::class)->syncUnitPeriod($unit->id, 10, 2026);

    openUnitBillingPage(User::factory()->admin()->create())
        ->assertSee('Invoice', false)
        ->assertSee(route('catering-unit-bills.invoice', $bill), false)
        ->assertSee('Buka invoice Daycare di tab baru', false);
});

it('does not expose a usable invoice action for stale, unsynced or no-data rows', function () {
    $staleUnit = CateringEmployeeUnit::factory()->create(['name' => 'Daycare']);
    $staleAttendance = createUnitBillingPageAttendance($staleUnit, '2026-10-01', 10, 13000);
    $staleBill = app(CateringUnitBillingService::class)->syncUnitPeriod($staleUnit->id, 10, 2026);
    $staleAttendance->update(['quantity' => 8]);

    $unsyncedUnit = CateringEmployeeUnit::factory()->create(['name' => 'TU']);
    createUnitBillingPageAttendance($unsyncedUnit, '2026-10-01', 5, 13000);

    CateringEmployeeUnit::factory()->create(['name' => 'SD']);

    openUnitBillingPage(User::factory()->admin()->create())
        ->assertSee('Perlu sinkronisasi')
        ->assertSee('Belum disinkronkan')
        ->assertDontSee(route('catering-unit-bills.invoice', $staleBill), false)
        ->assertDontSee('Buka invoice Daycare di tab baru', false)
        ->assertDontSee('Buka invoice TU di tab baru', false)
        ->assertDontSee('Buka invoice SD di tab baru', false);
});

it('offers the combined invoice action for an invoice-ready period with the selected month and year', function () {
    $unit = CateringEmployeeUnit::factory()->create(['name' => 'Daycare']);
    createUnitBillingPageAttendance($unit, '2026-11-01', 10, 13000);
    app(CateringUnitBillingService::class)->syncUnitPeriod($unit->id, 11, 2026);
    $combinedUrl = combinedInvoiceUrl(11, 2026);

    openUnitBillingPage(User::factory()->admin()->create(), month: 11, year: 2026)
        ->assertSee('Invoice Semua Unit')
        ->assertSee($combinedUrl, false)
        ->assertSee('Buka invoice semua unit periode November 2026 di tab baru', false)
        ->assertDontSee('Sinkronkan Tagihan Unit terlebih dahulu untuk mengaktifkan invoice semua unit.');
});

it('blocks the combined invoice action while a relevant unit is unsynced or stale', function () {
    $daycare = CateringEmployeeUnit::factory()->create(['name' => 'Daycare']);
    $sd = CateringEmployeeUnit::factory()->create(['name' => 'SD']);
    createUnitBillingPageAttendance($daycare, '2026-10-01', 10, 13000);
    $sdAttendance = createUnitBillingPageAttendance($sd, '2026-10-01', 10, 13000);
    $combinedUrl = combinedInvoiceUrl();
    $admin = User::factory()->admin()->create();
    $service = app(CateringUnitBillingService::class);

    $service->syncUnitPeriod($daycare->id, 10, 2026);

    openUnitBillingPage($admin)
        ->assertSee('Belum Disinkronkan')
        ->assertDontSee($combinedUrl, false)
        ->assertSee('Sinkronkan Tagihan Unit terlebih dahulu untuk mengaktifkan invoice semua unit.');

    $service->syncUnitPeriod($sd->id, 10, 2026);
    $sdAttendance->update(['quantity' => 6]);

    openUnitBillingPage($admin)
        ->assertSee('Perlu Sinkronisasi')
        ->assertDontSee($combinedUrl, false)
        ->assertSee('Sinkronkan Tagihan Unit terlebih dahulu untuk mengaktifkan invoice semua unit.');

    openUnitBillingPage($admin)
        ->call('sync')
        ->assertSee('Sinkron')
        ->assertSee($combinedUrl, false)
        ->assertDontSee('Sinkronkan Tagihan Unit terlebih dahulu untuk mengaktifkan invoice semua unit.');

    expect(app(CateringUnitInvoiceService::class)->buildCombinedInvoice(10, 2026))->toMatchArray([
        'rowCount' => 2,
        'grandTotalQuantity' => 16,
        'grandTotalAmount' => 208000,
    ]);
});

it('keeps the combined invoice action available with only no-data units and with a synchronized zero bill', function () {
    CateringEmployeeUnit::factory()->create(['name' => 'TU']);
    $zero = CateringEmployeeUnit::factory()->create(['name' => 'Daycare']);
    createUnitBillingPageAttendance($zero, '2026-10-01', 0, 13000);
    app(CateringUnitBillingService::class)->syncUnitPeriod($zero->id, 10, 2026);
    $combinedUrl = combinedInvoiceUrl();

    openUnitBillingPage(User::factory()->admin()->create())
        ->assertSee('Belum Ada Data')
        ->assertSee('Rp 0')
        ->assertSee('Sinkron')
        ->assertSee($combinedUrl, false)
        ->assertDontSee('Sinkronkan Tagihan Unit terlebih dahulu untuk mengaktifkan invoice semua unit.');

    expect(app(CateringUnitInvoiceService::class)->buildCombinedInvoice(10, 2026))->toMatchArray([
        'rowCount' => 1,
        'grandTotalQuantity' => 0,
        'grandTotalAmount' => 0,
    ]);
});

it('keeps every unit invoice surface administrator-only', function () {
    $unit = CateringEmployeeUnit::factory()->create();
    createUnitBillingPageAttendance($unit, '2026-10-01', 10, 13000);
    $bill = app(CateringUnitBillingService::class)->syncUnitPeriod($unit->id, 10, 2026);

    $this->get(route('catering-unit-bills.index'))->assertRedirect(route('login'));
    $this->get(route('catering-unit-bills.invoice', $bill))->assertRedirect(route('login'));
    $this->get(route('catering-unit-bills.invoice.all', ['month' => 10, 'year' => 2026]))
        ->assertRedirect(route('login'));

    $waliKelas = User::factory()->waliKelas()->create();

    $this->actingAs($waliKelas)->get(route('catering-unit-bills.index'))->assertForbidden();
    $this->actingAs($waliKelas)->get(route('catering-unit-bills.invoice', $bill))->assertForbidden();
    $this->actingAs($waliKelas)
        ->get(route('catering-unit-bills.invoice.all', ['month' => 10, 'year' => 2026]))
        ->assertForbidden();
});
