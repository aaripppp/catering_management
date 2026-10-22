<?php

use App\Livewire\CateringUnitBills\Index as CateringUnitBillsIndex;
use App\Models\CateringEmployeeUnit;
use App\Models\CateringUnitAttendance;
use App\Models\CateringUnitBill;
use App\Models\CateringUnitPayment;
use App\Models\User;
use App\Services\CateringUnitBillingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function unitPaymentPageSyncedBill(
    int $grossAmount = 1_000_000,
    int $month = 10,
    int $year = 2026,
    int $price = 1000,
): CateringUnitBill {
    $unit = CateringEmployeeUnit::factory()->create();

    CateringUnitAttendance::factory()->create([
        'catering_employee_unit_id' => $unit->id,
        'attendance_date' => sprintf('%04d-%02d-05', $year, $month),
        'quantity' => intdiv($grossAmount, $price),
        'price_per_person_snapshot' => $price,
    ]);

    $bill = app(CateringUnitBillingService::class)->syncUnitPeriod($unit->id, $month, $year);

    if ($bill === null) {
        throw new RuntimeException('Failed to synchronize the unit bill for the test.');
    }

    return $bill;
}

function unitPaymentPagePayment(
    CateringUnitBill $bill,
    int $amount,
    string $paidAt = '2026-10-10',
    ?string $method = null,
    ?string $note = null,
): CateringUnitPayment {
    return CateringUnitPayment::factory()->create([
        'catering_unit_bill_id' => $bill->id,
        'amount' => $amount,
        'paid_at' => CarbonImmutable::parse($paidAt),
        'payment_method' => $method,
        'note' => $note,
    ]);
}

function openUnitPaymentPage(User $admin, int $month = 10, int $year = 2026): Testable
{
    return Livewire::actingAs($admin)
        ->test(CateringUnitBillsIndex::class)
        ->set('month', $month)
        ->set('year', $year);
}

it('shows an unpaid unit bill with zero payment values and an available Bayar action', function () {
    $bill = unitPaymentPageSyncedBill(1_000_000);

    openUnitPaymentPage(User::factory()->admin()->create())
        ->assertSeeInOrder(['Tagihan', 'Terbayar', 'Lebih Bayar', 'Sisa', 'Status', 'Aksi'])
        ->assertSee('Rp 1.000.000')
        ->assertSee('Rp 0')
        ->assertSee('Belum Bayar')
        ->assertSeeHtml('wire:click="openPayment('.$bill->id.')"')
        ->assertSeeHtml('wire:click="openHistory('.$bill->id.')"');
});

it('shows partial payment values', function () {
    $bill = unitPaymentPageSyncedBill(1_000_000);
    unitPaymentPagePayment($bill, 400_000);

    openUnitPaymentPage(User::factory()->admin()->create())
        ->assertSee('Rp 400.000')
        ->assertSee('Rp 600.000')
        ->assertSee('Sebagian');
});

it('shows a fully paid unit bill', function () {
    $bill = unitPaymentPageSyncedBill(1_000_000);
    unitPaymentPagePayment($bill, 1_000_000);

    openUnitPaymentPage(User::factory()->admin()->create())
        ->assertSee('Lunas')
        ->assertSee('Rp 0');
});

it('shows an overpaid unit bill without creating a credit', function () {
    $bill = unitPaymentPageSyncedBill(1_000_000);
    unitPaymentPagePayment($bill, 1_100_000);

    openUnitPaymentPage(User::factory()->admin()->create())
        ->assertSee('Lebih Bayar')
        ->assertSee('Rp 100.000')
        ->assertSee('Rp 0');

    expect(DB::table('catering_credits')->count())->toBe(0);
});

it('records a payment from the modal and refreshes the table, cards and history', function () {
    $bill = unitPaymentPageSyncedBill(1_000_000);

    $component = openUnitPaymentPage(User::factory()->admin()->create())
        ->call('openPayment', $bill->id)
        ->assertSet('showPaymentModal', true)
        ->assertSet('paymentAmount', '1000000')
        ->set('paymentAmount', '240000')
        ->set('paymentDate', '2026-10-20')
        ->set('paymentMethod', 'Transfer')
        ->set('paymentNote', 'DP')
        ->call('savePayment')
        ->assertHasNoErrors()
        ->assertSet('showPaymentModal', false)
        ->assertDispatched('toast', type: 'success', message: 'Pembayaran unit berhasil dicatat.')
        ->assertSee('Rp 240.000')
        ->assertSee('Rp 760.000')
        ->assertSee('Sebagian');

    $this->assertDatabaseHas('catering_unit_payments', [
        'catering_unit_bill_id' => $bill->id,
        'amount' => 240000,
        'payment_method' => 'Transfer',
        'note' => 'DP',
    ]);

    $component
        ->call('openHistory', $bill->id)
        ->assertSet('showHistoryModal', true)
        ->assertSee('Transfer')
        ->assertSee('DP')
        ->assertSee('Rp 240.000');
});

it('uses min 1 and step 1 on the unit payment nominal input', function () {
    $bill = unitPaymentPageSyncedBill(1_000_000);

    openUnitPaymentPage(User::factory()->admin()->create())
        ->call('openPayment', $bill->id)
        ->assertSeeHtml('id="unit-payment-amount"')
        ->assertSeeHtml('min="1"')
        ->assertSeeHtml('step="1"')
        ->assertDontSeeHtml('step="1000"')
        ->assertSeeHtml('wire:model="paymentAmount"');
});

it('renders and closes the payment modal when a no-data unit follows the selected bill', function () {
    $bill = unitPaymentPageSyncedBill(91_000);
    CateringEmployeeUnit::factory()->create(['name' => 'Unit Tanpa Data']);

    openUnitPaymentPage(User::factory()->admin()->create())
        ->assertSeeHtml('wire:click="openPayment('.$bill->id.')"')
        ->call('openPayment', $bill->id)
        ->assertSet('showPaymentModal', true)
        ->assertSet('paymentBillId', $bill->id)
        ->assertSet('paymentAmount', '91000')
        ->assertSeeHtml('id="unit-payment-modal-title"')
        ->assertSee('Tanggal Bayar')
        ->assertSee('Nominal (Rp)')
        ->assertSeeHtml('id="unit-payment-amount"')
        ->call('closePaymentModal')
        ->assertSet('showPaymentModal', false)
        ->assertSet('paymentBillId', null)
        ->assertDontSeeHtml('id="unit-payment-modal-title"');
});

it('lists multiple payments and sums them in the table', function () {
    $bill = unitPaymentPageSyncedBill(1_000_000);
    unitPaymentPagePayment($bill, 111_000, '2026-10-01');
    unitPaymentPagePayment($bill, 222_000, '2026-10-02');

    openUnitPaymentPage(User::factory()->admin()->create())
        ->assertSee('Rp 333.000')
        ->call('openHistory', $bill->id)
        ->assertSee('Rp 111.000')
        ->assertSee('Rp 222.000');
});

it('orders unit payment history by paid date then id descending', function () {
    $bill = unitPaymentPageSyncedBill(1_000_000);
    unitPaymentPagePayment($bill, 100_000, '2026-10-01 08:00:00');
    unitPaymentPagePayment($bill, 200_000, '2026-10-05 08:00:00');
    unitPaymentPagePayment($bill, 300_000, '2026-10-05 09:00:00');

    openUnitPaymentPage(User::factory()->admin()->create())
        ->call('openHistory', $bill->id)
        ->assertSeeInOrder(['Rp 300.000', 'Rp 200.000', 'Rp 100.000']);
});

it('edits a payment from the history modal and recalculates totals', function () {
    $bill = unitPaymentPageSyncedBill(1_000_000);
    $payment = unitPaymentPagePayment($bill, 400_000, '2026-10-10', 'Tunai', 'Awal');

    $component = openUnitPaymentPage(User::factory()->admin()->create())
        ->call('openHistory', $bill->id)
        ->call('editPayment', $payment->id)
        ->assertSet('showHistoryModal', false)
        ->assertSet('showPaymentModal', true)
        ->assertSet('paymentAmount', '400000')
        ->assertSet('paymentMethod', 'Tunai')
        ->set('paymentAmount', '500000')
        ->call('savePayment')
        ->assertHasNoErrors()
        ->assertSet('showPaymentModal', false)
        ->assertSee('Rp 500.000');

    $this->assertDatabaseHas('catering_unit_payments', [
        'id' => $payment->id,
        'amount' => 500000,
    ]);

    $component
        ->call('openHistory', $bill->id)
        ->assertSee('Rp 500.000');
});

it('deletes a payment from the history modal and recalculates totals', function () {
    $bill = unitPaymentPageSyncedBill(1_000_000);
    $payment = unitPaymentPagePayment($bill, 400_000);

    openUnitPaymentPage(User::factory()->admin()->create())
        ->call('openHistory', $bill->id)
        ->call('confirmDeletePayment', $payment->id)
        ->assertSet('showDeletePaymentModal', true)
        ->call('deletePayment')
        ->assertSet('showDeletePaymentModal', false)
        ->assertDispatched('toast', type: 'danger', message: 'Pembayaran unit berhasil dihapus.')
        ->assertSee('Belum ada riwayat pembayaran.')
        ->assertSee('Belum Bayar');

    $this->assertDatabaseMissing('catering_unit_payments', ['id' => $payment->id]);
});

it('keeps existing payments visible but blocks new payments while the bill is stale', function () {
    $unit = CateringEmployeeUnit::factory()->create();
    $attendance = CateringUnitAttendance::factory()->create([
        'catering_employee_unit_id' => $unit->id,
        'attendance_date' => '2026-10-05',
        'quantity' => 1000,
        'price_per_person_snapshot' => 1000,
    ]);
    $bill = app(CateringUnitBillingService::class)->syncUnitPeriod($unit->id, 10, 2026);
    unitPaymentPagePayment($bill, 400_000);

    $attendance->update(['quantity' => 1200]);

    openUnitPaymentPage(User::factory()->admin()->create())
        ->assertSee('Perlu Sinkronisasi')
        ->assertSee('Sinkronkan tagihan terlebih dahulu.')
        ->assertSee('Rp 400.000')
        ->assertDontSeeHtml('wire:click="openPayment('.$bill->id.')"')
        ->call('openHistory', $bill->id)
        ->assertSee('Rp 400.000');
});

it('treats a synchronized zero bill as paid and disables the Bayar action', function () {
    $bill = unitPaymentPageSyncedBill(0);

    openUnitPaymentPage(User::factory()->admin()->create())
        ->assertSee('Lunas')
        ->assertSee('Rp 0')
        ->assertDontSeeHtml('wire:click="openPayment('.$bill->id.')"')
        ->assertSeeHtml('wire:click="openHistory('.$bill->id.')"');
});

it('builds period summary cards without netting one unit overpayment against another', function () {
    $overpaid = unitPaymentPageSyncedBill(1000);
    $underpaid = unitPaymentPageSyncedBill(1000);
    unitPaymentPagePayment($overpaid, 1200);
    unitPaymentPagePayment($underpaid, 500);

    openUnitPaymentPage(User::factory()->admin()->create())
        ->assertSeeInOrder([
            'Total Tagihan', 'Rp 2.000',
            'Total Terbayar', 'Rp 1.700',
            'Total Tunggakan', 'Rp 500',
            'Total Lebih Bayar', 'Rp 200',
        ])
        ->assertDontSee('Rp 300');
});

it('restricts unit payment ui actions to administrators', function () {
    $bill = unitPaymentPageSyncedBill(1_000_000);
    $waliKelas = User::factory()->waliKelas()->create();

    Livewire::actingAs($waliKelas)
        ->test(CateringUnitBillsIndex::class)
        ->assertForbidden();

    expect(Gate::forUser($waliKelas)->denies('create', CateringUnitPayment::class))->toBeTrue()
        ->and(Gate::forUser($waliKelas)->denies('update', CateringUnitPayment::class))->toBeTrue()
        ->and(Gate::forUser($waliKelas)->denies('delete', CateringUnitPayment::class))->toBeTrue()
        ->and(Gate::forUser(User::factory()->admin()->create())->allows('create', CateringUnitPayment::class))->toBeTrue();

    openUnitPaymentPage(User::factory()->admin()->create())
        ->call('openPayment', $bill->id)
        ->assertSet('showPaymentModal', true);
});

it('loads payment summaries for many bills without a query per bill', function () {
    $bills = collect(range(1, 20))->map(fn (): CateringUnitBill => unitPaymentPageSyncedBill(1_000_000));

    foreach ($bills as $bill) {
        unitPaymentPagePayment($bill, 250_000);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $component = openUnitPaymentPage(User::factory()->admin()->create());
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    $component
        ->assertSee('Rp 20.000.000')
        ->assertSee('Rp 5.000.000')
        ->assertSee('Rp 15.000.000')
        ->assertSee('Sebagian');

    expect($queryCount)->toBeLessThanOrEqual(20);
});
