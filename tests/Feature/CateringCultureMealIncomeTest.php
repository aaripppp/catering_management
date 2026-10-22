<?php

use App\Enums\CateringAttendanceStatus;
use App\Livewire\CateringCultureMeal\Index as CultureMealIndex;
use App\Models\CateringAttendance;
use App\Models\CateringBill;
use App\Models\CateringCategory;
use App\Models\CateringCredit;
use App\Models\CateringCultureMealIncome;
use App\Models\CateringMember;
use App\Models\CateringMemberPeriod;
use App\Models\CateringPayment;
use App\Models\CateringUnitBill;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\CateringCultureMealIncomeService;
use App\Services\CateringCultureMealMonitoringService;
use App\Services\CateringCultureMealSettingService;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

function incomeTestClass(string $name = 'VII A'): SchoolClass
{
    return SchoolClass::factory()->create(['name' => $name]);
}

function incomeService(): CateringCultureMealIncomeService
{
    return app(CateringCultureMealIncomeService::class);
}

function openCultureMealIncome(User $user, int $month = 10, int $year = 2026)
{
    return Livewire::actingAs($user)
        ->test(CultureMealIndex::class)
        ->set('month', $month)
        ->set('year', $year);
}

/**
 * @param  array{income_date: string, school_class_id: int, amount: int, note?: string|null}  $data
 */
function createManualIncome(User $actor, array $data): CateringCultureMealIncome
{
    return incomeService()->create($data, $actor);
}

it('creates a manual income entry with a class snapshot and creator', function () {
    $admin = User::factory()->admin()->create();
    $class = incomeTestClass('VII A');

    openCultureMealIncome($admin)
        ->call('createIncome')
        ->set('income_date', '2026-10-10')
        ->set('school_class_id', (string) $class->id)
        ->set('amount', '16000')
        ->set('note', 'Budaya makan kelas')
        ->call('saveIncome')
        ->assertHasNoErrors()
        ->assertSet('showFormModal', false);

    $income = CateringCultureMealIncome::sole();

    expect($income->income_date->toDateString())->toBe('2026-10-10')
        ->and($income->school_class_id)->toBe($class->id)
        ->and($income->school_class_name)->toBe('VII A')
        ->and($income->amount)->toBe(16000)
        ->and($income->note)->toBe('Budaya makan kelas')
        ->and($income->created_by)->toBe($admin->id)
        ->and(incomeService()->monthlyTotal(10, 2026))->toBe(16000);
});

it('allows multiple entries for the same date and class', function () {
    $admin = User::factory()->admin()->create();
    $class = incomeTestClass();

    foreach ([16000, 32000] as $amount) {
        openCultureMealIncome($admin)
            ->call('createIncome')
            ->set('income_date', '2026-10-10')
            ->set('school_class_id', (string) $class->id)
            ->set('amount', (string) $amount)
            ->call('saveIncome')
            ->assertHasNoErrors();
    }

    expect(CateringCultureMealIncome::count())->toBe(2)
        ->and(incomeService()->monthlyTotal(10, 2026))->toBe(48000);
});

it('updates a manual income entry and refreshes the class snapshot', function () {
    $admin = User::factory()->admin()->create();
    $oldClass = incomeTestClass('VII A');
    $newClass = incomeTestClass('VIII B');
    $income = createManualIncome($admin, [
        'income_date' => '2026-10-10',
        'school_class_id' => $oldClass->id,
        'amount' => 16000,
        'note' => null,
    ]);

    openCultureMealIncome($admin)
        ->call('editIncome', $income->id)
        ->assertSet('income_date', '2026-10-10')
        ->assertSet('amount', '16000')
        ->set('income_date', '2026-10-15')
        ->set('school_class_id', (string) $newClass->id)
        ->set('amount', '25000')
        ->set('note', 'Revisi')
        ->call('saveIncome')
        ->assertHasNoErrors();

    $income->refresh();

    expect($income->income_date->toDateString())->toBe('2026-10-15')
        ->and($income->school_class_id)->toBe($newClass->id)
        ->and($income->school_class_name)->toBe('VIII B')
        ->and($income->amount)->toBe(25000)
        ->and($income->note)->toBe('Revisi')
        ->and(incomeService()->monthlyTotal(10, 2026))->toBe(25000);
});

it('deletes a manual income entry only after confirmation', function () {
    $admin = User::factory()->admin()->create();
    $class = incomeTestClass();
    $income = createManualIncome($admin, [
        'income_date' => '2026-10-10',
        'school_class_id' => $class->id,
        'amount' => 16000,
        'note' => null,
    ]);

    openCultureMealIncome($admin)
        ->call('confirmDeleteIncome', $income->id)
        ->assertSet('showDeleteModal', true)
        ->call('deleteConfirmed')
        ->assertSet('showDeleteModal', false);

    expect(CateringCultureMealIncome::count())->toBe(0)
        ->and(incomeService()->monthlyTotal(10, 2026))->toBe(0);
});

it('keeps income history readable after the class is deleted', function () {
    $admin = User::factory()->admin()->create();
    $class = incomeTestClass('VII A');
    $income = createManualIncome($admin, [
        'income_date' => '2026-10-10',
        'school_class_id' => $class->id,
        'amount' => 16000,
        'note' => null,
    ]);

    $class->delete();
    $income->refresh();

    expect($income->school_class_id)->toBeNull()
        ->and($income->school_class_name)->toBe('VII A')
        ->and(CateringCultureMealIncome::query()->whereKey($income->id)->exists())->toBeTrue();
});

it('keeps income history when the creator account is deleted', function () {
    $admin = User::factory()->admin()->create();
    $class = incomeTestClass();
    $income = createManualIncome($admin, [
        'income_date' => '2026-10-10',
        'school_class_id' => $class->id,
        'amount' => 16000,
        'note' => null,
    ]);

    $admin->delete();
    $income->refresh();

    expect($income->created_by)->toBeNull()
        ->and(CateringCultureMealIncome::query()->whereKey($income->id)->exists())->toBeTrue();
});

it('filters manual income by the selected month', function () {
    $admin = User::factory()->admin()->create();
    $class = incomeTestClass();

    foreach (['2026-09-10' => 10000, '2026-10-10' => 16000, '2026-11-10' => 20000] as $date => $amount) {
        createManualIncome($admin, [
            'income_date' => $date,
            'school_class_id' => $class->id,
            'amount' => $amount,
            'note' => null,
        ]);
    }

    openCultureMealIncome($admin, 10, 2026)
        ->assertSee('Rp 16.000')
        ->assertSee('10 Oktober 2026')
        ->assertDontSee('10 September 2026')
        ->assertDontSee('10 November 2026');

    expect(incomeService()->monthlyTotal(10, 2026))->toBe(16000)
        ->and(incomeService()->monthlyRows(10, 2026))->toHaveCount(1);
});

it('rejects a manual income without a class and with a zero amount', function () {
    $admin = User::factory()->admin()->create();

    openCultureMealIncome($admin)
        ->call('createIncome')
        ->set('income_date', '2026-10-10')
        ->set('school_class_id', '')
        ->set('amount', '0')
        ->call('saveIncome')
        ->assertHasErrors(['school_class_id', 'amount']);

    expect(CateringCultureMealIncome::count())->toBe(0);
});

it('keeps manual income administrator only', function () {
    $this->get(route('catering-culture-meal.index'))->assertRedirect(route('login'));

    $waliKelas = User::factory()->waliKelas()->create();

    $this->actingAs($waliKelas)
        ->get(route('catering-culture-meal.index'))
        ->assertForbidden();

    Livewire::actingAs($waliKelas)
        ->test(CultureMealIndex::class)
        ->assertForbidden();

    $this->actingAs($waliKelas);

    expect(Gate::denies('createCateringCultureMeal'))->toBeTrue()
        ->and(Gate::denies('updateCateringCultureMeal'))->toBeTrue()
        ->and(Gate::denies('deleteCateringCultureMeal'))->toBeTrue();
});

it('does not touch attendance or billing records when managing manual income', function () {
    $admin = User::factory()->admin()->create();
    $class = incomeTestClass();

    CateringAttendance::factory()->create();
    CateringBill::factory()->create();
    CateringUnitBill::factory()->create();
    CateringPayment::factory()->create();
    CateringCredit::factory()->create();

    $before = [
        'attendance' => CateringAttendance::count(),
        'bills' => CateringBill::count(),
        'unit_bills' => CateringUnitBill::count(),
        'payments' => CateringPayment::count(),
        'credits' => CateringCredit::count(),
    ];

    $component = openCultureMealIncome($admin)
        ->call('createIncome')
        ->set('income_date', '2026-10-10')
        ->set('school_class_id', (string) $class->id)
        ->set('amount', '16000')
        ->call('saveIncome')
        ->assertHasNoErrors();

    $income = CateringCultureMealIncome::sole();

    $component
        ->call('confirmDeleteIncome', $income->id)
        ->call('deleteConfirmed');

    expect([
        'attendance' => CateringAttendance::count(),
        'bills' => CateringBill::count(),
        'unit_bills' => CateringUnitBill::count(),
        'payments' => CateringPayment::count(),
        'credits' => CateringCredit::count(),
    ])->toBe($before);
});

it('does not change the Data Tercatat count when managing manual income', function () {
    $admin = User::factory()->admin()->create();
    $class = incomeTestClass();

    $member = CateringMember::factory()
        ->for(CateringCategory::factory()->student())
        ->create();
    CateringMemberPeriod::create([
        'catering_member_id' => $member->id,
        'start_date' => '2026-10-01',
        'end_date' => null,
    ]);
    CateringAttendance::factory()->for($member)->create([
        'attendance_date' => '2026-10-10',
        'status' => CateringAttendanceStatus::Alfa,
    ]);

    $monitoring = app(CateringCultureMealMonitoringService::class);
    $before = $monitoring->monitor(10, 2026)['total'];

    $component = openCultureMealIncome($admin)
        ->call('createIncome')
        ->set('income_date', '2026-10-10')
        ->set('school_class_id', (string) $class->id)
        ->set('amount', '16000')
        ->call('saveIncome')
        ->assertHasNoErrors();

    $income = CateringCultureMealIncome::sole();
    $component->call('confirmDeleteIncome', $income->id)->call('deleteConfirmed');

    expect($before)->toBe(1)
        ->and($monitoring->monitor(10, 2026)['total'])->toBe($before)
        ->and(CateringAttendance::count())->toBe(1);
});

it('renders the manual income section, columns, and empty state', function () {
    openCultureMealIncome(User::factory()->admin()->create())
        ->assertSee('Budaya Makan')
        ->assertSee('Data Tercatat')
        ->assertSee('Pemasukan Manual')
        ->assertSee('Tanggal')
        ->assertSee('Kelas')
        ->assertSee('Nominal')
        ->assertSee('Catatan')
        ->assertSee('Aksi')
        ->assertSee('Belum ada pemasukan manual pada periode ini.');
});

it('displays the nominal as formatted rupiah while storing an integer', function () {
    $admin = User::factory()->admin()->create();
    $class = incomeTestClass();

    $income = createManualIncome($admin, [
        'income_date' => '2026-10-10',
        'school_class_id' => $class->id,
        'amount' => 150000,
        'note' => 'Nominal besar',
    ]);

    openCultureMealIncome($admin)
        ->assertSee('Rp 150.000')
        ->assertSee('Nominal besar');

    expect($income->amount)->toBe(150000)
        ->and(is_int($income->amount))->toBeTrue();
});

it('renders the nominal input with an unrestricted integer step', function () {
    openCultureMealIncome(User::factory()->admin()->create())
        ->call('createIncome')
        ->assertSeeHtml('id="income-amount" type="number" inputmode="numeric" min="1" step="1"')
        ->assertDontSeeHtml('id="income-amount" type="number" inputmode="numeric" min="1" step="1000"');
});

it('creates a manual income entry with a non-round nominal', function () {
    $admin = User::factory()->admin()->create();
    $class = incomeTestClass('VII D');

    openCultureMealIncome($admin)
        ->call('createIncome')
        ->set('income_date', '2026-10-21')
        ->set('school_class_id', (string) $class->id)
        ->set('amount', '240000')
        ->set('note', 'test')
        ->call('saveIncome')
        ->assertHasNoErrors()
        ->assertSet('showFormModal', false);

    $income = CateringCultureMealIncome::sole();

    expect($income->income_date->toDateString())->toBe('2026-10-21')
        ->and($income->school_class_id)->toBe($class->id)
        ->and($income->school_class_name)->toBe('VII D')
        ->and($income->amount)->toBe(240000)
        ->and($income->note)->toBe('test')
        ->and($income->created_by)->toBe($admin->id);
});

it('refreshes the manual income total and table after saving a non-round nominal', function () {
    $admin = User::factory()->admin()->create();
    $class = incomeTestClass('VII D');

    openCultureMealIncome($admin)
        ->call('createIncome')
        ->set('income_date', '2026-10-21')
        ->set('school_class_id', (string) $class->id)
        ->set('amount', '240000')
        ->set('note', 'Nominal besar')
        ->call('saveIncome')
        ->assertHasNoErrors()
        ->assertSeeHtml('font-bold">Rp 240.000</p>')
        ->assertSeeHtml('font-semibold text-slate-900">Rp 240.000')
        ->assertSee('VII D')
        ->assertSee('Nominal besar')
        ->assertDontSee('Belum ada pemasukan manual pada periode ini.');
});

it('accepts arbitrary positive integer nominal values', function (string $value) {
    $admin = User::factory()->admin()->create();
    $class = incomeTestClass();

    openCultureMealIncome($admin)
        ->call('createIncome')
        ->set('income_date', '2026-10-21')
        ->set('school_class_id', (string) $class->id)
        ->set('amount', $value)
        ->call('saveIncome')
        ->assertHasNoErrors();

    expect(CateringCultureMealIncome::sole()->amount)->toBe((int) $value);
})->with(['1', '16000', '240000', '240500']);

it('rejects zero, negative, decimal, and non-numeric nominal values', function (string $value) {
    $admin = User::factory()->admin()->create();
    $class = incomeTestClass();

    openCultureMealIncome($admin)
        ->call('createIncome')
        ->set('income_date', '2026-10-21')
        ->set('school_class_id', (string) $class->id)
        ->set('amount', $value)
        ->call('saveIncome')
        ->assertHasErrors(['amount']);

    expect(CateringCultureMealIncome::count())->toBe(0);
})->with(['0', '-1000', '12.5', 'abc']);

it('keeps Data Tercatat, price, and financial records unchanged after a non-round manual income', function () {
    $admin = User::factory()->admin()->create();
    $class = incomeTestClass();
    $member = CateringMember::factory()
        ->for(CateringCategory::factory()->student())
        ->create();
    CateringMemberPeriod::create([
        'catering_member_id' => $member->id,
        'start_date' => '2026-10-01',
        'end_date' => null,
    ]);
    CateringAttendance::factory()->for($member)->create([
        'attendance_date' => '2026-10-10',
        'status' => CateringAttendanceStatus::Alfa,
    ]);

    $monitoring = app(CateringCultureMealMonitoringService::class);
    $setting = app(CateringCultureMealSettingService::class);

    openCultureMealIncome($admin)
        ->call('createIncome')
        ->set('income_date', '2026-10-21')
        ->set('school_class_id', (string) $class->id)
        ->set('amount', '240000')
        ->call('saveIncome')
        ->assertHasNoErrors();

    expect($monitoring->monitor(10, 2026)['total'])->toBe(1)
        ->and(CateringAttendance::count())->toBe(1)
        ->and($setting->getCultureMealPrice())->toBe(16000)
        ->and(CateringBill::count())->toBe(0)
        ->and(CateringUnitBill::count())->toBe(0)
        ->and(CateringPayment::count())->toBe(0)
        ->and(CateringCredit::count())->toBe(0);
});
