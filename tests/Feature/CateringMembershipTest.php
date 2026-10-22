<?php

use App\Enums\CateringAttendanceStatus;
use App\Models\CateringAttendance;
use App\Models\CateringMember;
use App\Models\CateringMemberPeriod;
use App\Services\CateringAttendanceInitializerService;
use App\Services\CateringMembershipService;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

it('allows inclusive end_date covering last day', function () {
    $member = CateringMember::factory()->create();
    $period = CateringMemberPeriod::create([
        'catering_member_id' => $member->id,
        'start_date' => '2026-10-01',
        'end_date' => CarbonImmutable::parse('2026-10-05'),
    ]);

    expect(app(CateringMembershipService::class)->isCovered($member->id, CarbonImmutable::parse('2026-10-05')))->toBeTrue()
        ->and(app(CateringMembershipService::class)->isCovered($member->id, CarbonImmutable::parse('2026-10-06')))->toBeFalse();
});

it('allows start_date equal to end_date', function () {
    $member = CateringMember::factory()->create();
    CateringMemberPeriod::create([
        'catering_member_id' => $member->id,
        'start_date' => CarbonImmutable::parse('2026-10-15'),
        'end_date' => CarbonImmutable::parse('2026-10-15'),
    ]);

    expect(app(CateringMembershipService::class)->isCovered($member->id, CarbonImmutable::parse('2026-10-15')))->toBeTrue()
        ->and(app(CateringMembershipService::class)->isCovered($member->id, CarbonImmutable::parse('2026-10-14')))->toBeFalse()
        ->and(app(CateringMembershipService::class)->isCovered($member->id, CarbonImmutable::parse('2026-10-16')))->toBeFalse();
});

it('rejects overlapping periods for same member', function () {
    $member = CateringMember::factory()->create();
    CateringMemberPeriod::create([
        'catering_member_id' => $member->id,
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-10',
    ]);

    expect(fn () => app(CateringMembershipService::class)->validateMembershipPeriods(
        $member->id,
        CarbonImmutable::parse('2026-10-05'),
        CarbonImmutable::parse('2026-10-20'),
    ))->toThrow(InvalidArgumentException::class);
});

it('rejects creating second open ended period for same member', function () {
    $member = CateringMember::factory()->create();
    CateringMemberPeriod::create([
        'catering_member_id' => $member->id,
        'start_date' => '2026-10-01',
        'end_date' => null,
    ]);

    expect(fn () => app(CateringMembershipService::class)->validateMembershipPeriods($member->id, CarbonImmutable::parse('2026-11-01'), null))->toThrow(InvalidArgumentException::class);
});

it('rejects rejoining while an open membership period still exists', function () {
    $member = CateringMember::factory()->create();
    CateringMemberPeriod::query()->create([
        'catering_member_id' => $member->id,
        'start_date' => '2026-10-01',
        'end_date' => null,
    ]);

    expect(fn () => app(CateringMembershipService::class)->rejoin(
        $member->id,
        CarbonImmutable::parse('2026-11-01'),
    ))->toThrow(InvalidArgumentException::class, 'masih memiliki periode keikutsertaan');

    expect($member->periods()->count())->toBe(1);
});

it('initializer generates rows only for covered dates and preserves stored attendance outside period', function () {
    $member = CateringMember::factory()->create();
    CateringMemberPeriod::create([
        'catering_member_id' => $member->id,
        'start_date' => CarbonImmutable::parse('2026-10-05'),
        'end_date' => '2026-10-07',
    ]);
    CateringAttendance::factory()->for($member)->create([
        'attendance_date' => '2026-10-04',
        'status' => CateringAttendanceStatus::Ikut,
    ]);

    CateringAttendanceInitializerService::ensureMonth(2026, 10);
    $att = $member->attendances()->get()->pluck('attendance_date')->map->toDateString();

    expect($att->contains('2026-10-04'))->toBeTrue()
        ->and($att->contains('2026-10-05'))->toBeTrue()
        ->and($att->contains('2026-10-07'))->toBeTrue()
        ->and($att->contains('2026-10-08'))->toBeFalse();
});

it('applies start stop and rejoin dates to attendance eligibility without deleting history', function () {
    $member = CateringMember::factory()->create();
    $membership = app(CateringMembershipService::class);

    $membership->start($member->id, CarbonImmutable::parse('2026-10-20'));
    CateringAttendanceInitializerService::ensureMonth(2026, 10);

    $octoberDates = $member->attendances()
        ->whereDate('attendance_date', '>=', '2026-10-01')
        ->whereDate('attendance_date', '<=', '2026-10-31')
        ->orderBy('attendance_date')
        ->get(['attendance_date'])
        ->pluck('attendance_date')
        ->map->toDateString();

    expect($octoberDates)->toHaveCount(12)
        ->and($octoberDates->first())->toBe('2026-10-20')
        ->and($octoberDates->last())->toBe('2026-10-31');

    $membership->stop($member->id, CarbonImmutable::parse('2026-10-25'));

    expect($membership->isCovered($member->id, CarbonImmutable::parse('2026-10-20')))->toBeTrue()
        ->and($membership->isCovered($member->id, CarbonImmutable::parse('2026-10-25')))->toBeTrue()
        ->and($membership->isCovered($member->id, CarbonImmutable::parse('2026-10-26')))->toBeFalse()
        ->and($member->attendances()->whereMonth('attendance_date', 10)->count())->toBe(12);

    $membership->rejoin($member->id, CarbonImmutable::parse('2026-11-17'));
    CateringAttendanceInitializerService::ensureMonth(2026, 11);

    $novemberDates = $member->attendances()
        ->whereDate('attendance_date', '>=', '2026-11-01')
        ->whereDate('attendance_date', '<=', '2026-11-30')
        ->orderBy('attendance_date')
        ->get(['attendance_date'])
        ->pluck('attendance_date')
        ->map->toDateString();

    expect($novemberDates)->toHaveCount(14)
        ->and($novemberDates->first())->toBe('2026-11-17')
        ->and($novemberDates->last())->toBe('2026-11-30')
        ->and($membership->isCovered($member->id, CarbonImmutable::parse('2026-11-16')))->toBeFalse()
        ->and($membership->isCovered($member->id, CarbonImmutable::parse('2026-11-17')))->toBeTrue()
        ->and($member->periods()->count())->toBe(2);
});
