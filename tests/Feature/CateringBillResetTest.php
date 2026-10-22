<?php

use App\Enums\CateringAttendanceStatus;
use App\Enums\CateringParticipantGroup;
use App\Livewire\CateringBills\Index as CateringBillIndex;
use App\Models\CateringAttendance;
use App\Models\CateringBill;
use App\Models\CateringCategory;
use App\Models\CateringCredit;
use App\Models\CateringCreditAllocation;
use App\Models\CateringMember;
use App\Models\CateringMemberPeriod;
use App\Models\CateringPayment;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\CateringBillingService;
use App\Services\CateringBillResetService;
use App\Services\CateringInvoiceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function billResetService(): CateringBillResetService
{
    return app(CateringBillResetService::class);
}

function createResetBill(CateringMember $member, int $year, int $month, int $grossAmount = 100000): CateringBill
{
    return CateringBill::factory()->create([
        'catering_member_id' => $member->id,
        'school_class_id' => $member->school_class_id,
        'catering_category_id' => $member->catering_category_id,
        'period_year' => $year,
        'period_month' => $month,
        'gross_amount' => $grossAmount,
    ]);
}

it('shows an admin-only reset action and denies unauthorized access', function () {
    $admin = User::factory()->admin()->create();
    $waliKelas = User::factory()->waliKelas()->create();

    Livewire::test(CateringBillIndex::class)
        ->assertForbidden();

    expect($admin->can('resetPeriod', CateringBill::class))->toBeTrue()
        ->and($waliKelas->can('resetPeriod', CateringBill::class))->toBeFalse();

    Livewire::actingAs($admin)
        ->test(CateringBillIndex::class)
        ->assertSee('Reset Tagihan');

    Livewire::actingAs($waliKelas)
        ->test(CateringBillIndex::class)
        ->assertForbidden();
});

it('reauthorizes both reset actions when they are invoked directly', function () {
    $admin = User::factory()->admin()->create();
    $waliKelas = User::factory()->waliKelas()->create();
    $bill = CateringBill::factory()->create([
        'period_month' => 8,
        'period_year' => 2026,
    ]);
    $confirmation = Livewire::actingAs($admin)->test(CateringBillIndex::class);

    $this->actingAs($waliKelas);
    $confirmation
        ->call('confirmResetBills')
        ->assertForbidden();

    $reset = Livewire::actingAs($admin)
        ->test(CateringBillIndex::class)
        ->set('resetBillMonth', 8)
        ->set('resetBillYear', 2026);

    $this->actingAs($waliKelas);
    $reset
        ->call('resetBills')
        ->assertForbidden();

    expect($bill->fresh())->not->toBeNull();
});

it('previews reset counts in a modal and keeps the selected period snapshotted', function () {
    $admin = User::factory()->admin()->create();
    $member = CateringMember::factory()->create();
    $octoberBill = createResetBill($member, 2026, 10);
    $novemberBill = createResetBill($member, 2026, 11);
    CateringAttendance::factory()->for($member)->create([
        'attendance_date' => '2026-10-01',
        'status' => CateringAttendanceStatus::Ikut,
    ]);
    CateringAttendance::factory()->for($member)->create([
        'attendance_date' => '2026-10-02',
        'status' => CateringAttendanceStatus::Sakit,
    ]);

    Livewire::actingAs($admin)
        ->test(CateringBillIndex::class)
        ->set('month', 10)
        ->set('year', 2026)
        ->call('confirmResetBills')
        ->assertSet('showResetBillsModal', true)
        ->assertSet('resetBillMonth', 10)
        ->assertSet('resetBillYear', 2026)
        ->assertSet('resetBillSummary.bill_count', 1)
        ->assertSet('resetBillSummary.attendance_count', 2)
        ->assertSet('resetBillSummary.payment_count', 0)
        ->assertSet('resetBillSummary.financial_dependency_count', 0)
        ->assertSee('Reset Tagihan Oktober 2026?')
        ->assertSee('Data absensi tidak akan dihapus.')
        ->assertSeeHtml('wire:click="resetBills"')
        ->assertSeeHtml('wire:loading.attr="disabled"')
        ->assertSeeHtml('wire:target="resetBills"')
        ->set('month', 11)
        ->set('year', 2026)
        ->call('resetBills')
        ->assertSet('showResetBillsModal', false);

    expect($octoberBill->fresh())->toBeNull()
        ->and($novemberBill->fresh())->not->toBeNull();
});

it('deletes every clean selected-period bill while preserving attendance and other periods', function () {
    $firstMember = CateringMember::factory()->create();
    $secondMember = CateringMember::factory()->create();
    $previousBill = createResetBill($firstMember, 2026, 7);
    $selectedBills = collect([
        createResetBill($firstMember, 2026, 8),
        createResetBill($secondMember, 2026, 8),
    ]);
    $nextBill = createResetBill($secondMember, 2026, 9);

    foreach ([
        [$firstMember, '2026-07-31', CateringAttendanceStatus::Izin],
        [$firstMember, '2026-08-01', CateringAttendanceStatus::Ikut],
        [$firstMember, '2026-08-02', CateringAttendanceStatus::Libur],
        [$secondMember, '2026-08-01', CateringAttendanceStatus::Sakit],
        [$secondMember, '2026-09-01', CateringAttendanceStatus::Alfa],
    ] as [$member, $date, $status]) {
        CateringAttendance::factory()->for($member)->create([
            'attendance_date' => $date,
            'status' => $status,
        ]);
    }

    $attendanceBefore = CateringAttendance::query()->orderBy('id')->pluck('status', 'id')->all();
    $result = billResetService()->reset(2026, 8);

    expect($result['can_reset'])->toBeTrue()
        ->and($result['deleted_count'])->toBe(2)
        ->and(CateringBill::query()->whereKey($selectedBills->pluck('id'))->exists())->toBeFalse()
        ->and($previousBill->fresh())->not->toBeNull()
        ->and($nextBill->fresh())->not->toBeNull()
        ->and(CateringAttendance::query()->orderBy('id')->pluck('status', 'id')->all())->toBe($attendanceBefore);
});

it('blocks reset when a selected-period bill has a payment', function () {
    $member = CateringMember::factory()->create();
    $bill = createResetBill($member, 2026, 8);
    $payment = CateringPayment::factory()->create([
        'catering_bill_id' => $bill->id,
        'catering_member_id' => $member->id,
    ]);

    $preview = billResetService()->preview(2026, 8);
    $result = billResetService()->reset(2026, 8);

    expect($preview['can_reset'])->toBeFalse()
        ->and($preview['payment_count'])->toBe(1)
        ->and($result['deleted_count'])->toBe(0)
        ->and($bill->fresh())->not->toBeNull()
        ->and($payment->fresh())->not->toBeNull();
});

it('blocks reset when a selected-period bill generated credit', function () {
    $member = CateringMember::factory()->create();
    $bill = createResetBill($member, 2026, 8);
    $payment = CateringPayment::factory()->create([
        'catering_bill_id' => $bill->id,
        'catering_member_id' => $member->id,
    ]);
    $credit = CateringCredit::factory()->create([
        'catering_member_id' => $member->id,
        'source_payment_id' => $payment->id,
        'source_bill_id' => $bill->id,
    ]);

    $preview = billResetService()->preview(2026, 8);
    $result = billResetService()->reset(2026, 8);

    expect($preview['can_reset'])->toBeFalse()
        ->and($preview['generated_credit_count'])->toBe(1)
        ->and($result['deleted_count'])->toBe(0)
        ->and($bill->fresh())->not->toBeNull()
        ->and($credit->fresh())->not->toBeNull();
});

it('blocks reset when selected bills receive or source credit allocations', function () {
    $member = CateringMember::factory()->create();
    $sourceBill = createResetBill($member, 2026, 7);
    $selectedBill = createResetBill($member, 2026, 8);
    $laterBill = createResetBill($member, 2026, 9);
    $sourcePayment = CateringPayment::factory()->create([
        'catering_bill_id' => $sourceBill->id,
        'catering_member_id' => $member->id,
    ]);
    $sourceCredit = CateringCredit::factory()->create([
        'catering_member_id' => $member->id,
        'source_payment_id' => $sourcePayment->id,
        'source_bill_id' => $sourceBill->id,
    ]);
    $incomingAllocation = CateringCreditAllocation::factory()->create([
        'catering_credit_id' => $sourceCredit->id,
        'catering_bill_id' => $selectedBill->id,
    ]);

    $selectedPayment = CateringPayment::factory()->create([
        'catering_bill_id' => $selectedBill->id,
        'catering_member_id' => $member->id,
    ]);
    $selectedCredit = CateringCredit::factory()->create([
        'catering_member_id' => $member->id,
        'source_payment_id' => $selectedPayment->id,
        'source_bill_id' => $selectedBill->id,
    ]);
    $downstreamAllocation = CateringCreditAllocation::factory()->create([
        'catering_credit_id' => $selectedCredit->id,
        'catering_bill_id' => $laterBill->id,
    ]);

    $preview = billResetService()->preview(2026, 8);
    $result = billResetService()->reset(2026, 8);

    expect($preview['can_reset'])->toBeFalse()
        ->and($preview['allocation_count'])->toBe(2)
        ->and($result['deleted_count'])->toBe(0)
        ->and($selectedBill->fresh())->not->toBeNull()
        ->and($incomingAllocation->fresh())->not->toBeNull()
        ->and($downstreamAllocation->fresh())->not->toBeNull();
});

it('shows a blocked modal without an executable reset action', function () {
    $member = CateringMember::factory()->create();
    $bill = createResetBill($member, 2026, 8);
    CateringPayment::factory()->create([
        'catering_bill_id' => $bill->id,
        'catering_member_id' => $member->id,
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringBillIndex::class)
        ->set('month', 8)
        ->set('year', 2026)
        ->call('confirmResetBills')
        ->assertSet('resetBillSummary.can_reset', false)
        ->assertSee('Reset tidak dapat dilakukan karena periode ini sudah memiliki transaksi pembayaran atau data finansial terkait.')
        ->assertSee('Hapus atau koreksi pembayaran terlebih dahulu sebelum mereset tagihan.')
        ->assertDontSeeHtml('wire:click="resetBills"');
});

it('rechecks financial dependencies when reset is confirmed', function () {
    $admin = User::factory()->admin()->create();
    $member = CateringMember::factory()->create();
    $bill = createResetBill($member, 2026, 8);
    $component = Livewire::actingAs($admin)
        ->test(CateringBillIndex::class)
        ->set('month', 8)
        ->set('year', 2026)
        ->call('confirmResetBills')
        ->assertSet('resetBillSummary.can_reset', true);

    CateringPayment::factory()->create([
        'catering_bill_id' => $bill->id,
        'catering_member_id' => $member->id,
    ]);

    $component
        ->call('resetBills')
        ->assertSet('showResetBillsModal', true)
        ->assertSet('resetBillSummary.can_reset', false)
        ->assertDispatched('toast', type: 'danger');

    expect($bill->fresh())->not->toBeNull();
});

it('treats an empty period and a second reset as safe no-ops', function () {
    $first = billResetService()->reset(2026, 8);
    $second = billResetService()->reset(2026, 8);

    expect($first['can_reset'])->toBeTrue()
        ->and($first['deleted_count'])->toBe(0)
        ->and($second['can_reset'])->toBeTrue()
        ->and($second['deleted_count'])->toBe(0);
});

it('keeps bills empty until explicit synchronization recreates them', function () {
    $admin = User::factory()->admin()->create();
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $category = CateringCategory::factory()->student()->create(['price_per_day' => 15000]);
    $member = CateringMember::factory()->create([
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);
    CateringMemberPeriod::create(['catering_member_id' => $member->id, 'start_date' => '2026-08-01', 'end_date' => null]);

    foreach (range(1, 4) as $day) {
        CateringAttendance::factory()->for($member)->create([
            'attendance_date' => "2026-08-0{$day}",
            'status' => CateringAttendanceStatus::Ikut,
        ]);
    }

    app(CateringBillingService::class)->generate(
        2026,
        8,
        CateringParticipantGroup::Student,
        $schoolClass,
        actor: $admin,
    );
    $attendanceBefore = CateringAttendance::query()->orderBy('id')->pluck('status', 'id')->all();

    billResetService()->reset(2026, 8);

    expect(CateringBill::query()->where('period_year', 2026)->where('period_month', 8)->count())->toBe(0)
        ->and(CateringAttendance::query()->orderBy('id')->pluck('status', 'id')->all())->toBe($attendanceBefore);

    app(CateringBillingService::class)->generate(
        2026,
        8,
        CateringParticipantGroup::Student,
        $schoolClass,
        actor: $admin,
    );

    expect(CateringBill::query()->where('period_year', 2026)->where('period_month', 8)->count())->toBe(1)
        ->and(CateringBill::query()->sole()->gross_amount)->toBe(60000)
        ->and(CateringAttendance::query()->orderBy('id')->pluck('status', 'id')->all())->toBe($attendanceBefore);
});

it('removes a clean reset period from cumulative invoice outstanding', function () {
    $schoolClass = SchoolClass::factory()->create();
    $category = CateringCategory::factory()->student()->create(['price_per_day' => 15000]);
    $member = CateringMember::factory()->create([
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);
    CateringMemberPeriod::create(['catering_member_id' => $member->id, 'start_date' => '2026-08-01', 'end_date' => null]);
    createResetBill($member, 2026, 8, 152000);
    createResetBill($member, 2026, 9, 302000);
    $start = CarbonImmutable::create(2026, 10, 1);
    $invoiceService = app(CateringInvoiceService::class);

    $before = $invoiceService->buildMemberInvoice(
        $member->fresh(['cateringCategory', 'schoolClass']),
        $start,
        $start->endOfMonth(),
    );
    billResetService()->reset(2026, 8);
    $after = $invoiceService->buildMemberInvoice(
        $member->fresh(['cateringCategory', 'schoolClass']),
        $start,
        $start->endOfMonth(),
    );

    expect($before['totalOutstanding'])->toBe(454000)
        ->and($after['totalOutstanding'])->toBe(302000);
});

it('uses bounded queries for preview and set-based reset as the period grows', function () {
    $members = CateringMember::factory()->count(20)->create();

    foreach ($members as $member) {
        createResetBill($member, 2026, 8);
        CateringAttendance::factory()->for($member)->create([
            'attendance_date' => '2026-08-01',
            'status' => CateringAttendanceStatus::Ikut,
        ]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $preview = billResetService()->preview(2026, 8);
    $previewQueryCount = count(DB::getQueryLog());
    DB::flushQueryLog();
    $result = billResetService()->reset(2026, 8);
    $resetQueryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($preview['bill_count'])->toBe(20)
        ->and($previewQueryCount)->toBeLessThanOrEqual(5)
        ->and($result['deleted_count'])->toBe(20)
        ->and($resetQueryCount)->toBeLessThanOrEqual(8);
});
