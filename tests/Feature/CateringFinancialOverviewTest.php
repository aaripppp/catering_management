<?php

use App\Enums\CateringAttendanceStatus;
use App\Enums\CateringParticipantGroup;
use App\Livewire\CateringMonthlyReport\Index as CateringMonthlyReport;
use App\Models\CateringAttendance;
use App\Models\CateringBill;
use App\Models\CateringCategory;
use App\Models\CateringCultureMealIncome;
use App\Models\CateringEmployeeUnit;
use App\Models\CateringMember;
use App\Models\CateringMemberPeriod;
use App\Models\CateringPayment;
use App\Models\CateringUnitAttendance;
use App\Models\CateringUnitBill;
use App\Models\CateringUnitPayment;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\CateringCultureMealSettingService;
use App\Services\CateringMonthlyReportService;
use App\Services\CateringPaymentService;
use App\Services\CateringUnitBillingService;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * @return array{source: CateringBill, first: CateringBill, second: CateringBill}
 */
function financialOverviewStudentScenario(): array
{
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $category = CateringCategory::factory()->student()->create();
    $firstMember = CateringMember::factory()->create([
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);
    $secondMember = CateringMember::factory()->create([
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);
    $source = CateringBill::factory()->create([
        'catering_member_id' => $firstMember->id,
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
        'period_month' => 9,
        'period_year' => 2026,
        'gross_amount' => 1000,
    ]);
    $first = CateringBill::factory()->create([
        'catering_member_id' => $firstMember->id,
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
        'period_month' => 10,
        'period_year' => 2026,
        'gross_amount' => 1000,
    ]);
    $second = CateringBill::factory()->create([
        'catering_member_id' => $secondMember->id,
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
        'period_month' => 10,
        'period_year' => 2026,
        'gross_amount' => 1000,
    ]);
    $paymentService = app(CateringPaymentService::class);
    $paymentService->record($source, 1100);
    $paymentService->record($first, 400);
    $paymentService->record($second, 1200);

    return compact('source', 'first', 'second');
}

/** @return array{unit: CateringEmployeeUnit, attendance: CateringUnitAttendance, bill: CateringUnitBill} */
function financialOverviewSyncedUnitBill(string $name, int $month = 10, int $year = 2026): array
{
    $unit = CateringEmployeeUnit::factory()->create(['name' => $name]);
    $attendance = CateringUnitAttendance::factory()->create([
        'catering_employee_unit_id' => $unit->id,
        'attendance_date' => sprintf('%04d-%02d-05', $year, $month),
        'quantity' => 1,
        'price_per_person_snapshot' => 1000,
    ]);
    $bill = app(CateringUnitBillingService::class)->syncUnitPeriod($unit->id, $month, $year);

    if ($bill === null) {
        throw new RuntimeException('Failed to synchronize the unit bill for the financial overview test.');
    }

    return compact('unit', 'attendance', 'bill');
}

function financialOverviewCultureAttendance(int $count, int $month = 10, int $year = 2026): CateringMember
{
    $member = CateringMember::factory()
        ->for(CateringCategory::factory()->student())
        ->create();
    CateringMemberPeriod::create([
        'catering_member_id' => $member->id,
        'start_date' => sprintf('%04d-%02d-01', $year, $month),
        'end_date' => null,
    ]);

    foreach (range(1, $count) as $day) {
        CateringAttendance::factory()->for($member)->create([
            'attendance_date' => sprintf('%04d-%02d-%02d', $year, $month, $day),
            'status' => CateringAttendanceStatus::Alfa,
        ]);
    }

    return $member;
}

function financialOverviewSummaryBlockMarkup(Testable $component, string $id): string
{
    $html = html_entity_decode($component->html(), ENT_QUOTES | ENT_HTML5);
    preg_match('/<article id="'.preg_quote($id, '/').'".*?<\/article>/s', $html, $matches);

    return $matches[0] ?? '';
}

function financialOverviewSummaryBlockText(Testable $component, string $id): string
{
    $markup = str_replace(['</td>', '</th>', '</h3>'], ' ', financialOverviewSummaryBlockMarkup($component, $id));

    return trim((string) preg_replace('/\s+/', ' ', strip_tags($markup)));
}

it('combines student unit and culture values without netting overpayments or legacy employee bills', function () {
    financialOverviewStudentScenario();
    CateringBill::factory()->create([
        'participant_group' => CateringParticipantGroup::Employee,
        'school_class_id' => null,
        'school_class_name' => null,
        'school_level' => null,
        'gross_amount' => 9000,
    ]);
    ['attendance' => $staleAttendance, 'bill' => $overpaidUnitBill] = financialOverviewSyncedUnitBill('Unit A');
    ['bill' => $underpaidUnitBill] = financialOverviewSyncedUnitBill('Unit B');
    CateringUnitPayment::factory()->create([
        'catering_unit_bill_id' => $overpaidUnitBill->id,
        'amount' => 1200,
    ]);
    CateringUnitPayment::factory()->create([
        'catering_unit_bill_id' => $underpaidUnitBill->id,
        'amount' => 500,
    ]);
    $staleAttendance->update(['quantity' => 2]);
    $unsyncedUnit = CateringEmployeeUnit::factory()->create(['name' => 'Belum Sinkron']);
    CateringUnitAttendance::factory()->create([
        'catering_employee_unit_id' => $unsyncedUnit->id,
        'attendance_date' => '2026-10-06',
        'quantity' => 99,
        'price_per_person_snapshot' => 1000,
    ]);
    financialOverviewCultureAttendance(10);
    app(CateringCultureMealSettingService::class)->updateCultureMealPrice(100);
    CateringCultureMealIncome::factory()->create([
        'income_date' => '2026-10-10',
        'amount' => 1200,
    ]);

    $overview = app(CateringMonthlyReportService::class)->financialOverview(10, 2026);

    expect($overview)->toBe([
        'student' => [
            'billed' => 2000,
            'paid' => 1700,
            'remaining' => 500,
        ],
        'unit' => [
            'billed' => 2000,
            'paid' => 1700,
            'remaining' => 500,
            'overpaid' => 200,
            'paid_count' => 0,
            'partial_count' => 1,
            'unpaid_count' => 0,
            'has_stale' => true,
            'stale_count' => 1,
        ],
        'culture' => [
            'recorded_count' => 10,
            'price' => 100,
            'billed' => 1000,
            'income' => 1200,
            'difference' => -200,
        ],
        'total' => [
            'billed' => 5000,
            'paid' => 4600,
            'uncovered_difference' => 1000,
        ],
    ]);

    expect(CateringUnitBill::query()->where('catering_employee_unit_id', $unsyncedUnit->id)->exists())->toBeFalse();
});

it('uses the current culture price when reporting a historical period', function () {
    financialOverviewCultureAttendance(1, month: 9);
    $settingService = app(CateringCultureMealSettingService::class);
    $settingService->updateCultureMealPrice(16000);

    $firstOverview = app(CateringMonthlyReportService::class)->financialOverview(9, 2026);
    $settingService->updateCultureMealPrice(18000);
    $secondOverview = app(CateringMonthlyReportService::class)->financialOverview(9, 2026);

    expect($firstOverview['culture'])->toMatchArray([
        'recorded_count' => 1,
        'price' => 16000,
        'billed' => 16000,
        'difference' => 16000,
    ])->and($secondOverview['culture'])->toMatchArray([
        'recorded_count' => 1,
        'price' => 18000,
        'billed' => 18000,
        'difference' => 18000,
    ]);
});

it('returns a complete zero overview for an empty period', function () {
    $overview = app(CateringMonthlyReportService::class)->financialOverview(2, 2026);

    expect($overview)->toBe([
        'student' => ['billed' => 0, 'paid' => 0, 'remaining' => 0],
        'unit' => [
            'billed' => 0,
            'paid' => 0,
            'remaining' => 0,
            'overpaid' => 0,
            'paid_count' => 0,
            'partial_count' => 0,
            'unpaid_count' => 0,
            'has_stale' => false,
            'stale_count' => 0,
        ],
        'culture' => [
            'recorded_count' => 0,
            'price' => 16000,
            'billed' => 0,
            'income' => 0,
            'difference' => 0,
        ],
        'total' => ['billed' => 0, 'paid' => 0, 'uncovered_difference' => 0],
    ]);
});

it('renders four financial rows and a stale unit warning', function () {
    CateringBill::factory()->create(['gross_amount' => 1000]);
    ['attendance' => $attendance] = financialOverviewSyncedUnitBill('Unit Stale');
    $attendance->update(['quantity' => 2]);
    financialOverviewCultureAttendance(1);
    CateringCultureMealIncome::factory()->create([
        'income_date' => '2026-10-10',
        'amount' => 16200,
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMonthlyReport::class)
        ->set('month', 10)
        ->set('year', 2026)
        ->assertSee('Siswa')
        ->assertSee('Total Tagihan Siswa')
        ->assertSee('Total Terbayar Siswa')
        ->assertSee('Tunggakan Siswa')
        ->assertSee('Pegawai / Unit')
        ->assertSee('Total Tagihan Unit')
        ->assertSee('Total Terbayar Unit')
        ->assertSee('Tunggakan Unit')
        ->assertSee('Budaya Makan')
        ->assertSee('Tagihan Tercatat')
        ->assertSee('Pemasukan Manual')
        ->assertSee('Selisih')
        ->assertSee('Total Keseluruhan')
        ->assertSee('Total Semua Tagihan')
        ->assertSee('Total Semua Terbayar')
        ->assertSee('Total Selisih Belum Tertutup')
        ->assertSee('-Rp 200')
        ->assertSee('Sebagian Tagihan Unit perlu disinkronkan. Nilai yang tampil menggunakan snapshot tagihan terakhir.');
});

it('renders all four domains in separate Ringkasan blocks with authoritative values', function () {
    financialOverviewStudentScenario();
    $unitBills = collect([
        financialOverviewSyncedUnitBill('Unit Lunas')['bill'],
        financialOverviewSyncedUnitBill('Unit Sebagian')['bill'],
        financialOverviewSyncedUnitBill('Unit Belum Bayar')['bill'],
        financialOverviewSyncedUnitBill('Unit Lebih Bayar')['bill'],
    ]);
    foreach ([0 => 1000, 1 => 400, 3 => 1200] as $index => $amount) {
        CateringUnitPayment::factory()->create([
            'catering_unit_bill_id' => $unitBills[$index]->id,
            'amount' => $amount,
        ]);
    }
    financialOverviewCultureAttendance(10);
    CateringCultureMealIncome::factory()->create([
        'income_date' => '2026-10-10',
        'amount' => 120000,
    ]);
    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMonthlyReport::class)
        ->set('month', 10)
        ->set('year', 2026)
        ->set('participantGroup', CateringParticipantGroup::Student->value);

    $student = financialOverviewSummaryBlockText($component, 'summary-student');
    $unit = financialOverviewSummaryBlockText($component, 'summary-unit');
    $culture = financialOverviewSummaryBlockText($component, 'summary-culture');
    $total = financialOverviewSummaryBlockText($component, 'summary-total');

    expect($component->html())->toContain('id="all-domain-summary" class="grid grid-cols-1 gap-4 lg:grid-cols-2"');
    expect($student)->toContain(
        'Total Tagihan Rp 2.000',
        'Total Terbayar Rp 1.700',
        'Tunggakan Rp 500',
        'Lebih Bayar Rp 200',
        'Lunas 1',
        'Sebagian 1',
        'Belum Bayar 0',
    );
    expect($unit)->toContain(
        'Total Tagihan Unit Rp 4.000',
        'Total Terbayar Unit Rp 2.600',
        'Tunggakan Unit Rp 1.600',
        'Lebih Bayar Unit Rp 200',
        'Unit Lunas 1',
        'Unit Sebagian 1',
        'Unit Belum Bayar 1',
    );
    expect($culture)->toContain(
        'Data Tercatat 10',
        'Harga Budaya Makan Rp 16.000',
        'Tagihan Tercatat Rp 160.000',
        'Pemasukan Manual Rp 120.000',
        'Selisih Rp 40.000',
    )->not->toContain('Lunas', 'Sebagian', 'Belum Bayar', 'Tunggakan');
    expect($total)->toContain(
        'Total Semua Tagihan Rp 166.000',
        'Total Semua Terbayar Rp 124.300',
        'Total Selisih Belum Tertutup Rp 42.100',
    );
});

it('renders a signed negative Budaya Makan difference in Ringkasan', function () {
    financialOverviewCultureAttendance(10);
    CateringCultureMealIncome::factory()->create([
        'income_date' => '2026-10-10',
        'amount' => 200000,
    ]);
    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMonthlyReport::class)
        ->set('month', 10)
        ->set('year', 2026);

    $culture = financialOverviewSummaryBlockText($component, 'summary-culture');

    expect($culture)->toContain('Selisih -Rp 40.000');
});

it('isolates Ringkasan student filters from Unit Budaya and global totals', function () {
    $firstClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $secondClass = SchoolClass::factory()->create(['name' => '8A', 'level' => '8']);
    CateringBill::factory()->create([
        'school_class_id' => $firstClass->id,
        'school_class_name' => '7A',
        'school_level' => '7',
        'gross_amount' => 1000,
    ]);
    CateringBill::factory()->create([
        'school_class_id' => $secondClass->id,
        'school_class_name' => '8A',
        'school_level' => '8',
        'gross_amount' => 2000,
    ]);
    ['bill' => $unitBill] = financialOverviewSyncedUnitBill('Unit Tetap');
    CateringUnitPayment::factory()->create([
        'catering_unit_bill_id' => $unitBill->id,
        'amount' => 400,
    ]);
    financialOverviewCultureAttendance(1);
    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMonthlyReport::class)
        ->set('month', 10)
        ->set('year', 2026)
        ->set('participantGroup', CateringParticipantGroup::Student->value);

    $studentBefore = financialOverviewSummaryBlockText($component, 'summary-student');
    $unitBefore = financialOverviewSummaryBlockText($component, 'summary-unit');
    $cultureBefore = financialOverviewSummaryBlockText($component, 'summary-culture');
    $totalBefore = financialOverviewSummaryBlockText($component, 'summary-total');
    $component->set('jenjang', 'SMP')->set('schoolClassId', (string) $firstClass->id);

    expect(financialOverviewSummaryBlockText($component, 'summary-student'))
        ->not->toBe($studentBefore)
        ->toContain('Total Tagihan Rp 1.000');
    expect(financialOverviewSummaryBlockText($component, 'summary-unit'))->toBe($unitBefore);
    expect(financialOverviewSummaryBlockText($component, 'summary-culture'))->toBe($cultureBefore);
    expect(financialOverviewSummaryBlockText($component, 'summary-total'))->toBe($totalBefore);
});

it('includes stale Unit snapshots and warns inside the Ringkasan Unit block without syncing', function () {
    ['attendance' => $attendance, 'bill' => $bill] = financialOverviewSyncedUnitBill('Unit Stale Ringkasan');
    $attendance->update(['quantity' => 2]);
    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMonthlyReport::class)
        ->set('month', 10)
        ->set('year', 2026);

    $unit = financialOverviewSummaryBlockText($component, 'summary-unit');

    expect($unit)->toContain(
        'Total Tagihan Unit Rp 1.000',
        'Tunggakan Unit Rp 1.000',
        'Sebagian Tagihan Unit perlu disinkronkan. Nilai menggunakan snapshot terakhir.',
    )->and($bill->fresh()->gross_amount)->toBe(1000);
});

it('keeps every Ringkasan domain visible with zero values for an empty period', function () {
    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMonthlyReport::class)
        ->set('month', 2)
        ->set('year', 2026);

    $student = financialOverviewSummaryBlockText($component, 'summary-student');
    $unit = financialOverviewSummaryBlockText($component, 'summary-unit');
    $culture = financialOverviewSummaryBlockText($component, 'summary-culture');
    $total = financialOverviewSummaryBlockText($component, 'summary-total');

    expect($student)->toContain('Total Tagihan Rp 0', 'Total Terbayar Rp 0', 'Lunas 0');
    expect($unit)->toContain('Total Tagihan Unit Rp 0', 'Total Terbayar Unit Rp 0', 'Unit Lunas 0');
    expect($culture)->toContain('Data Tercatat 0', 'Harga Budaya Makan Rp 16.000', 'Selisih Rp 0');
    expect($total)->toContain('Total Semua Tagihan Rp 0', 'Total Semua Terbayar Rp 0', 'Total Selisih Belum Tertutup Rp 0');
    expect($component->html())->not->toContain('Belum ada data laporan untuk periode/filter ini.');
});

it('keeps the global overview independent from student detail filters', function () {
    $firstClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $secondClass = SchoolClass::factory()->create(['name' => '8A', 'level' => '8']);
    CateringBill::factory()->create([
        'school_class_id' => $firstClass->id,
        'school_class_name' => '7A',
        'school_level' => '7',
        'gross_amount' => 1000,
    ]);
    CateringBill::factory()->create([
        'school_class_id' => $secondClass->id,
        'school_class_name' => '8A',
        'school_level' => '8',
        'gross_amount' => 2000,
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMonthlyReport::class)
        ->set('month', 10)
        ->set('year', 2026)
        ->set('participantGroup', CateringParticipantGroup::Student->value)
        ->set('jenjang', 'SMP')
        ->set('schoolClassId', (string) $firstClass->id)
        ->assertViewHas('summary', fn (array $summary): bool => $summary['gross_amount'] === 1000)
        ->assertViewHas('financialOverview', fn (array $overview): bool => $overview['student']['billed'] === 3000);
});

it('updates every domain in the overview when the period changes', function () {
    CateringBill::factory()->create(['period_month' => 11, 'gross_amount' => 2000]);
    CateringUnitBill::factory()->create([
        'period_month' => 11,
        'period_year' => 2026,
        'gross_amount' => 3000,
    ]);
    financialOverviewCultureAttendance(1, month: 11);
    app(CateringCultureMealSettingService::class)->updateCultureMealPrice(4000);
    CateringCultureMealIncome::factory()->create([
        'income_date' => '2026-11-10',
        'amount' => 5000,
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMonthlyReport::class)
        ->set('month', 11)
        ->set('year', 2026)
        ->assertViewHas('financialOverview', fn (array $overview): bool => $overview['student']['billed'] === 2000
            && $overview['unit']['billed'] === 3000
            && $overview['culture']['billed'] === 4000
            && $overview['culture']['income'] === 5000
            && $overview['total']['billed'] === 9000);
});

it('loads a large cross-domain overview with a bounded query count', function () {
    $schoolClass = SchoolClass::factory()->create();
    $studentCategory = CateringCategory::factory()->student()->create();

    foreach (range(1, 25) as $index) {
        $member = CateringMember::factory()->create([
            'school_class_id' => $schoolClass->id,
            'catering_category_id' => $studentCategory->id,
        ]);
        $bill = CateringBill::factory()->create([
            'catering_member_id' => $member->id,
            'school_class_id' => $schoolClass->id,
            'catering_category_id' => $studentCategory->id,
            'gross_amount' => 1000,
        ]);
        CateringPayment::factory()->create([
            'catering_bill_id' => $bill->id,
            'catering_member_id' => $bill->catering_member_id,
            'amount' => 500,
        ]);
    }

    $unitBills = CateringUnitBill::factory()->count(25)->create(['gross_amount' => 1000]);
    foreach ($unitBills as $bill) {
        CateringUnitPayment::factory()->create([
            'catering_unit_bill_id' => $bill->id,
            'amount' => 500,
        ]);
    }

    foreach (range(1, 25) as $index) {
        $member = CateringMember::factory()->create([
            'school_class_id' => $schoolClass->id,
            'catering_category_id' => $studentCategory->id,
        ]);
        CateringMemberPeriod::create([
            'catering_member_id' => $member->id,
            'start_date' => '2026-10-01',
            'end_date' => null,
        ]);
        CateringAttendance::factory()->for($member)->create([
            'attendance_date' => '2026-10-10',
            'status' => CateringAttendanceStatus::Alfa,
        ]);
    }

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $overview = app(CateringMonthlyReportService::class)->financialOverview(10, 2026);

    expect($overview['student'])->toBe(['billed' => 25000, 'paid' => 12500, 'remaining' => 12500])
        ->and($overview['unit'])->toMatchArray([
            'billed' => 25000,
            'paid' => 12500,
            'remaining' => 12500,
            'paid_count' => 0,
            'partial_count' => 25,
            'unpaid_count' => 0,
        ])
        ->and($overview['culture']['recorded_count'])->toBe(25)
        ->and($queries)->toBeLessThanOrEqual(12);
});
