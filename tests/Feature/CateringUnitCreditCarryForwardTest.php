<?php

use App\Models\CateringEmployeeUnit;
use App\Models\CateringUnitAttendance;
use App\Models\CateringUnitBill;
use App\Models\CateringUnitCredit;
use App\Models\CateringUnitCreditAllocation;
use App\Models\CateringUnitPayment;
use App\Services\CateringMonthlyReportService;
use App\Services\CateringUnitBillingService;
use App\Services\CateringUnitFinancialReconciliationService;
use App\Services\CateringUnitPaymentService;
use Carbon\CarbonImmutable;

function unitCreditBill(CateringEmployeeUnit $unit, int $month, int $year, int $amount): CateringUnitBill
{
    CateringUnitAttendance::factory()->create([
        'catering_employee_unit_id' => $unit->id,
        'attendance_date' => sprintf('%04d-%02d-05', $year, $month),
        'quantity' => intdiv($amount, 100),
        'price_per_person_snapshot' => 100,
    ]);

    return app(CateringUnitBillingService::class)->syncUnitPeriod($unit->id, $month, $year);
}

function unitCreditPayment(CateringUnitBill $bill, int $amount, string $paidAt = '2026-01-15'): CateringUnitPayment
{
    return app(CateringUnitPaymentService::class)->create(
        bill: $bill,
        amount: $amount,
        paidAt: CarbonImmutable::parse($paidAt),
    );
}

it('carries overpayment forward through later bills of only the same unit', function () {
    $unit = CateringEmployeeUnit::factory()->create();
    $otherUnit = CateringEmployeeUnit::factory()->create();
    $sourceBill = unitCreditBill($unit, 1, 2026, 1000);
    $februaryBill = unitCreditBill($unit, 2, 2026, 600);
    $marchBill = unitCreditBill($unit, 3, 2026, 700);
    $otherBill = unitCreditBill($otherUnit, 2, 2026, 500);

    $payment = unitCreditPayment($sourceBill, 2200);

    $credit = CateringUnitCredit::query()->sole();
    expect($credit->catering_employee_unit_id)->toBe($unit->id)
        ->and($credit->source_unit_payment_id)->toBe($payment->id)
        ->and($credit->source_unit_bill_id)->toBe($sourceBill->id)
        ->and($credit->original_amount)->toBe(1200)
        ->and($credit->remaining_amount)->toBe(0);
    expect(CateringUnitCreditAllocation::query()->orderBy('catering_unit_bill_id')->pluck('amount', 'catering_unit_bill_id')->all())
        ->toBe([$februaryBill->id => 600, $marchBill->id => 600]);
    expect(app(CateringUnitPaymentService::class)->summaryForBill($sourceBill->refresh()))->toMatchArray([
        'paid_amount' => 2200,
        'recognized_paid_amount' => 1000,
        'overpaid_amount' => 1200,
    ]);
    expect(app(CateringUnitPaymentService::class)->summaryForBill($marchBill->refresh()))->toMatchArray([
        'paid_amount' => 0,
        'credit_applied_amount' => 600,
        'recognized_paid_amount' => 600,
        'remaining_amount' => 100,
    ]);
    expect(app(CateringUnitPaymentService::class)->summaryForBill($otherBill->refresh()))->toMatchArray([
        'credit_applied_amount' => 0,
        'remaining_amount' => 500,
    ]);
});

it('never allocates a credit to an earlier bill or its source bill', function () {
    $unit = CateringEmployeeUnit::factory()->create();
    $earlierBill = unitCreditBill($unit, 1, 2026, 1000);
    $sourceBill = unitCreditBill($unit, 2, 2026, 1000);

    unitCreditPayment($sourceBill, 1500, '2026-02-15');

    expect(CateringUnitCreditAllocation::query()->count())->toBe(0)
        ->and(CateringUnitCredit::query()->value('remaining_amount'))->toBe(500)
        ->and($earlierBill->refresh()->creditAppliedAmount())->toBe(0)
        ->and($sourceBill->refresh()->creditAppliedAmount())->toBe(0);
});

it('rebuilds allocations when an overpayment is edited or deleted', function () {
    $unit = CateringEmployeeUnit::factory()->create();
    $sourceBill = unitCreditBill($unit, 1, 2026, 1000);
    $futureBill = unitCreditBill($unit, 2, 2026, 1000);
    $payment = unitCreditPayment($sourceBill, 1500);
    $paymentService = app(CateringUnitPaymentService::class);

    $paymentService->update($payment, 2200, CarbonImmutable::parse('2026-01-20'));

    expect(CateringUnitCredit::query()->value('original_amount'))->toBe(1200)
        ->and(CateringUnitCredit::query()->value('remaining_amount'))->toBe(200)
        ->and($futureBill->refresh()->creditAppliedAmount())->toBe(1000);

    $paymentService->delete($payment);

    expect(CateringUnitCredit::query()->count())->toBe(0)
        ->and(CateringUnitCreditAllocation::query()->count())->toBe(0)
        ->and($futureBill->refresh()->remainingAmount())->toBe(1000);
});

it('reconciles credits after source bill resync and subsidy changes', function () {
    $unit = CateringEmployeeUnit::factory()->create();
    $sourceBill = unitCreditBill($unit, 1, 2026, 1000);
    $futureBill = unitCreditBill($unit, 2, 2026, 1000);
    unitCreditPayment($sourceBill, 1500);
    $attendance = CateringUnitAttendance::query()
        ->where('catering_employee_unit_id', $unit->id)
        ->whereMonth('attendance_date', 1)
        ->sole();

    $attendance->update(['quantity' => 12]);
    app(CateringUnitBillingService::class)->syncUnitPeriod($unit->id, 1, 2026);

    expect(CateringUnitCredit::query()->value('original_amount'))->toBe(300)
        ->and($futureBill->refresh()->creditAppliedAmount())->toBe(300);

    app(CateringUnitBillingService::class)->updateSubsidy($sourceBill->refresh(), [
        'employee_subsidy_enabled' => true,
        'employee_subsidy_rate' => 25,
        'student_subsidy_enabled' => false,
        'student_subsidy_qty' => 0,
        'student_subsidy_rate' => 0,
    ]);

    expect(CateringUnitCredit::query()->value('original_amount'))->toBe(600)
        ->and($futureBill->refresh()->creditAppliedAmount())->toBe(600);
});

it('reports recognized settlement separately and never treats allocated credit as cash income', function () {
    $unit = CateringEmployeeUnit::factory()->create();
    $sourceBill = unitCreditBill($unit, 1, 2026, 1000);
    unitCreditBill($unit, 2, 2026, 1000);
    unitCreditPayment($sourceBill, 1500, '2026-01-15');
    $reportService = app(CateringMonthlyReportService::class);

    $january = $reportService->financialOverview(1, 2026);
    $february = $reportService->financialOverview(2, 2026);
    $januaryIncome = $reportService->actualIncomeForDateRange('2026-01-01', '2026-01-31');
    $februaryIncome = $reportService->actualIncomeForDateRange('2026-02-01', '2026-02-28');

    expect($january['unit'])->toMatchArray(['billed' => 1000, 'paid' => 1000, 'remaining' => 0, 'overpaid' => 500]);
    expect($february['unit'])->toMatchArray(['billed' => 1000, 'paid' => 500, 'remaining' => 500, 'overpaid' => 0]);
    expect($januaryIncome['unit'])->toBe(1500)
        ->and($februaryIncome['unit'])->toBe(0);
});

it('backfills historical overpayments idempotently through the reconciliation command', function () {
    $unit = CateringEmployeeUnit::factory()->create();
    $sourceBill = CateringUnitBill::factory()->create([
        'catering_employee_unit_id' => $unit->id,
        'period_month' => 1,
        'period_year' => 2026,
        'gross_amount' => 1000,
        'net_amount' => 1000,
    ]);
    $futureBill = CateringUnitBill::factory()->create([
        'catering_employee_unit_id' => $unit->id,
        'period_month' => 2,
        'period_year' => 2026,
        'gross_amount' => 1000,
        'net_amount' => 1000,
    ]);
    CateringUnitPayment::factory()->create([
        'catering_unit_bill_id' => $sourceBill->id,
        'amount' => 1600,
    ]);

    $this->artisan('catering:reconcile-unit-credits', ['--unit' => [$unit->id]])->assertSuccessful();
    $firstCredit = CateringUnitCredit::query()->sole()->only([
        'catering_employee_unit_id', 'source_unit_bill_id', 'original_amount', 'remaining_amount',
    ]);
    $firstAllocation = CateringUnitCreditAllocation::query()->sole()->only(['catering_unit_bill_id', 'amount']);

    app(CateringUnitFinancialReconciliationService::class)->reconcileUnits([$unit->id]);

    expect(CateringUnitCredit::query()->sole()->only(array_keys($firstCredit)))->toBe($firstCredit)
        ->and(CateringUnitCreditAllocation::query()->sole()->only(array_keys($firstAllocation)))->toBe($firstAllocation)
        ->and($firstAllocation)->toBe(['catering_unit_bill_id' => $futureBill->id, 'amount' => 600]);
});
