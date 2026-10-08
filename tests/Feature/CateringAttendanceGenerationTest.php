<?php

use App\Enums\CateringAttendanceStatus;
use App\Enums\CateringParticipantGroup;
use App\Livewire\CateringAttendance\Index as CateringAttendanceIndex;
use App\Models\CateringAttendance;
use App\Models\CateringCategory;
use App\Models\CateringMember;
use App\Models\SchoolClass;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function attendanceGenerationComponent(): Testable
{
    return Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringAttendanceIndex::class)
        ->set('month', 10)
        ->set('year', 2026);
}

function generatedStatus(CateringMember $member, string $date): ?CateringAttendanceStatus
{
    return $member->attendances()
        ->get()
        ->first(fn (CateringAttendance $attendance): bool => $attendance->attendance_date->isSameDay($date))
        ?->status;
}

it('generates the selected month for every active participant', function () {
    $schoolClass = SchoolClass::factory()->create();
    $studentCategory = CateringCategory::factory()->student()->create();
    $employeeCategory = CateringCategory::factory()->employee()->create();
    $students = CateringMember::factory()->count(2)->create([
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $studentCategory->id,
    ]);
    $employee = CateringMember::factory()->withoutClass()->create([
        'catering_category_id' => $employeeCategory->id,
    ]);

    $component = attendanceGenerationComponent()
        ->assertSee('Generate Kehadiran')
        ->assertSeeHtml('wire:target="generateAttendance"')
        ->call('generateAttendance')
        ->assertDispatched(
            'toast',
            type: 'success',
            message: 'Absensi Oktober 2026 berhasil digenerate.',
        );

    foreach ([...$students, $employee] as $member) {
        expect($member->attendances()->count())->toBe(31);
    }

    expect(CateringAttendance::query()->count())->toBe(93);
});

it('uses ikut as the weekday default', function () {
    $member = CateringMember::factory()->create();

    attendanceGenerationComponent()->call('generateAttendance');

    expect(generatedStatus($member, '2026-10-05'))->toBe(CateringAttendanceStatus::Ikut);
});

it('uses libur as the weekend default', function () {
    $member = CateringMember::factory()->create();

    attendanceGenerationComponent()->call('generateAttendance');

    expect(generatedStatus($member, '2026-10-03'))->toBe(CateringAttendanceStatus::Libur)
        ->and(generatedStatus($member, '2026-10-04'))->toBe(CateringAttendanceStatus::Libur);
});

it('preserves an existing sakit attendance', function () {
    $member = CateringMember::factory()->create();
    CateringAttendance::factory()->for($member)->create([
        'attendance_date' => '2026-10-05',
        'status' => CateringAttendanceStatus::Sakit,
    ]);

    attendanceGenerationComponent()->call('generateAttendance');

    expect(generatedStatus($member, '2026-10-05'))->toBe(CateringAttendanceStatus::Sakit)
        ->and($member->attendances()->count())->toBe(31);
});

it('preserves an existing izin attendance', function () {
    $member = CateringMember::factory()->create();
    CateringAttendance::factory()->for($member)->create([
        'attendance_date' => '2026-10-06',
        'status' => CateringAttendanceStatus::Izin,
    ]);

    attendanceGenerationComponent()->call('generateAttendance');

    expect(generatedStatus($member, '2026-10-06'))->toBe(CateringAttendanceStatus::Izin)
        ->and($member->attendances()->count())->toBe(31);
});

it('is idempotent when generation is clicked twice', function () {
    $member = CateringMember::factory()->create();
    $component = attendanceGenerationComponent();

    $component->call('generateAttendance');
    $component->call('generateAttendance')
        ->assertDispatched(
            'toast',
            type: 'info',
            message: 'Semua absensi Oktober 2026 sudah tersedia.',
        );

    expect($member->attendances()->count())->toBe(31)
        ->and(CateringAttendance::query()->count())->toBe(31);
});

it('generates only missing rows for a new participant and refreshes the open matrix', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $category = CateringCategory::factory()->student()->create();
    $existingMember = CateringMember::factory()->create([
        'name' => 'test',
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);
    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringAttendanceIndex::class)
        ->call('changeFilter', 'participantGroup', CateringParticipantGroup::Student->value)
        ->call('changeFilter', 'year', 2026)
        ->call('changeFilter', 'month', 10)
        ->call('changeFilter', 'jenjang', 'SMP')
        ->call('changeFilter', 'schoolClassId', (string) $schoolClass->id)
        ->assertSet('participants', fn (array $participants): bool => array_column($participants, 'name') === ['test']);
    $newMember = CateringMember::factory()->create([
        'name' => 'test 3',
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);

    $component->call('generateAttendance')
        ->assertSet('participants', fn (array $participants): bool => array_column($participants, 'name') === ['test', 'test 3']);

    expect($existingMember->attendances()->count())->toBe(31)
        ->and($newMember->attendances()->count())->toBe(31)
        ->and(CateringAttendance::query()->count())->toBe(62);
});

it('does not generate attendance for inactive participants', function () {
    $activeMember = CateringMember::factory()->create();
    $inactiveMember = CateringMember::factory()->inactive()->create();

    attendanceGenerationComponent()->call('generateAttendance');

    expect($activeMember->attendances()->count())->toBe(31)
        ->and($inactiveMember->attendances()->count())->toBe(0);
});

it('does not limit generation to the selected class or participant group', function () {
    $selectedClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $otherClass = SchoolClass::factory()->create(['name' => '8A', 'level' => '8']);
    $studentCategory = CateringCategory::factory()->student()->create();
    $employeeCategory = CateringCategory::factory()->employee()->create();
    $selectedStudent = CateringMember::factory()->create([
        'school_class_id' => $selectedClass->id,
        'catering_category_id' => $studentCategory->id,
    ]);
    $otherStudent = CateringMember::factory()->create([
        'school_class_id' => $otherClass->id,
        'catering_category_id' => $studentCategory->id,
    ]);
    $employee = CateringMember::factory()->withoutClass()->create([
        'catering_category_id' => $employeeCategory->id,
    ]);

    attendanceGenerationComponent()
        ->set('participantGroup', CateringParticipantGroup::Student->value)
        ->set('jenjang', 'SMP')
        ->set('schoolClassId', (string) $selectedClass->id)
        ->call('generateAttendance');

    expect($selectedStudent->attendances()->count())->toBe(31)
        ->and($otherStudent->attendances()->count())->toBe(31)
        ->and($employee->attendances()->count())->toBe(31);
});
