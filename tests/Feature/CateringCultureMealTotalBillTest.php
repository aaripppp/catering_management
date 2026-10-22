<?php

use App\Enums\CateringAttendanceStatus;
use App\Livewire\CateringCultureMeal\Index as CultureMealIndex;
use App\Models\CateringAttendance;
use App\Models\CateringBill;
use App\Models\CateringCategory;
use App\Models\CateringCredit;
use App\Models\CateringMember;
use App\Models\CateringMemberPeriod;
use App\Models\CateringPayment;
use App\Models\CateringUnitBill;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\CateringCultureMealIncomeService;
use App\Services\CateringCultureMealSettingService;
use Livewire\Livewire;

function totalBillMember(string $start = '2026-09-01'): CateringMember
{
    $member = CateringMember::factory()
        ->for(CateringCategory::factory()->student())
        ->create();

    CateringMemberPeriod::create([
        'catering_member_id' => $member->id,
        'start_date' => $start,
        'end_date' => null,
    ]);

    return $member;
}

function totalBillAttendance(CateringMember $member, string $date): CateringAttendance
{
    return CateringAttendance::factory()->for($member)->create([
        'attendance_date' => $date,
        'status' => CateringAttendanceStatus::Alfa,
    ]);
}

function totalBillPage(User $user, int $month = 10, int $year = 2026)
{
    return Livewire::actingAs($user)
        ->test(CultureMealIndex::class)
        ->set('month', $month)
        ->set('year', $year);
}

function totalBillCard(string $amount): string
{
    return 'text-3xl font-bold text-blue-700">'.$amount.'</p>';
}

it('calculates one recorded attendance at the reference price', function () {
    $member = totalBillMember('2026-10-01');
    totalBillAttendance($member, '2026-10-10');

    totalBillPage(User::factory()->admin()->create())
        ->assertSee('Total Tagihan Tercatat')
        ->assertSee('1 × Rp 16.000')
        ->assertSeeHtml(totalBillCard('Rp 16.000'));
});

it('multiplies twenty five recorded attendances by the reference price', function () {
    $member = totalBillMember('2026-10-01');

    for ($day = 1; $day <= 25; $day++) {
        totalBillAttendance($member, sprintf('2026-10-%02d', $day));
    }

    totalBillPage(User::factory()->admin()->create())
        ->assertSee('25 × Rp 16.000')
        ->assertSeeHtml(totalBillCard('Rp 400.000'));
});

it('shows a zero total when nothing is recorded', function () {
    totalBillPage(User::factory()->admin()->create())
        ->assertSee('0 × Rp 16.000')
        ->assertSeeHtml(totalBillCard('Rp 0'));
});

it('recalculates the recorded bill when the reference price changes', function () {
    $admin = User::factory()->admin()->create();
    $member = totalBillMember('2026-10-01');
    totalBillAttendance($member, '2026-10-10');
    totalBillAttendance($member, '2026-10-11');

    $component = totalBillPage($admin)
        ->assertSeeHtml(totalBillCard('Rp 32.000'));

    $component
        ->call('editCultureMealPrice')
        ->set('culture_meal_price', '17000')
        ->call('saveCultureMealPrice')
        ->assertHasNoErrors()
        ->assertSee('2 × Rp 17.000')
        ->assertSeeHtml(totalBillCard('Rp 34.000'))
        ->assertSeeHtml('text-3xl font-bold">2</p>');
});

it('does not change the recorded bill when manual income is created', function () {
    $admin = User::factory()->admin()->create();
    $class = SchoolClass::factory()->create();
    $member = totalBillMember('2026-10-01');
    totalBillAttendance($member, '2026-10-10');
    totalBillAttendance($member, '2026-10-11');

    totalBillPage($admin)
        ->assertSeeHtml(totalBillCard('Rp 32.000'))
        ->call('createIncome')
        ->set('income_date', '2026-10-12')
        ->set('school_class_id', (string) $class->id)
        ->set('amount', '50000')
        ->call('saveIncome')
        ->assertHasNoErrors()
        ->assertSeeHtml(totalBillCard('Rp 32.000'));
});

it('does not change the recorded bill when manual income is updated', function () {
    $admin = User::factory()->admin()->create();
    $class = SchoolClass::factory()->create();
    $member = totalBillMember('2026-10-01');
    totalBillAttendance($member, '2026-10-10');
    totalBillAttendance($member, '2026-10-11');

    $income = app(CateringCultureMealIncomeService::class)->create([
        'income_date' => '2026-10-12',
        'school_class_id' => $class->id,
        'amount' => 50000,
        'note' => null,
    ], $admin);

    totalBillPage($admin)
        ->assertSeeHtml(totalBillCard('Rp 32.000'))
        ->call('editIncome', $income->id)
        ->set('amount', '75000')
        ->call('saveIncome')
        ->assertHasNoErrors()
        ->assertSeeHtml(totalBillCard('Rp 32.000'));
});

it('does not change the recorded bill when manual income is deleted', function () {
    $admin = User::factory()->admin()->create();
    $class = SchoolClass::factory()->create();
    $member = totalBillMember('2026-10-01');
    totalBillAttendance($member, '2026-10-10');
    totalBillAttendance($member, '2026-10-11');

    $income = app(CateringCultureMealIncomeService::class)->create([
        'income_date' => '2026-10-12',
        'school_class_id' => $class->id,
        'amount' => 50000,
        'note' => null,
    ], $admin);

    totalBillPage($admin)
        ->assertSeeHtml(totalBillCard('Rp 32.000'))
        ->call('confirmDeleteIncome', $income->id)
        ->call('deleteConfirmed')
        ->assertSeeHtml(totalBillCard('Rp 32.000'));
});

it('recalculates the recorded bill when the month or year changes', function () {
    $admin = User::factory()->admin()->create();
    $member = totalBillMember('2026-09-01');
    totalBillAttendance($member, '2026-09-01');
    totalBillAttendance($member, '2026-09-02');
    totalBillAttendance($member, '2026-09-03');
    totalBillAttendance($member, '2026-10-01');
    totalBillAttendance($member, '2026-10-02');

    $component = totalBillPage($admin)
        ->set('month', 9)
        ->assertSee('3 × Rp 16.000')
        ->assertSeeHtml(totalBillCard('Rp 48.000'));

    $component
        ->set('month', 10)
        ->assertSee('2 × Rp 16.000')
        ->assertSeeHtml(totalBillCard('Rp 32.000'));
});

it('formats the recorded bill as Indonesian Rupiah for a custom price', function () {
    app(CateringCultureMealSettingService::class)->updateCultureMealPrice(17500);

    $member = totalBillMember('2026-10-01');
    totalBillAttendance($member, '2026-10-10');
    totalBillAttendance($member, '2026-10-11');

    totalBillPage(User::factory()->admin()->create())
        ->assertSee('2 × Rp 17.500')
        ->assertSeeHtml(totalBillCard('Rp 35.000'));
});

it('never creates bill, payment, credit, or unit bill rows', function () {
    $admin = User::factory()->admin()->create();
    $member = totalBillMember('2026-10-01');
    totalBillAttendance($member, '2026-10-10');

    totalBillPage($admin)
        ->assertSee('Total Tagihan Tercatat')
        ->call('editCultureMealPrice')
        ->set('culture_meal_price', '18000')
        ->call('saveCultureMealPrice')
        ->assertHasNoErrors();

    expect(CateringBill::count())->toBe(0)
        ->and(CateringUnitBill::count())->toBe(0)
        ->and(CateringPayment::count())->toBe(0)
        ->and(CateringCredit::count())->toBe(0);
});
