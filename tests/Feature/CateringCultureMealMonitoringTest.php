<?php

use App\Enums\CateringAttendanceStatus;
use App\Livewire\CateringCultureMeal\Index as CultureMealIndex;
use App\Models\CateringAttendance;
use App\Models\CateringCategory;
use App\Models\CateringMember;
use App\Models\CateringMemberPeriod;
use App\Models\User;
use App\Services\CateringCultureMealMonitoringService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function cultureMealStudent(string $name = 'Siswa Budaya Makan'): CateringMember
{
    return CateringMember::factory()
        ->for(CateringCategory::factory()->student())
        ->create(['name' => $name]);
}

function cultureMealPeriod(CateringMember $member, string $start, ?string $end = null): CateringMemberPeriod
{
    return CateringMemberPeriod::create([
        'catering_member_id' => $member->id,
        'start_date' => $start,
        'end_date' => $end,
    ]);
}

function cultureMealAttendance(CateringMember $member, string $date, CateringAttendanceStatus $status = CateringAttendanceStatus::Alfa): CateringAttendance
{
    return CateringAttendance::factory()->for($member)->create([
        'attendance_date' => $date,
        'status' => $status,
    ]);
}

function cultureMealMonitor(int $month = 10, int $year = 2026): array
{
    return app(CateringCultureMealMonitoringService::class)->monitor($month, $year);
}

function cultureMealDailyCount(array $monitoring, string $date): ?int
{
    $day = $monitoring['days']->first(fn (array $day): bool => $day['date']->toDateString() === $date);

    return $day === null ? null : $day['count'];
}

function openCultureMeal(User $user, int $month = 10, int $year = 2026)
{
    return Livewire::actingAs($user)
        ->test(CultureMealIndex::class)
        ->set('month', $month)
        ->set('year', $year);
}

it('counts Budaya Makan up to and including the membership end date', function () {
    $member = cultureMealStudent();
    cultureMealPeriod($member, '2026-10-01', '2026-10-20');
    cultureMealAttendance($member, '2026-10-20');
    cultureMealAttendance($member, '2026-10-21');

    $monitoring = cultureMealMonitor(10, 2026);

    expect($monitoring['total'])->toBe(1)
        ->and(cultureMealDailyCount($monitoring, '2026-10-20'))->toBe(1)
        ->and(cultureMealDailyCount($monitoring, '2026-10-21'))->toBe(0);
});

it('ignores Budaya Makan recorded before the membership start date', function () {
    $member = cultureMealStudent();
    cultureMealPeriod($member, '2026-10-10');
    cultureMealAttendance($member, '2026-10-05');
    cultureMealAttendance($member, '2026-10-10');

    $monitoring = cultureMealMonitor(10, 2026);

    expect($monitoring['total'])->toBe(1)
        ->and(cultureMealDailyCount($monitoring, '2026-10-05'))->toBe(0)
        ->and(cultureMealDailyCount($monitoring, '2026-10-10'))->toBe(1);
});

it('counts Budaya Makan for an open membership period', function () {
    $member = cultureMealStudent();
    cultureMealPeriod($member, '2026-10-01', null);
    cultureMealAttendance($member, '2026-10-18');

    expect(cultureMealMonitor(10, 2026)['total'])->toBe(1);
});

it('counts only the internal alfa status as Budaya Makan', function () {
    $member = cultureMealStudent();
    cultureMealPeriod($member, '2026-10-01');
    $nonBudayaStatuses = [
        CateringAttendanceStatus::Ikut,
        CateringAttendanceStatus::Izin,
        CateringAttendanceStatus::Sakit,
        CateringAttendanceStatus::TidakIkut,
        CateringAttendanceStatus::Ujian,
        CateringAttendanceStatus::EventUnit,
        CateringAttendanceStatus::Puasa,
        CateringAttendanceStatus::Libur,
    ];

    foreach ($nonBudayaStatuses as $index => $status) {
        cultureMealAttendance($member, '2026-10-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT), $status);
    }

    cultureMealAttendance($member, '2026-10-20', CateringAttendanceStatus::Alfa);

    expect(cultureMealMonitor(10, 2026)['total'])->toBe(1);
});

it('keeps attendance outside membership coverage stored while ignoring it in monitoring', function () {
    $member = cultureMealStudent();
    cultureMealPeriod($member, '2026-10-01', '2026-10-20');
    cultureMealAttendance($member, '2026-10-20');
    cultureMealAttendance($member, '2026-10-21');

    $monitoring = cultureMealMonitor(10, 2026);

    expect($monitoring['total'])->toBe(1)
        ->and(CateringAttendance::query()
            ->where('catering_member_id', $member->id)
            ->whereDate('attendance_date', '2026-10-21')
            ->exists())->toBeTrue();
});

it('groups multiple Budaya Makan rows per date and sums the total from the daily counts', function () {
    $first = cultureMealStudent('Siswa Satu');
    $second = cultureMealStudent('Siswa Dua');
    cultureMealPeriod($first, '2026-10-01');
    cultureMealPeriod($second, '2026-10-01');
    cultureMealAttendance($first, '2026-10-12');
    cultureMealAttendance($second, '2026-10-12');
    cultureMealAttendance($second, '2026-10-13');

    $monitoring = cultureMealMonitor(10, 2026);

    expect(cultureMealDailyCount($monitoring, '2026-10-12'))->toBe(2)
        ->and(cultureMealDailyCount($monitoring, '2026-10-13'))->toBe(1)
        ->and($monitoring['total'])->toBe(3)
        ->and($monitoring['total'])->toBe($monitoring['days']->sum('count'));
});

it('includes every calendar date with a zero count when no attendance exists', function () {
    $monitoring = cultureMealMonitor(11, 2026);

    expect($monitoring['total'])->toBe(0)
        ->and($monitoring['days'])->toHaveCount(30)
        ->and(cultureMealDailyCount($monitoring, '2026-11-01'))->toBe(0)
        ->and(cultureMealDailyCount($monitoring, '2026-11-30'))->toBe(0)
        ->and($monitoring['days']->first()['date']->toDateString())->toBe('2026-11-01')
        ->and($monitoring['days']->last()['date']->toDateString())->toBe('2026-11-30');
});

it('handles month length and leap year without phantom dates', function (int $month, int $year, int $expectedDays) {
    $monitoring = cultureMealMonitor($month, $year);

    expect($monitoring['days'])->toHaveCount($expectedDays)
        ->and($monitoring['days']->last()['date']->toDateString())
        ->toBe(CarbonImmutable::create($year, $month, 1)->endOfMonth()->toDateString());
})->with([
    'february non-leap' => [2, 2026, 28],
    'february leap year' => [2, 2028, 29],
    'thirty day month' => [4, 2026, 30],
    'thirty one day month' => [1, 2026, 31],
]);

it('does not count legacy employee attendance as Budaya Makan', function () {
    $employee = CateringMember::factory()
        ->for(CateringCategory::factory()->employee())
        ->create();
    cultureMealPeriod($employee, '2026-10-01');
    cultureMealAttendance($employee, '2026-10-10');

    $student = cultureMealStudent();
    cultureMealPeriod($student, '2026-10-01');
    cultureMealAttendance($student, '2026-10-10');

    expect(cultureMealMonitor(10, 2026)['total'])->toBe(1);
});

it('renders the monitoring labels and daily breakdown for an administrator', function () {
    $member = cultureMealStudent();
    cultureMealPeriod($member, '2026-10-01');
    cultureMealAttendance($member, '2026-10-02');

    openCultureMeal(User::factory()->admin()->create())
        ->assertSee('Budaya Makan')
        ->assertSee('Data Tercatat')
        ->assertSee('02 Oktober 2026')
        ->assertSee('31 Oktober 2026')
        ->assertDontSee('Alfa');
});

it('shows the Budaya Makan navigation entry for an administrator', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('catering-culture-meal.index'))
        ->assertOk()
        ->assertSee('Budaya Makan');
});

it('reloads the monitoring data when the period changes', function () {
    $octoberMember = cultureMealStudent();
    cultureMealPeriod($octoberMember, '2026-10-01');
    cultureMealAttendance($octoberMember, '2026-10-10');
    cultureMealAttendance($octoberMember, '2026-10-11');

    $novemberMember = cultureMealStudent();
    cultureMealPeriod($novemberMember, '2026-11-01');
    cultureMealAttendance($novemberMember, '2026-11-10');

    openCultureMeal(User::factory()->admin()->create(), month: 10, year: 2026)
        ->assertSet('month', 10)
        ->assertSee('Oktober 2026')
        ->set('month', 11)
        ->assertSee('November 2026')
        ->assertSee('10 November 2026')
        ->assertDontSee('Oktober 2026');
});

it('keeps the Budaya Makan monitoring administrator only', function () {
    $this->get(route('catering-culture-meal.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->waliKelas()->create())
        ->get(route('catering-culture-meal.index'))
        ->assertForbidden();
});

it('forbids direct component access for non administrators', function () {
    Livewire::actingAs(User::factory()->waliKelas()->create())
        ->test(CultureMealIndex::class)
        ->assertForbidden();
});

it('keeps the monitoring query count bounded with many members and dates', function () {
    foreach (range(1, 20) as $index) {
        $member = cultureMealStudent('Siswa '.$index);
        cultureMealPeriod($member, '2026-10-01');

        foreach (range(1, 20) as $day) {
            cultureMealAttendance($member, '2026-10-'.str_pad((string) $day, 2, '0', STR_PAD_LEFT));
        }
    }

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $monitoring = cultureMealMonitor(10, 2026);

    expect($monitoring['total'])->toBe(400)
        ->and($queries)->toBeLessThanOrEqual(2);
});
