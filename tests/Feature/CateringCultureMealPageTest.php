<?php

use App\Enums\CateringAttendanceStatus;
use App\Livewire\CateringCultureMeal\Index as CultureMealIndex;
use App\Models\CateringAttendance;
use App\Models\CateringCategory;
use App\Models\CateringCultureMealIncome;
use App\Models\CateringMember;
use App\Models\CateringMemberPeriod;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\CateringCultureMealIncomeService;
use App\Services\CateringCultureMealMonitoringService;
use Livewire\Livewire;

function pageCultureStudent(): CateringMember
{
    return CateringMember::factory()
        ->for(CateringCategory::factory()->student())
        ->create();
}

function pageCultureCovered(CateringMember $member, string $start = '2026-09-01'): CateringMemberPeriod
{
    return CateringMemberPeriod::create([
        'catering_member_id' => $member->id,
        'start_date' => $start,
        'end_date' => null,
    ]);
}

function pageCultureAlfa(CateringMember $member, string $date): CateringAttendance
{
    return CateringAttendance::factory()->for($member)->create([
        'attendance_date' => $date,
        'status' => CateringAttendanceStatus::Alfa,
    ]);
}

function pageCulturePage(User $user, int $month = 10, int $year = 2026)
{
    return Livewire::actingAs($user)
        ->test(CultureMealIndex::class)
        ->set('month', $month)
        ->set('year', $year);
}

function pageCultureIncome(User $actor, string $date, int $classId, int $amount, ?string $note = null): CateringCultureMealIncome
{
    return app(CateringCultureMealIncomeService::class)->create([
        'income_date' => $date,
        'school_class_id' => $classId,
        'amount' => $amount,
        'note' => $note,
    ], $actor);
}

it('renders both summary cards and the shared period filter', function () {
    pageCulturePage(User::factory()->admin()->create())
        ->assertSee('Budaya Makan')
        ->assertSee('Data Tercatat')
        ->assertSee('Pemasukan Manual')
        ->assertSee('Bulan')
        ->assertSee('Tahun')
        ->assertSee('Oktober 2026');
});

it('drives both sections from the same selected month and year', function () {
    $admin = User::factory()->admin()->create();
    $class = SchoolClass::factory()->create(['name' => 'VII A']);
    $member = pageCultureStudent();
    pageCultureCovered($member);
    pageCultureAlfa($member, '2026-09-10');
    pageCultureAlfa($member, '2026-10-10');
    pageCultureAlfa($member, '2026-10-11');

    pageCultureIncome($admin, '2026-09-15', $class->id, 11000, 'Pemasukan September');
    pageCultureIncome($admin, '2026-10-15', $class->id, 22000, 'Pemasukan Oktober');

    $component = pageCulturePage($admin)
        ->set('month', 9)
        ->assertSee('Pemasukan September')
        ->assertDontSee('Pemasukan Oktober')
        ->assertSeeHtml('font-bold">Rp 11.000</p>')
        ->assertSeeHtml('font-bold">1</p>');

    $component
        ->set('month', 10)
        ->assertSee('Pemasukan Oktober')
        ->assertDontSee('Pemasukan September')
        ->assertSeeHtml('font-bold">Rp 22.000</p>')
        ->assertSeeHtml('font-bold">2</p>');
});

it('renders the correct Data Tercatat total from membership-covered attendance only', function () {
    $admin = User::factory()->admin()->create();
    $member = pageCultureStudent();
    pageCultureCovered($member, '2026-10-01');
    pageCultureAlfa($member, '2026-10-05');
    pageCultureAlfa($member, '2026-10-20');

    pageCultureAlfa(pageCultureStudent(), '2026-10-05');

    $monitoring = app(CateringCultureMealMonitoringService::class)->monitor(10, 2026);

    expect($monitoring['total'])->toBe(2);

    pageCulturePage($admin)
        ->assertSeeHtml('font-bold">2</p>')
        ->assertSee('05 Oktober 2026')
        ->assertSee('20 Oktober 2026');
});

it('renders the correct Pemasukan Manual total for the period', function () {
    $admin = User::factory()->admin()->create();
    $class = SchoolClass::factory()->create();

    pageCultureIncome($admin, '2026-10-01', $class->id, 16000);
    pageCultureIncome($admin, '2026-10-20', $class->id, 4000);

    pageCulturePage($admin)
        ->assertSeeHtml('font-bold">Rp 20.000</p>');
});

it('renders every calendar date including zero-count days', function () {
    pageCulturePage(User::factory()->admin()->create())
        ->assertSee('01 Oktober 2026')
        ->assertSee('15 Oktober 2026')
        ->assertSee('31 Oktober 2026')
        ->assertSee('31 tanggal');
});

it('shows the manual income empty state and zero total', function () {
    pageCulturePage(User::factory()->admin()->create())
        ->assertSee('Belum ada pemasukan manual pada periode ini.')
        ->assertSeeHtml('font-bold">Rp 0</p>');
});

it('refreshes the list and total after creating manual income', function () {
    $admin = User::factory()->admin()->create();
    $class = SchoolClass::factory()->create(['name' => 'VIII B']);

    pageCulturePage($admin)
        ->assertSee('Belum ada pemasukan manual pada periode ini.')
        ->call('createIncome')
        ->set('income_date', '2026-10-12')
        ->set('school_class_id', (string) $class->id)
        ->set('amount', '16000')
        ->set('note', 'Iuran budaya makan')
        ->call('saveIncome')
        ->assertHasNoErrors()
        ->assertSee('Iuran budaya makan')
        ->assertSee('VIII B')
        ->assertSee('Rp 16.000')
        ->assertDontSee('Belum ada pemasukan manual pada periode ini.');
});

it('refreshes the list and total after editing manual income', function () {
    $admin = User::factory()->admin()->create();
    $class = SchoolClass::factory()->create(['name' => 'IX C']);
    $income = pageCultureIncome($admin, '2026-10-12', $class->id, 16000);

    pageCulturePage($admin)
        ->call('editIncome', $income->id)
        ->assertSet('amount', '16000')
        ->assertSet('school_class_id', (string) $class->id)
        ->set('amount', '25000')
        ->call('saveIncome')
        ->assertHasNoErrors()
        ->assertSee('Rp 25.000')
        ->assertDontSeeHtml('font-semibold text-slate-900">Rp 16.000');
});

it('refreshes the list and total after deleting manual income', function () {
    $admin = User::factory()->admin()->create();
    $class = SchoolClass::factory()->create();
    $income = pageCultureIncome($admin, '2026-10-12', $class->id, 16000);

    pageCulturePage($admin)
        ->call('confirmDeleteIncome', $income->id)
        ->assertSee('Hapus Pemasukan?')
        ->call('deleteConfirmed')
        ->assertSee('Belum ada pemasukan manual pada periode ini.')
        ->assertSeeHtml('font-bold">Rp 0</p>');
});

it('never changes Data Tercatat when manual income is created, edited, or deleted', function () {
    $admin = User::factory()->admin()->create();
    $class = SchoolClass::factory()->create();
    $member = pageCultureStudent();
    pageCultureCovered($member);
    pageCultureAlfa($member, '2026-10-10');

    $monitoring = app(CateringCultureMealMonitoringService::class);
    $before = $monitoring->monitor(10, 2026)['total'];

    $component = pageCulturePage($admin)
        ->call('createIncome')
        ->set('income_date', '2026-10-12')
        ->set('school_class_id', (string) $class->id)
        ->set('amount', '16000')
        ->call('saveIncome')
        ->assertHasNoErrors();

    $income = CateringCultureMealIncome::sole();

    $component
        ->call('editIncome', $income->id)
        ->set('amount', '30000')
        ->call('saveIncome')
        ->assertHasNoErrors();

    $component
        ->call('confirmDeleteIncome', $income->id)
        ->call('deleteConfirmed');

    expect($before)->toBe(1)
        ->and($monitoring->monitor(10, 2026)['total'])->toBe($before)
        ->and(CateringAttendance::count())->toBe(1);
});

it('never shows the internal alfa label as user-facing text', function () {
    $admin = User::factory()->admin()->create();
    $member = pageCultureStudent();
    pageCultureCovered($member);
    pageCultureAlfa($member, '2026-10-10');

    pageCulturePage($admin)
        ->assertSee('Budaya Makan')
        ->assertDontSee('Alfa')
        ->assertDontSee('alfa');
});

it('keeps the Budaya Makan page administrator only', function () {
    $this->get(route('catering-culture-meal.index'))->assertRedirect(route('login'));

    $waliKelas = User::factory()->waliKelas()->create();

    $this->actingAs($waliKelas)
        ->get(route('catering-culture-meal.index'))
        ->assertForbidden();

    Livewire::actingAs($waliKelas)
        ->test(CultureMealIndex::class)
        ->assertForbidden();
});
