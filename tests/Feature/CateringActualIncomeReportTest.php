<?php

use App\Enums\CateringParticipantGroup;
use App\Livewire\CateringMonthlyReport\Index;
use App\Models\CateringBill;
use App\Models\CateringCredit;
use App\Models\CateringCreditAllocation;
use App\Models\CateringCultureMealIncome;
use App\Models\CateringMember;
use App\Models\CateringPayment;
use App\Models\CateringUnitBill;
use App\Models\CateringUnitPayment;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\CateringMonthlyReportService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function actualIncomeBill(CateringParticipantGroup $participantGroup = CateringParticipantGroup::Student): CateringBill
{
    $member = CateringMember::factory()->create();

    return CateringBill::factory()->for($member)->create([
        'participant_group' => $participantGroup->value,
        'school_class_id' => $member->school_class_id,
        'catering_category_id' => $member->catering_category_id,
    ]);
}

function actualIncomePayment(CateringBill $bill, int $amount, string $paidAt): CateringPayment
{
    return CateringPayment::factory()->for($bill)->create([
        'catering_member_id' => $bill->catering_member_id,
        'amount' => $amount,
        'paid_at' => $paidAt,
    ]);
}

test('actual income defaults to the current calendar month and renders after student statistics', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-22 09:00:00', config('app.timezone')));
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(Index::class)
        ->assertSet('incomeFromDate', '2026-10-01')
        ->assertSet('incomeToDate', '2026-10-22')
        ->assertSeeInOrder(['Statistik Pembayaran Siswa', 'Pemasukan Aktual', 'Ringkasan'])
        ->assertSee('Pemasukan Siswa')
        ->assertSee('Pemasukan Unit')
        ->assertSee('Pemasukan Budaya Makan')
        ->assertSee('Total Pemasukan');
});

test('actual income aggregates the three cash sources and excludes employee bills and out of range payments', function () {
    $studentBill = actualIncomeBill();
    $employeeBill = actualIncomeBill(CateringParticipantGroup::Employee);
    $unitBill = CateringUnitBill::factory()->create();
    $schoolClass = SchoolClass::factory()->create();

    actualIncomePayment($studentBill, 100_000, '2026-10-05 10:00:00');
    actualIncomePayment($studentBill, 999_000, '2026-10-11 00:00:00');
    actualIncomePayment($employeeBill, 500_000, '2026-10-05 10:00:00');
    CateringUnitPayment::factory()->for($unitBill)->create(['amount' => 91_000, 'paid_at' => '2026-10-06 11:00:00']);
    CateringCultureMealIncome::factory()->for($schoolClass)->create(['amount' => 240_000, 'income_date' => '2026-10-07']);

    expect(app(CateringMonthlyReportService::class)->actualIncomeForDateRange('2026-10-01', '2026-10-10'))->toBe([
        'student' => 100_000,
        'unit' => 91_000,
        'culture' => 240_000,
        'total' => 431_000,
    ]);
});

test('actual income counts overpayments once without adding derived student credit allocations', function () {
    $sourceBill = actualIncomeBill();
    $targetBill = CateringBill::factory()->create([
        'catering_member_id' => $sourceBill->catering_member_id,
        'catering_category_id' => $sourceBill->catering_category_id,
        'school_class_id' => $sourceBill->school_class_id,
        'period_month' => 11,
    ]);
    $payment = actualIncomePayment($sourceBill, 150_000, '2026-10-05 10:00:00');
    $credit = CateringCredit::factory()->create([
        'catering_member_id' => $sourceBill->catering_member_id,
        'source_payment_id' => $payment->id,
        'source_bill_id' => $sourceBill->id,
        'original_amount' => 50_000,
        'remaining_amount' => 0,
    ]);
    CateringCreditAllocation::factory()
        ->for($credit, 'cateringCredit')
        ->for($targetBill, 'cateringBill')
        ->create(['amount' => 50_000]);

    $unitBill = CateringUnitBill::factory()->create(['gross_amount' => 100_000]);
    CateringUnitPayment::factory()->for($unitBill)->create(['amount' => 150_000, 'paid_at' => '2026-10-05 11:00:00']);

    expect(app(CateringMonthlyReportService::class)->actualIncomeForDateRange('2026-10-05', '2026-10-05'))->toBe([
        'student' => 150_000,
        'unit' => 150_000,
        'culture' => 0,
        'total' => 300_000,
    ]);
});

test('actual income includes both date boundaries across months and multiple payments on one day', function () {
    $bill = actualIncomeBill();

    actualIncomePayment($bill, 999_000, '2026-09-27 23:59:59');
    actualIncomePayment($bill, 10_000, '2026-09-28 00:00:00');
    actualIncomePayment($bill, 20_000, '2026-10-03 00:00:00');
    actualIncomePayment($bill, 30_000, '2026-10-03 23:59:59');
    actualIncomePayment($bill, 999_000, '2026-10-04 00:00:00');

    expect(app(CateringMonthlyReportService::class)->actualIncomeForDateRange('2026-09-28', '2026-10-03'))
        ->toMatchArray(['student' => 60_000, 'total' => 60_000]);
});

test('deleted transactions no longer contribute and an empty range returns zero totals', function () {
    $bill = actualIncomeBill();
    $deletedPayment = actualIncomePayment($bill, 100_000, '2026-10-05 10:00:00');
    actualIncomePayment($bill, 200_000, '2026-10-05 11:00:00');
    $deletedPayment->delete();

    $service = app(CateringMonthlyReportService::class);

    expect($service->actualIncomeForDateRange('2026-10-05', '2026-10-05'))->toBe([
        'student' => 200_000,
        'unit' => 0,
        'culture' => 0,
        'total' => 200_000,
    ])->and($service->actualIncomeForDateRange('2026-11-01', '2026-11-30'))->toBe([
        'student' => 0,
        'unit' => 0,
        'culture' => 0,
        'total' => 0,
    ]);
});

test('invalid actual income dates show field errors without failing the report render', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-22 09:00:00', config('app.timezone')));
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(Index::class)
        ->set('incomeFromDate', '2026-10-10')
        ->set('incomeToDate', '2026-10-01')
        ->assertHasErrors(['incomeToDate' => 'after_or_equal'])
        ->assertViewHas('actualIncome', ['student' => 0, 'unit' => 0, 'culture' => 0, 'total' => 0]);
});

test('actual income remains independent from monthly and participant filters', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-22 09:00:00', config('app.timezone')));
    $this->actingAs(User::factory()->admin()->create());
    $schoolClass = SchoolClass::factory()->create(['level' => '7']);
    $bill = actualIncomeBill();
    actualIncomePayment($bill, 125_000, '2026-10-10 09:00:00');
    $expected = ['student' => 125_000, 'unit' => 0, 'culture' => 0, 'total' => 125_000];

    Livewire::test(Index::class)
        ->assertViewHas('actualIncome', $expected)
        ->set('month', 9)
        ->set('year', 2025)
        ->set('participantGroup', CateringParticipantGroup::Student->value)
        ->set('jenjang', 'SMP')
        ->set('schoolClassId', (string) $schoolClass->id)
        ->assertViewHas('actualIncome', $expected);
});

test('actual income query count remains fixed as transaction volume grows', function () {
    $bill = actualIncomeBill();
    $unitBill = CateringUnitBill::factory()->create();
    $schoolClass = SchoolClass::factory()->create();

    CateringPayment::factory()->count(20)->for($bill)->create([
        'catering_member_id' => $bill->catering_member_id,
        'paid_at' => '2026-10-05 10:00:00',
    ]);
    CateringUnitPayment::factory()->count(20)->for($unitBill)->create(['paid_at' => '2026-10-05 10:00:00']);
    CateringCultureMealIncome::factory()->count(20)->for($schoolClass)->create(['income_date' => '2026-10-05']);

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    app(CateringMonthlyReportService::class)->actualIncomeForDateRange('2026-10-01', '2026-10-31');

    expect($queries)->toHaveCount(3);
});
