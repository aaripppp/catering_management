<?php

use App\Enums\CateringAttendanceStatus;
use App\Models\CateringAttendance;
use App\Models\CateringMember;
use Illuminate\Database\QueryException;

it('defines the tidak ikut attendance status', function () {
    expect(CateringAttendanceStatus::TidakIkut->value)->toBe('tidak_ikut')
        ->and(CateringAttendanceStatus::TidakIkut->label())->toBe('Tidak Ikut')
        ->and(CateringAttendanceStatus::TidakIkut->shorthand())->toBe('T');
});

it('creates attendance with typed status and date for a catering member', function () {
    $member = CateringMember::factory()->create();
    $attendance = CateringAttendance::factory()->for($member)->create([
        'attendance_date' => '2026-09-01',
        'status' => CateringAttendanceStatus::Ikut,
    ]);

    expect($attendance->status)->toBe(CateringAttendanceStatus::Ikut)
        ->and($attendance->attendance_date->toDateString())->toBe('2026-09-01')
        ->and($attendance->cateringMember->is($member))->toBeTrue();

    $this->assertDatabaseHas('catering_attendances', [
        'catering_member_id' => $member->id,
        'status' => 'ikut',
    ]);

    expect(CateringAttendance::query()->whereDate('attendance_date', '2026-09-01')->exists())->toBeTrue();
});

it('exposes attendances through the catering member relationship', function () {
    $member = CateringMember::factory()->create();
    CateringAttendance::factory()->for($member)->count(2)->sequence(
        ['attendance_date' => '2026-09-01'],
        ['attendance_date' => '2026-09-02'],
    )->create();

    expect($member->attendances)->toHaveCount(2)
        ->and($member->attendances->every(fn (CateringAttendance $attendance): bool => $attendance->cateringMember->is($member)))->toBeTrue();
});

it('rejects duplicate attendance for the same member and date', function () {
    $member = CateringMember::factory()->create();
    CateringAttendance::factory()->for($member)->create([
        'attendance_date' => '2026-09-01',
    ]);

    expect(fn () => CateringAttendance::factory()->for($member)->create([
        'attendance_date' => '2026-09-01',
    ]))->toThrow(QueryException::class);

    expect(CateringAttendance::query()->count())->toBe(1);
});

it('allows the same member to have attendance on different dates', function () {
    $member = CateringMember::factory()->create();

    CateringAttendance::factory()->for($member)->create(['attendance_date' => '2026-09-01']);
    CateringAttendance::factory()->for($member)->create(['attendance_date' => '2026-09-02']);

    expect($member->attendances()->count())->toBe(2);
});

it('allows different members to have attendance on the same date', function () {
    $firstMember = CateringMember::factory()->create();
    $secondMember = CateringMember::factory()->create();

    CateringAttendance::factory()->for($firstMember)->create(['attendance_date' => '2026-09-01']);
    CateringAttendance::factory()->for($secondMember)->create(['attendance_date' => '2026-09-01']);

    expect(CateringAttendance::query()->whereDate('attendance_date', '2026-09-01')->count())->toBe(2);
});

it('preserves attendance history by restricting member deletion', function () {
    $member = CateringMember::factory()->create();
    $attendance = CateringAttendance::factory()->for($member)->create();

    expect(fn () => $member->delete())->toThrow(QueryException::class)
        ->and($member->fresh())->not->toBeNull()
        ->and($attendance->fresh())->not->toBeNull();
});
