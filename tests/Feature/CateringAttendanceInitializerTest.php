<?php

use App\Enums\CateringAttendanceStatus;
use App\Models\CateringAttendance;
use App\Models\CateringCategory;
use App\Models\CateringMember;
use App\Models\SchoolClass;
use App\Services\CateringAttendanceInitializerService;
use Illuminate\Support\Facades\DB;

function initializerStoredStatus(CateringMember $member, string $date): ?CateringAttendanceStatus
{
    return CateringAttendance::query()
        ->where('catering_member_id', $member->id)
        ->get()
        ->first(fn (CateringAttendance $attendance): bool => $attendance->attendance_date->isSameDay($date))
        ?->status;
}

it('initialises one date globally for every active student and employee', function () {
    $firstClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $secondClass = SchoolClass::factory()->create(['name' => '8A', 'level' => '8']);
    $studentCategory = CateringCategory::factory()->student()->create();
    $employeeCategory = CateringCategory::factory()->employee()->create();

    $firstStudent = CateringMember::factory()->create([
        'school_class_id' => $firstClass->id,
        'catering_category_id' => $studentCategory->id,
    ]);
    $secondStudent = CateringMember::factory()->create([
        'school_class_id' => $secondClass->id,
        'catering_category_id' => $studentCategory->id,
    ]);
    $employee = CateringMember::factory()->withoutClass()->create([
        'catering_category_id' => $employeeCategory->id,
    ]);
    $inactive = CateringMember::factory()->inactive()->create([
        'school_class_id' => $firstClass->id,
        'catering_category_id' => $studentCategory->id,
    ]);

    CateringAttendanceInitializerService::ensureDate('2026-09-30');

    expect(initializerStoredStatus($firstStudent, '2026-09-30'))->toBe(CateringAttendanceStatus::Ikut)
        ->and(initializerStoredStatus($secondStudent, '2026-09-30'))->toBe(CateringAttendanceStatus::Ikut)
        ->and(initializerStoredStatus($employee, '2026-09-30'))->toBe(CateringAttendanceStatus::Ikut)
        ->and(initializerStoredStatus($inactive, '2026-09-30'))->toBeNull()
        ->and(CateringAttendance::query()->count())->toBe(3);
});

it('uses libur on weekends and ikut on weekdays', function () {
    $member = CateringMember::factory()->create();

    CateringAttendanceInitializerService::ensureDate('2026-09-05');
    CateringAttendanceInitializerService::ensureDate('2026-09-07');

    expect(initializerStoredStatus($member, '2026-09-05'))->toBe(CateringAttendanceStatus::Libur)
        ->and(initializerStoredStatus($member, '2026-09-07'))->toBe(CateringAttendanceStatus::Ikut);
});

it('preserves every existing attendance status', function (CateringAttendanceStatus $status) {
    $member = CateringMember::factory()->create();
    CateringAttendance::factory()->for($member)->create([
        'attendance_date' => '2026-09-30',
        'status' => $status,
    ]);

    CateringAttendanceInitializerService::ensureDate('2026-09-30');

    expect(initializerStoredStatus($member, '2026-09-30'))->toBe($status)
        ->and(CateringAttendance::query()->count())->toBe(1);
})->with(CateringAttendanceStatus::cases());

it('initialises complete calendar months including leap February', function () {
    $member = CateringMember::factory()->create();

    CateringAttendanceInitializerService::ensureMonth(2028, 2);

    expect($member->attendances()->count())->toBe(29)
        ->and(initializerStoredStatus($member, '2028-02-29'))->toBe(CateringAttendanceStatus::Ikut);
});

it('is idempotent and fills a new active member on a later run', function () {
    $existingMember = CateringMember::factory()->create();

    CateringAttendanceInitializerService::ensureMonth(2026, 9);
    CateringAttendanceInitializerService::ensureMonth(2026, 9);

    $newMember = CateringMember::factory()->create();
    CateringAttendanceInitializerService::ensureMonth(2026, 9);

    expect($existingMember->attendances()->count())->toBe(30)
        ->and($newMember->attendances()->count())->toBe(30)
        ->and(CateringAttendance::query()->count())->toBe(60);
});

it('initialises a large month without a query per member or date', function () {
    $schoolClass = SchoolClass::factory()->create();
    $category = CateringCategory::factory()->student()->create();
    CateringMember::factory()->count(30)->create([
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);
    $queries = 0;

    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    CateringAttendanceInitializerService::ensureMonth(2026, 9);

    expect($queries)->toBeLessThanOrEqual(4)
        ->and(CateringAttendance::query()->count())->toBe(900);
});
