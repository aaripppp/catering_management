<?php

use App\Enums\CateringAttendanceStatus;
use App\Enums\CateringBillStatus;
use App\Enums\CateringParticipantGroup;
use App\Models\CateringAttendance;
use App\Models\CateringBill;
use App\Models\CateringCategory;
use App\Models\CateringCredit;
use App\Models\CateringCreditAllocation;
use App\Models\CateringMember;
use App\Models\CateringPayment;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\CateringBillingService;
use App\Services\CateringPaymentService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * @return array{member: CateringMember, class: SchoolClass, category: CateringCategory}
 */
function billingParticipant(int $pricePerDay = 20000, string $name = 'Ahmad'): array
{
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $category = CateringCategory::factory()->student()->create([
        'name' => 'Siswa Umum '.fake()->unique()->numerify('###'),
        'price_per_day' => $pricePerDay,
    ]);
    $member = CateringMember::factory()->create([
        'name' => $name,
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);

    return compact('member', 'schoolClass', 'category') + ['class' => $schoolClass];
}

function storeBillingAttendance(
    CateringMember $member,
    int $year,
    int $month,
    int $activeDays = 20,
    int $sickDays = 0,
): void {
    $start = CarbonImmutable::create($year, $month, 1);
    $rows = [];

    foreach (range(0, $activeDays + $sickDays - 1) as $offset) {
        $rows[] = [
            'catering_member_id' => $member->id,
            'attendance_date' => $start->addDays($offset)->toDateString(),
            'status' => $offset < $activeDays
                ? CateringAttendanceStatus::Ikut->value
                : CateringAttendanceStatus::Sakit->value,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    CateringAttendance::query()->insert($rows);
}

function generateBillingPeriod(
    SchoolClass $schoolClass,
    int $year = 2026,
    int $month = 10,
    ?User $actor = null,
): array {
    return app(CateringBillingService::class)->generate(
        $year,
        $month,
        CateringParticipantGroup::Student,
        $schoolClass,
        actor: $actor,
    );
}

it('generates an immutable monthly snapshot from attendance and category data', function () {
    ['member' => $member, 'class' => $schoolClass, 'category' => $category] = billingParticipant();
    storeBillingAttendance($member, 2026, 10, activeDays: 20, sickDays: 2);
    $admin = User::factory()->admin()->create();

    $result = generateBillingPeriod($schoolClass, actor: $admin);
    $bill = CateringBill::query()->sole();
    $categoryNameSnapshot = $bill->category_name;

    expect($result)->toBe(['created' => 1, 'regenerated' => 0, 'skipped' => 0, 'credit_applied' => 0])
        ->and($bill->catering_member_id)->toBe($member->id)
        ->and($bill->category_name)->toBe($category->name)
        ->and($bill->price_per_day)->toBe(20000)
        ->and($bill->saved_days)->toBe(22)
        ->and($bill->active_days)->toBe(20)
        ->and($bill->sakit_days)->toBe(2)
        ->and($bill->gross_amount)->toBe(400000)
        ->and($bill->payment_status)->toBe(CateringBillStatus::Unpaid)
        ->and($bill->generated_by)->toBe($admin->id);

    $category->update(['price_per_day' => 22000, 'name' => 'Harga Baru']);

    expect($bill->fresh()->price_per_day)->toBe(20000)
        ->and($bill->fresh()->category_name)->toBe($categoryNameSnapshot)
        ->and($bill->fresh()->gross_amount)->toBe(400000);
});

it('prevents duplicate member bills for one period at database level', function () {
    ['member' => $member, 'class' => $schoolClass] = billingParticipant();
    storeBillingAttendance($member, 2026, 10);
    generateBillingPeriod($schoolClass);

    expect(fn () => CateringBill::factory()->create([
        'catering_member_id' => $member->id,
        'period_month' => 10,
        'period_year' => 2026,
    ]))->toThrow(QueryException::class);
});

it('resyncs unpaid attendance totals while preserving the existing price snapshot', function () {
    ['member' => $member, 'class' => $schoolClass, 'category' => $category] = billingParticipant();
    storeBillingAttendance($member, 2026, 10, activeDays: 20);
    generateBillingPeriod($schoolClass);
    $bill = CateringBill::query()->sole();

    $category->update(['price_per_day' => 25000]);
    CateringAttendance::factory()->for($member)->create([
        'attendance_date' => '2026-10-21',
        'status' => CateringAttendanceStatus::Ikut,
    ]);
    $result = generateBillingPeriod($schoolClass);

    expect($result['regenerated'])->toBe(1)
        ->and($result['skipped'])->toBe(0)
        ->and($bill->fresh()->active_days)->toBe(21)
        ->and($bill->fresh()->price_per_day)->toBe(20000)
        ->and($bill->fresh()->gross_amount)->toBe(420000)
        ->and($bill->fresh()->payment_status)->toBe(CateringBillStatus::Unpaid);
});

it('allows partially paid bills to resync without changing payment transactions', function () {
    ['member' => $member, 'class' => $schoolClass] = billingParticipant();
    storeBillingAttendance($member, 2026, 10, activeDays: 20);
    generateBillingPeriod($schoolClass);
    $bill = CateringBill::query()->sole();
    $payment = app(CateringPaymentService::class)->record($bill, 150000);

    CateringAttendance::factory()->for($member)->create([
        'attendance_date' => '2026-10-21',
        'status' => CateringAttendanceStatus::Ikut,
    ]);
    $result = generateBillingPeriod($schoolClass);

    expect($result['regenerated'])->toBe(1)
        ->and($result['skipped'])->toBe(0)
        ->and($bill->fresh()->gross_amount)->toBe(420000)
        ->and($bill->fresh()->paidAmount())->toBe(150000)
        ->and($bill->fresh()->remainingAmount())->toBe(270000)
        ->and($bill->fresh()->payment_status)->toBe(CateringBillStatus::Partial)
        ->and($bill->fresh()->payments()->sole()->is($payment))->toBeTrue();
});

it('changes a fully paid bill to partial when resync increases its amount', function () {
    ['member' => $member, 'class' => $schoolClass] = billingParticipant();
    storeBillingAttendance($member, 2026, 10, activeDays: 20);
    generateBillingPeriod($schoolClass);
    $bill = CateringBill::query()->sole();
    app(CateringPaymentService::class)->record($bill, 400000);

    CateringAttendance::factory()->for($member)->create([
        'attendance_date' => '2026-10-21',
        'status' => CateringAttendanceStatus::Ikut,
    ]);
    generateBillingPeriod($schoolClass);

    expect($bill->fresh()->active_days)->toBe(21)
        ->and($bill->fresh()->gross_amount)->toBe(420000)
        ->and($bill->fresh()->paidAmount())->toBe(400000)
        ->and($bill->fresh()->remainingAmount())->toBe(20000)
        ->and($bill->fresh()->payment_status)->toBe(CateringBillStatus::Partial)
        ->and($bill->fresh()->payments()->count())->toBe(1);
});

it('creates recalculated credit when resync decreases a fully paid bill', function () {
    ['member' => $member, 'class' => $schoolClass] = billingParticipant();
    storeBillingAttendance($member, 2026, 10, activeDays: 20);
    generateBillingPeriod($schoolClass);
    $bill = CateringBill::query()->sole();
    app(CateringPaymentService::class)->record($bill, 400000);

    CateringAttendance::query()
        ->where('catering_member_id', $member->id)
        ->where('attendance_date', '2026-10-20')
        ->update(['status' => CateringAttendanceStatus::Sakit]);
    generateBillingPeriod($schoolClass);

    expect($bill->fresh()->active_days)->toBe(19)
        ->and($bill->fresh()->gross_amount)->toBe(380000)
        ->and($bill->fresh()->payment_status)->toBe(CateringBillStatus::Paid)
        ->and($bill->fresh()->remainingAmount())->toBe(0)
        ->and($bill->fresh()->generatedCreditAmount())->toBe(20000)
        ->and(CateringCredit::query()->sole()->original_amount)->toBe(20000)
        ->and(CateringCredit::query()->sole()->remaining_amount)->toBe(20000);
});

it('derives unpaid partial and paid statuses from transaction history', function () {
    ['member' => $member, 'class' => $schoolClass] = billingParticipant();
    storeBillingAttendance($member, 2026, 10);
    generateBillingPeriod($schoolClass);
    $bill = CateringBill::query()->sole();

    expect($bill->payment_status)->toBe(CateringBillStatus::Unpaid)
        ->and($bill->paidAmount())->toBe(0)
        ->and($bill->remainingAmount())->toBe(400000);

    app(CateringPaymentService::class)->record($bill, 150000);

    expect($bill->fresh()->payment_status)->toBe(CateringBillStatus::Partial)
        ->and($bill->fresh()->paidAmount())->toBe(150000)
        ->and($bill->fresh()->remainingAmount())->toBe(250000);

    app(CateringPaymentService::class)->record($bill, 250000);

    expect($bill->fresh()->payment_status)->toBe(CateringBillStatus::Paid)
        ->and($bill->fresh()->payments()->pluck('amount')->all())->toBe([150000, 250000])
        ->and($bill->fresh()->paidAmount())->toBe(400000)
        ->and($bill->fresh()->remainingAmount())->toBe(0)
        ->and(CateringCredit::query()->count())->toBe(0);
});

it('turns overpayment into source linked credit without changing gross amount', function () {
    ['member' => $member, 'class' => $schoolClass] = billingParticipant();
    storeBillingAttendance($member, 2026, 10);
    generateBillingPeriod($schoolClass);
    $bill = CateringBill::query()->sole();

    $payment = app(CateringPaymentService::class)->record($bill, 500000);
    $credit = CateringCredit::query()->sole();

    expect($payment->amount)->toBe(500000)
        ->and($bill->fresh()->payment_status)->toBe(CateringBillStatus::Paid)
        ->and($bill->fresh()->gross_amount)->toBe(400000)
        ->and($credit->source_payment_id)->toBe($payment->id)
        ->and($credit->source_bill_id)->toBe($bill->id)
        ->and($credit->original_amount)->toBe(100000)
        ->and($credit->remaining_amount)->toBe(100000);
});

it('automatically applies available credit to the next generated month', function () {
    ['member' => $member, 'class' => $schoolClass] = billingParticipant();
    storeBillingAttendance($member, 2026, 10);
    generateBillingPeriod($schoolClass, month: 10);
    app(CateringPaymentService::class)->record(CateringBill::query()->sole(), 500000);

    storeBillingAttendance($member, 2026, 11);
    generateBillingPeriod($schoolClass, month: 11);
    $november = CateringBill::query()->where('period_month', 11)->sole();

    expect($november->gross_amount)->toBe(400000)
        ->and($november->creditAppliedAmount())->toBe(100000)
        ->and($november->remainingAmount())->toBe(300000)
        ->and($november->payment_status)->toBe(CateringBillStatus::Partial)
        ->and(CateringCredit::query()->sole()->remaining_amount)->toBe(0)
        ->and(CateringCreditAllocation::query()->sole()->amount)->toBe(100000);
});

it('carries a large credit safely across multiple future periods', function () {
    ['member' => $member, 'class' => $schoolClass] = billingParticipant();
    storeBillingAttendance($member, 2026, 10);
    generateBillingPeriod($schoolClass, month: 10);
    app(CateringPaymentService::class)->record(CateringBill::query()->sole(), 1300000);

    foreach ([[2026, 11], [2026, 12], [2027, 1]] as [$year, $month]) {
        storeBillingAttendance($member, $year, $month);
        generateBillingPeriod($schoolClass, year: $year, month: $month);
    }

    $november = CateringBill::query()->where('period_year', 2026)->where('period_month', 11)->sole();
    $december = CateringBill::query()->where('period_year', 2026)->where('period_month', 12)->sole();
    $january = CateringBill::query()->where('period_year', 2027)->where('period_month', 1)->sole();

    expect($november->creditAppliedAmount())->toBe(400000)
        ->and($november->payment_status)->toBe(CateringBillStatus::Paid)
        ->and($december->creditAppliedAmount())->toBe(400000)
        ->and($december->payment_status)->toBe(CateringBillStatus::Paid)
        ->and($january->creditAppliedAmount())->toBe(100000)
        ->and($january->remainingAmount())->toBe(300000)
        ->and($january->payment_status)->toBe(CateringBillStatus::Partial)
        ->and(CateringCredit::query()->sole()->remaining_amount)->toBe(0)
        ->and(CateringCreditAllocation::query()->sum('amount'))->toBe(900000)
        ->and(CateringCreditAllocation::query()->count())->toBe(3);
});

it('resyncs bills with allocated credit and preserves their price snapshot', function () {
    ['member' => $member, 'class' => $schoolClass, 'category' => $category] = billingParticipant();
    storeBillingAttendance($member, 2026, 10);
    generateBillingPeriod($schoolClass, month: 10);
    app(CateringPaymentService::class)->record(CateringBill::query()->sole(), 500000);
    storeBillingAttendance($member, 2026, 11);
    generateBillingPeriod($schoolClass, month: 11);
    $november = CateringBill::query()->where('period_month', 11)->sole();

    $category->update(['price_per_day' => 30000]);
    CateringAttendance::factory()->for($member)->create([
        'attendance_date' => '2026-11-21',
        'status' => CateringAttendanceStatus::Ikut,
    ]);
    $result = generateBillingPeriod($schoolClass, month: 11);

    expect($result['regenerated'])->toBe(1)
        ->and($result['skipped'])->toBe(0)
        ->and($november->fresh()->price_per_day)->toBe(20000)
        ->and($november->fresh()->gross_amount)->toBe(420000)
        ->and($november->fresh()->creditAppliedAmount())->toBe(100000)
        ->and($november->fresh()->remainingAmount())->toBe(320000);
});

it('creates missing participant bills on resync without duplicating existing bills', function () {
    ['member' => $firstMember, 'class' => $schoolClass, 'category' => $category] = billingParticipant();
    storeBillingAttendance($firstMember, 2026, 10);
    generateBillingPeriod($schoolClass);
    $existingBillId = CateringBill::query()->sole()->id;
    $secondMember = CateringMember::factory()->create([
        'name' => 'Peserta Baru',
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);
    storeBillingAttendance($secondMember, 2026, 10, activeDays: 5);

    $result = generateBillingPeriod($schoolClass);

    expect($result['created'])->toBe(1)
        ->and($result['regenerated'])->toBe(1)
        ->and(CateringBill::query()->count())->toBe(2)
        ->and(CateringBill::query()->whereKey($existingBillId)->count())->toBe(1)
        ->and(CateringBill::query()->where('catering_member_id', $secondMember->id)->sole()->gross_amount)->toBe(100000);
});

it('edits a payment and rebuilds source credit and downstream allocations', function () {
    ['member' => $member, 'class' => $schoolClass] = billingParticipant();
    storeBillingAttendance($member, 2026, 10);
    generateBillingPeriod($schoolClass, month: 10);
    $october = CateringBill::query()->sole();
    $payment = app(CateringPaymentService::class)->record($october, 500000, note: 'Awal');
    storeBillingAttendance($member, 2026, 11);
    generateBillingPeriod($schoolClass, month: 11);
    $november = CateringBill::query()->where('period_month', 11)->sole();
    $unrelatedPayment = CateringPayment::factory()->create(['amount' => 123000]);

    $updated = app(CateringPaymentService::class)->update(
        $payment,
        450000,
        CarbonImmutable::parse('2026-10-08 10:00:00'),
        'Dikoreksi',
    );

    expect($updated->amount)->toBe(450000)
        ->and($updated->paid_at->format('Y-m-d'))->toBe('2026-10-08')
        ->and($updated->note)->toBe('Dikoreksi')
        ->and($october->fresh()->paidAmount())->toBe(450000)
        ->and($october->fresh()->generatedCreditAmount())->toBe(50000)
        ->and($october->fresh()->payment_status)->toBe(CateringBillStatus::Paid)
        ->and($november->fresh()->creditAppliedAmount())->toBe(50000)
        ->and($november->fresh()->remainingAmount())->toBe(350000)
        ->and(CateringCredit::query()->where('source_payment_id', $payment->id)->sole()->original_amount)->toBe(50000)
        ->and(CateringPayment::query()->where('catering_bill_id', $october->id)->count())->toBe(1)
        ->and($unrelatedPayment->fresh()->amount)->toBe(123000);

    app(CateringPaymentService::class)->update(
        $payment,
        350000,
        CarbonImmutable::parse('2026-10-08 10:00:00'),
        'Dikoreksi lagi',
    );

    expect($october->fresh()->generatedCreditAmount())->toBe(0)
        ->and($october->fresh()->remainingAmount())->toBe(50000)
        ->and($october->fresh()->payment_status)->toBe(CateringBillStatus::Partial)
        ->and($november->fresh()->creditAppliedAmount())->toBe(0)
        ->and(CateringCredit::query()->where('source_payment_id', $payment->id)->doesntExist())->toBeTrue();
});

it('deletes a payment and removes stale source credit and downstream allocations', function () {
    ['member' => $member, 'class' => $schoolClass] = billingParticipant();
    storeBillingAttendance($member, 2026, 10);
    generateBillingPeriod($schoolClass, month: 10);
    $october = CateringBill::query()->sole();
    $payment = app(CateringPaymentService::class)->record($october, 500000);
    storeBillingAttendance($member, 2026, 11);
    generateBillingPeriod($schoolClass, month: 11);
    $november = CateringBill::query()->where('period_month', 11)->sole();

    app(CateringPaymentService::class)->delete($payment);

    expect(CateringPayment::query()->count())->toBe(0)
        ->and(CateringCredit::query()->count())->toBe(0)
        ->and(CateringCreditAllocation::query()->count())->toBe(0)
        ->and($october->fresh()->paidAmount())->toBe(0)
        ->and($october->fresh()->remainingAmount())->toBe(400000)
        ->and($october->fresh()->payment_status)->toBe(CateringBillStatus::Unpaid)
        ->and($november->fresh()->creditAppliedAmount())->toBe(0)
        ->and($november->fresh()->remainingAmount())->toBe(400000)
        ->and($november->fresh()->payment_status)->toBe(CateringBillStatus::Unpaid);
});

it('does not generate new future bills for inactive participants', function () {
    ['member' => $member, 'class' => $schoolClass] = billingParticipant();
    storeBillingAttendance($member, 2026, 10);
    generateBillingPeriod($schoolClass);
    $historicalBill = CateringBill::query()->sole();

    $member->update(['is_active' => false]);
    storeBillingAttendance($member, 2026, 11);
    $result = generateBillingPeriod($schoolClass, month: 11);

    expect($result['created'])->toBe(0)
        ->and(CateringBill::query()->count())->toBe(1)
        ->and($historicalBill->fresh())->not->toBeNull();
});

it('rejects non-positive payments and blocks normal payment after settlement', function () {
    ['member' => $member, 'class' => $schoolClass] = billingParticipant();
    storeBillingAttendance($member, 2026, 10);
    generateBillingPeriod($schoolClass);
    $bill = CateringBill::query()->sole();
    $payments = app(CateringPaymentService::class);

    expect(fn () => $payments->record($bill, 0))->toThrow(ValidationException::class);

    $payments->record($bill, 400000);

    expect(fn () => $payments->record($bill, 1))->toThrow(ValidationException::class)
        ->and($bill->fresh()->payments()->count())->toBe(1);
});

it('generates many participant snapshots with bounded reads and bulk bill writes', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $category = CateringCategory::factory()->student()->create(['price_per_day' => 20000]);
    $members = CateringMember::factory()->count(30)->create([
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);

    foreach ($members as $member) {
        storeBillingAttendance($member, 2026, 10);
    }

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    generateBillingPeriod($schoolClass);

    expect(CateringBill::query()->count())->toBe(30)
        ->and($queries)->toBeLessThan(15);
});
