<?php

use App\Enums\CateringUnitBillPaymentStatus;
use App\Models\CateringCredit;
use App\Models\CateringEmployeeUnit;
use App\Models\CateringUnitAttendance;
use App\Models\CateringUnitBill;
use App\Models\CateringUnitPayment;
use App\Models\User;
use App\Services\CateringUnitBillingService;
use App\Services\CateringUnitPaymentService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

function unitPaymentBilling(): CateringUnitBillingService
{
    return app(CateringUnitBillingService::class);
}

function unitPaymentService(): CateringUnitPaymentService
{
    return app(CateringUnitPaymentService::class);
}

function unitPaymentSyncedBill(
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

    $bill = unitPaymentBilling()->syncUnitPeriod($unit->id, $month, $year);

    if ($bill === null) {
        throw new RuntimeException('Failed to synchronize the unit bill for the test.');
    }

    return $bill;
}

function unitPaymentRecord(
    CateringUnitBill $bill,
    int $amount,
    ?CarbonImmutable $paidAt = null,
    ?User $actor = null,
    ?string $note = null,
): CateringUnitPayment {
    return unitPaymentService()->create($bill, $amount, $actor, $note, $paidAt);
}

function unitPaymentSummary(CateringUnitBill $bill): array
{
    return unitPaymentService()->summaryForBill($bill->refresh());
}

it('reports an unpaid bill', function () {
    $bill = unitPaymentSyncedBill(1_000_000);

    $summary = unitPaymentSummary($bill);

    expect($summary['billed_amount'])->toBe(1_000_000)
        ->and($summary['paid_amount'])->toBe(0)
        ->and($summary['remaining_amount'])->toBe(1_000_000)
        ->and($summary['overpaid_amount'])->toBe(0)
        ->and($summary['status'])->toBe(CateringUnitBillPaymentStatus::Unpaid)
        ->and($summary['status_label'])->toBe('Belum Bayar');
});

it('reports a partially paid bill', function () {
    $bill = unitPaymentSyncedBill(1_000_000);
    unitPaymentRecord($bill, 400_000);

    $summary = unitPaymentSummary($bill);

    expect($summary['paid_amount'])->toBe(400_000)
        ->and($summary['remaining_amount'])->toBe(600_000)
        ->and($summary['overpaid_amount'])->toBe(0)
        ->and($summary['status'])->toBe(CateringUnitBillPaymentStatus::Partial)
        ->and($summary['status_label'])->toBe('Sebagian');
});

it('reports a fully paid bill across multiple payments', function () {
    $bill = unitPaymentSyncedBill(1_000_000);
    unitPaymentRecord($bill, 400_000);
    unitPaymentRecord($bill, 600_000);

    $summary = unitPaymentSummary($bill);

    expect($summary['paid_amount'])->toBe(1_000_000)
        ->and($summary['remaining_amount'])->toBe(0)
        ->and($summary['overpaid_amount'])->toBe(0)
        ->and($summary['status'])->toBe(CateringUnitBillPaymentStatus::Paid)
        ->and($summary['status_label'])->toBe('Lunas');
});

it('reports an overpaid bill without creating a unit credit', function () {
    $bill = unitPaymentSyncedBill(1_000_000);
    unitPaymentRecord($bill, 1_100_000);

    $summary = unitPaymentSummary($bill);

    expect($summary['paid_amount'])->toBe(1_100_000)
        ->and($summary['remaining_amount'])->toBe(0)
        ->and($summary['overpaid_amount'])->toBe(100_000)
        ->and($summary['status'])->toBe(CateringUnitBillPaymentStatus::Overpaid)
        ->and($summary['status_label'])->toBe('Lebih Bayar')
        ->and(CateringCredit::count())->toBe(0)
        ->and(CateringUnitPayment::count())->toBe(1);
});

it('allows multiple payments on the same bill', function () {
    $bill = unitPaymentSyncedBill(5_880_000);
    unitPaymentRecord($bill, 3_000_000);
    unitPaymentRecord($bill, 2_880_000);

    $summary = unitPaymentSummary($bill);

    expect(CateringUnitPayment::query()->where('catering_unit_bill_id', $bill->id)->count())->toBe(2)
        ->and($summary['paid_amount'])->toBe(5_880_000)
        ->and($summary['remaining_amount'])->toBe(0)
        ->and($summary['status'])->toBe(CateringUnitBillPaymentStatus::Paid);
});

it('preserves payments and recalculates remaining when the bill is resynced upward', function () {
    $unit = CateringEmployeeUnit::factory()->create();
    $attendance = CateringUnitAttendance::factory()->create([
        'catering_employee_unit_id' => $unit->id,
        'attendance_date' => '2026-10-05',
        'quantity' => 1000,
        'price_per_person_snapshot' => 1000,
    ]);

    $bill = unitPaymentBilling()->syncUnitPeriod($unit->id, 10, 2026);
    unitPaymentRecord($bill, 1_000_000);

    $attendance->update(['quantity' => 1200]);
    unitPaymentBilling()->syncUnitPeriod($unit->id, 10, 2026);

    $summary = unitPaymentSummary($bill);

    expect(CateringUnitPayment::count())->toBe(1)
        ->and($summary['billed_amount'])->toBe(1_200_000)
        ->and($summary['paid_amount'])->toBe(1_000_000)
        ->and($summary['remaining_amount'])->toBe(200_000)
        ->and($summary['status'])->toBe(CateringUnitBillPaymentStatus::Partial);
});

it('preserves payments and reports overpayment when the bill is resynced downward', function () {
    $unit = CateringEmployeeUnit::factory()->create();
    $attendance = CateringUnitAttendance::factory()->create([
        'catering_employee_unit_id' => $unit->id,
        'attendance_date' => '2026-10-05',
        'quantity' => 1000,
        'price_per_person_snapshot' => 1000,
    ]);

    $bill = unitPaymentBilling()->syncUnitPeriod($unit->id, 10, 2026);
    unitPaymentRecord($bill, 1_000_000);

    $attendance->update(['quantity' => 900]);
    unitPaymentBilling()->syncUnitPeriod($unit->id, 10, 2026);

    $summary = unitPaymentSummary($bill);

    expect(CateringUnitPayment::count())->toBe(1)
        ->and($summary['billed_amount'])->toBe(900_000)
        ->and($summary['paid_amount'])->toBe(1_000_000)
        ->and($summary['remaining_amount'])->toBe(0)
        ->and($summary['overpaid_amount'])->toBe(100_000)
        ->and($summary['status'])->toBe(CateringUnitBillPaymentStatus::Overpaid);
});

it('blocks new payments while the bill is stale but keeps existing history readable', function () {
    $unit = CateringEmployeeUnit::factory()->create();
    CateringUnitAttendance::factory()->create([
        'catering_employee_unit_id' => $unit->id,
        'attendance_date' => '2026-10-05',
        'quantity' => 1000,
        'price_per_person_snapshot' => 1000,
    ]);

    $bill = unitPaymentBilling()->syncUnitPeriod($unit->id, 10, 2026);
    $payment = unitPaymentRecord($bill, 1_000_000);

    CateringUnitAttendance::factory()->create([
        'catering_employee_unit_id' => $unit->id,
        'attendance_date' => '2026-10-06',
        'quantity' => 10,
        'price_per_person_snapshot' => 1000,
    ]);

    expect(fn () => unitPaymentRecord($bill, 50_000))
        ->toThrow(ValidationException::class);

    expect(CateringUnitPayment::count())->toBe(1)
        ->and(unitPaymentService()->historyForBill($bill)->pluck('id')->all())->toBe([$payment->id]);
});

it('treats a synchronized zero bill as paid and blocks a positive payment', function () {
    $bill = unitPaymentSyncedBill(0);

    $summary = unitPaymentSummary($bill);

    expect($summary['billed_amount'])->toBe(0)
        ->and($summary['paid_amount'])->toBe(0)
        ->and($summary['remaining_amount'])->toBe(0)
        ->and($summary['overpaid_amount'])->toBe(0)
        ->and($summary['status'])->toBe(CateringUnitBillPaymentStatus::Paid)
        ->and($summary['status_label'])->toBe('Lunas');

    expect(fn () => unitPaymentRecord($bill, 10_000))
        ->toThrow(ValidationException::class);

    expect(CateringUnitPayment::count())->toBe(0);
});

it('keeps period overpayment separate from another unit outstanding balance', function () {
    $billA = CateringUnitBill::factory()->create([
        'period_month' => 10,
        'period_year' => 2026,
        'gross_amount' => 1000,
    ]);
    $billB = CateringUnitBill::factory()->create([
        'period_month' => 10,
        'period_year' => 2026,
        'gross_amount' => 1000,
    ]);

    CateringUnitPayment::factory()->create([
        'catering_unit_bill_id' => $billA->id,
        'amount' => 1200,
    ]);
    CateringUnitPayment::factory()->create([
        'catering_unit_bill_id' => $billB->id,
        'amount' => 500,
    ]);

    $summary = unitPaymentService()->periodSummary(10, 2026);

    expect($summary['total_billed'])->toBe(2000)
        ->and($summary['total_paid'])->toBe(1700)
        ->and($summary['total_remaining'])->toBe(500)
        ->and($summary['total_overpaid'])->toBe(200);
});

it('aggregates exact unit payment statuses without treating overpayment as paid', function () {
    $paidBill = CateringUnitBill::factory()->create(['gross_amount' => 1000]);
    $partialBill = CateringUnitBill::factory()->create(['gross_amount' => 1000]);
    CateringUnitBill::factory()->create(['gross_amount' => 1000]);
    $overpaidBill = CateringUnitBill::factory()->create(['gross_amount' => 1000]);
    CateringUnitPayment::factory()->create([
        'catering_unit_bill_id' => $paidBill->id,
        'amount' => 1000,
    ]);
    CateringUnitPayment::factory()->create([
        'catering_unit_bill_id' => $partialBill->id,
        'amount' => 400,
    ]);
    CateringUnitPayment::factory()->create([
        'catering_unit_bill_id' => $overpaidBill->id,
        'amount' => 1200,
    ]);

    $summary = unitPaymentService()->periodSummary(10, 2026);

    expect($summary)->toBe([
        'total_billed' => 4000,
        'total_paid' => 2600,
        'total_remaining' => 1600,
        'total_overpaid' => 200,
        'paid_count' => 1,
        'partial_count' => 1,
        'unpaid_count' => 1,
    ]);
});

it('counts a zero unit bill as paid in period status totals', function () {
    CateringUnitBill::factory()->create(['gross_amount' => 0]);

    $summary = unitPaymentService()->periodSummary(10, 2026);

    expect($summary['paid_count'])->toBe(1)
        ->and($summary['partial_count'])->toBe(0)
        ->and($summary['unpaid_count'])->toBe(0);
});

it('orders payment history by paid date then id descending', function () {
    $bill = unitPaymentSyncedBill(1_000_000);
    $first = unitPaymentRecord($bill, 100_000, CarbonImmutable::parse('2026-10-01 08:00:00'));
    $second = unitPaymentRecord($bill, 100_000, CarbonImmutable::parse('2026-10-05 08:00:00'));
    $third = unitPaymentRecord($bill, 100_000, CarbonImmutable::parse('2026-10-05 09:00:00'));

    expect(unitPaymentService()->historyForBill($bill)->pluck('id')->all())
        ->toBe([$third->id, $second->id, $first->id]);
});

it('updates and deletes existing unit payments', function () {
    $bill = unitPaymentSyncedBill(1_000_000);
    $payment = unitPaymentRecord($bill, 400_000);

    unitPaymentService()->update($payment, 600_000, CarbonImmutable::parse('2026-10-10'));
    $summary = unitPaymentSummary($bill);

    expect($summary['paid_amount'])->toBe(600_000)
        ->and($summary['status'])->toBe(CateringUnitBillPaymentStatus::Partial);

    unitPaymentService()->delete($payment);
    $summary = unitPaymentSummary($bill);

    expect(CateringUnitPayment::count())->toBe(0)
        ->and($summary['paid_amount'])->toBe(0)
        ->and($summary['status'])->toBe(CateringUnitBillPaymentStatus::Unpaid);
});

it('rejects non-positive payment amounts', function (int $amount) {
    $bill = unitPaymentSyncedBill(1_000_000);

    expect(fn () => unitPaymentRecord($bill, $amount))
        ->toThrow(ValidationException::class);

    expect(CateringUnitPayment::count())->toBe(0);
})->with([0, -1000]);

it('restricts unit payments to administrators', function () {
    $admin = User::factory()->admin()->create();
    $waliKelas = User::factory()->waliKelas()->create();

    expect(Gate::forUser($admin)->allows('viewAny', CateringUnitPayment::class))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('create', CateringUnitPayment::class))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('update', CateringUnitPayment::class))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('delete', CateringUnitPayment::class))->toBeTrue()
        ->and(Gate::forUser($waliKelas)->denies('viewAny', CateringUnitPayment::class))->toBeTrue()
        ->and(Gate::forUser($waliKelas)->denies('create', CateringUnitPayment::class))->toBeTrue()
        ->and(Gate::forUser($waliKelas)->denies('update', CateringUnitPayment::class))->toBeTrue()
        ->and(Gate::forUser($waliKelas)->denies('delete', CateringUnitPayment::class))->toBeTrue();
});

it('loads the period summary without a query per bill', function () {
    $bills = CateringUnitBill::factory()->count(25)->create([
        'period_month' => 10,
        'period_year' => 2026,
        'gross_amount' => 100_000,
    ]);

    foreach ($bills as $bill) {
        CateringUnitPayment::factory()->create([
            'catering_unit_bill_id' => $bill->id,
            'amount' => 40_000,
        ]);
    }

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $summary = unitPaymentService()->periodSummary(10, 2026);

    expect($summary['total_billed'])->toBe(2_500_000)
        ->and($summary['total_paid'])->toBe(1_000_000)
        ->and($summary['total_remaining'])->toBe(1_500_000)
        ->and($queries)->toBeLessThan(5);
});
