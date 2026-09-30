<?php

use App\Enums\CateringAttendanceStatus;
use App\Enums\CateringEmployeeAssignmentType;
use App\Enums\CateringParticipantGroup;
use App\Enums\Gender;
use App\Enums\SchoolLevel;
use App\Livewire\CateringMembers\Index as CateringMemberIndex;
use App\Models\CateringAttendance;
use App\Models\CateringCategory;
use App\Models\CateringEmployeeAssignment;
use App\Models\CateringMember;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\QueryException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function employeeCategory(): CateringCategory
{
    return CateringCategory::factory()->employee()->create();
}

function employeeMember(?CateringCategory $category = null): CateringMember
{
    return CateringMember::factory()->withoutClass()->create([
        'catering_category_id' => ($category ?? employeeCategory())->id,
    ]);
}

/**
 * Fill the member form for an employee carrying the given placement.
 */
function openEmployeeForm(CateringMember $member, CateringCategory $category): Testable
{
    return Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMemberIndex::class)
        ->call('edit', $member->id)
        ->set('employee_assignment_type', CateringEmployeeAssignmentType::General->value);
}

it('creates an employee assignment with class type', function () {
    $category = employeeCategory();
    $schoolClass = SchoolClass::factory()->create(['name' => '1A', 'level' => '1']);
    $member = employeeMember($category);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMemberIndex::class)
        ->call('create')
        ->set('name', 'Guru Satu A')
        ->set('catering_category_id', (string) $category->id)
        ->set('employee_assignment_type', CateringEmployeeAssignmentType::SchoolClass->value)
        ->set('employee_assignment_school_class_id', (string) $schoolClass->id)
        ->call('save')
        ->assertHasNoErrors();

    $member = CateringMember::where('name', 'Guru Satu A')->firstOrFail();

    expect($member->employeeAssignment)
        ->not->toBeNull()
        ->assignment_type->toBe(CateringEmployeeAssignmentType::SchoolClass)
        ->school_class_id->toBe($schoolClass->id)
        ->level->toBeNull()
        ->education_level->toBeNull();
});

it('creates an employee assignment with level type', function () {
    $category = employeeCategory();
    $member = employeeMember($category);

    openEmployeeForm($member, $category)
        ->set('employee_assignment_type', CateringEmployeeAssignmentType::Level->value)
        ->set('employee_assignment_level', SchoolLevel::Grade3->value)
        ->call('save')
        ->assertHasNoErrors();

    expect($member->fresh()->employeeAssignment)
        ->assignment_type->toBe(CateringEmployeeAssignmentType::Level)
        ->level->toBe(SchoolLevel::Grade3->value)
        ->school_class_id->toBeNull()
        ->education_level->toBeNull();
});

it('creates an employee assignment with education level type', function () {
    $category = employeeCategory();
    $member = employeeMember($category);

    openEmployeeForm($member, $category)
        ->set('employee_assignment_type', CateringEmployeeAssignmentType::EducationLevel->value)
        ->set('employee_assignment_education_level', 'SMA')
        ->call('save')
        ->assertHasNoErrors();

    expect($member->fresh()->employeeAssignment)
        ->assignment_type->toBe(CateringEmployeeAssignmentType::EducationLevel)
        ->education_level->toBe('SMA')
        ->school_class_id->toBeNull()
        ->level->toBeNull();
});

it('creates an employee assignment with general type', function () {
    $category = employeeCategory();
    $member = employeeMember($category);

    openEmployeeForm($member, $category)
        ->set('employee_assignment_type', CateringEmployeeAssignmentType::General->value)
        ->call('save')
        ->assertHasNoErrors();

    expect($member->fresh()->employeeAssignment)
        ->assignment_type->toBe(CateringEmployeeAssignmentType::General)
        ->school_class_id->toBeNull()
        ->level->toBeNull()
        ->education_level->toBeNull();
});

it('blocks a student participant from receiving an employee assignment', function () {
    $category = CateringCategory::factory()->student()->create();
    $schoolClass = SchoolClass::factory()->create();
    $member = CateringMember::factory()->create([
        'catering_category_id' => $category->id,
        'school_class_id' => $schoolClass->id,
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMemberIndex::class)
        ->call('create')
        ->set('name', 'Siswa Baru')
        ->set('catering_category_id', (string) $category->id)
        ->set('school_class_id', (string) $schoolClass->id)
        ->set('employee_assignment_type', CateringEmployeeAssignmentType::General->value)
        ->call('save')
        ->assertHasErrors(['employee_assignment_type']);

    expect($member->employeeAssignment)->toBeNull();
});

it('rejects a forged employee assignment posted for a student category', function () {
    $category = CateringCategory::factory()->student()->create();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMemberIndex::class)
        ->call('create')
        ->set('name', 'Siswa Forged')
        ->set('catering_category_id', (string) $category->id)
        ->set('employee_assignment_type', CateringEmployeeAssignmentType::General->value)
        ->set('employee_assignment_education_level', 'SMA')
        ->call('save')
        ->assertHasErrors(['employee_assignment_type', 'employee_assignment_education_level']);

    expect(CateringEmployeeAssignment::query()->count())->toBe(0);
});

it('requires a school class for class type', function () {
    $category = employeeCategory();
    $member = employeeMember($category);

    openEmployeeForm($member, $category)
        ->set('employee_assignment_type', CateringEmployeeAssignmentType::SchoolClass->value)
        ->set('employee_assignment_school_class_id', '')
        ->call('save')
        ->assertHasErrors(['employee_assignment_school_class_id']);

    expect($member->fresh()->employeeAssignment)->toBeNull();
});

it('requires a canonical level for level type', function () {
    $category = employeeCategory();
    $member = employeeMember($category);

    openEmployeeForm($member, $category)
        ->set('employee_assignment_type', CateringEmployeeAssignmentType::Level->value)
        ->set('employee_assignment_level', '')
        ->call('save')
        ->assertHasErrors(['employee_assignment_level']);

    expect($member->fresh()->employeeAssignment)->toBeNull();
});

it('rejects a level value that is not a canonical school level', function () {
    $category = employeeCategory();
    $member = employeeMember($category);

    openEmployeeForm($member, $category)
        ->set('employee_assignment_type', CateringEmployeeAssignmentType::Level->value)
        ->set('employee_assignment_level', '13')
        ->call('save')
        ->assertHasErrors(['employee_assignment_level']);

    expect($member->fresh()->employeeAssignment)->toBeNull();
});

it('requires an education level for education level type', function () {
    $category = employeeCategory();
    $member = employeeMember($category);

    openEmployeeForm($member, $category)
        ->set('employee_assignment_type', CateringEmployeeAssignmentType::EducationLevel->value)
        ->set('employee_assignment_education_level', '')
        ->call('save')
        ->assertHasErrors(['employee_assignment_education_level']);

    expect($member->fresh()->employeeAssignment)->toBeNull();
});

it('rejects an education level that is not a canonical jenjang', function () {
    $category = employeeCategory();
    $member = employeeMember($category);

    openEmployeeForm($member, $category)
        ->set('employee_assignment_type', CateringEmployeeAssignmentType::EducationLevel->value)
        ->set('employee_assignment_education_level', 'Universitas')
        ->call('save')
        ->assertHasErrors(['employee_assignment_education_level']);
});

it('rejects an inactive class for a class type assignment', function () {
    $category = employeeCategory();
    $member = employeeMember($category);
    $inactiveClass = SchoolClass::factory()->inactive()->create();

    openEmployeeForm($member, $category)
        ->set('employee_assignment_type', CateringEmployeeAssignmentType::SchoolClass->value)
        ->set('employee_assignment_school_class_id', (string) $inactiveClass->id)
        ->call('save')
        ->assertHasErrors(['employee_assignment_school_class_id']);

    expect($member->fresh()->employeeAssignment)->toBeNull();
});

it('clears every target field for general type', function () {
    $category = employeeCategory();
    $schoolClass = SchoolClass::factory()->create();
    $member = employeeMember($category);

    CateringEmployeeAssignment::factory()->schoolClass($schoolClass)->create([
        'catering_member_id' => $member->id,
    ]);

    openEmployeeForm($member, $category)
        ->set('employee_assignment_type', CateringEmployeeAssignmentType::General->value)
        ->call('save')
        ->assertHasNoErrors();

    $assignment = $member->fresh()->employeeAssignment;

    expect($assignment->assignment_type)->toBe(CateringEmployeeAssignmentType::General)
        ->and($assignment->school_class_id)->toBeNull()
        ->and($assignment->level)->toBeNull()
        ->and($assignment->education_level)->toBeNull();
});

it('clears stale fields when the assignment type changes', function () {
    $category = employeeCategory();
    $schoolClass = SchoolClass::factory()->create();
    $member = employeeMember($category);

    CateringEmployeeAssignment::factory()->schoolClass($schoolClass)->create([
        'catering_member_id' => $member->id,
    ]);

    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMemberIndex::class)
        ->call('edit', $member->id);

    // The form is preloaded with the stored class placement.
    expect($component->get('employee_assignment_type'))
        ->toBe(CateringEmployeeAssignmentType::SchoolClass->value)
        ->and($component->get('employee_assignment_school_class_id'))
        ->toBe((string) $schoolClass->id);

    $component
        ->set('employee_assignment_type', CateringEmployeeAssignmentType::EducationLevel->value)
        ->set('employee_assignment_education_level', 'SMA')
        ->call('save')
        ->assertHasNoErrors();

    $assignment = $member->fresh()->employeeAssignment;

    expect($assignment->assignment_type)->toBe(CateringEmployeeAssignmentType::EducationLevel)
        ->and($assignment->education_level)->toBe('SMA')
        ->and($assignment->school_class_id)->toBeNull()
        ->and($assignment->level)->toBeNull();
});

it('clears the form fields when the assignment type changes', function () {
    $category = employeeCategory();
    $schoolClass = SchoolClass::factory()->create();
    $member = employeeMember($category);

    CateringEmployeeAssignment::factory()->schoolClass($schoolClass)->create([
        'catering_member_id' => $member->id,
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMemberIndex::class)
        ->call('edit', $member->id)
        ->set('employee_assignment_school_class_id', (string) $schoolClass->id)
        ->set('employee_assignment_type', CateringEmployeeAssignmentType::Level->value)
        ->assertSet('employee_assignment_school_class_id', '')
        ->set('employee_assignment_level', '3')
        ->set('employee_assignment_type', CateringEmployeeAssignmentType::EducationLevel->value)
        ->assertSet('employee_assignment_level', '')
        ->set('employee_assignment_type', CateringEmployeeAssignmentType::General->value)
        ->assertSet('employee_assignment_education_level', '');
});

it('enforces one assignment row per employee at the database level', function () {
    $member = employeeMember();

    CateringEmployeeAssignment::factory()->general()->create([
        'catering_member_id' => $member->id,
    ]);

    expect(fn () => CateringEmployeeAssignment::factory()->general()->create([
        'catering_member_id' => $member->id,
    ]))->toThrow(QueryException::class);
});

it('reuses the same row when the employee is edited again', function () {
    $category = employeeCategory();
    $member = employeeMember($category);

    openEmployeeForm($member, $category)->call('save')->assertHasNoErrors();
    openEmployeeForm($member->fresh(), $category)->call('save')->assertHasNoErrors();

    expect(CateringEmployeeAssignment::query()->count())->toBe(1);
});

it('exposes the employeeAssignment relationship on the member', function () {
    $schoolClass = SchoolClass::factory()->create();
    $member = employeeMember();

    expect($member->employeeAssignment)->toBeNull();

    $assignment = CateringEmployeeAssignment::factory()->schoolClass($schoolClass)->create([
        'catering_member_id' => $member->id,
    ]);

    expect($member->fresh()->employeeAssignment->is($assignment))->toBeTrue();
});

it('exposes the schoolClass relationship for a class assignment', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '3B', 'level' => '3']);
    $member = employeeMember();

    $assignment = CateringEmployeeAssignment::factory()->schoolClass($schoolClass)->create([
        'catering_member_id' => $member->id,
    ]);

    expect($assignment->schoolClass)->not->toBeNull()
        ->id->toBe($schoolClass->id)
        ->name->toBe('3B')
        ->and($assignment->cateringMember->is($member))->toBeTrue()
        ->and($assignment->target_label)->toBe('3B')
        ->and($assignment->has_missing_target)->toBeFalse();
});

it('keeps a student usable through the existing crud flow', function () {
    $category = CateringCategory::factory()->student()->create();
    $schoolClass = SchoolClass::factory()->create();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMemberIndex::class)
        ->call('create')
        ->set('name', 'Siswa Tetap')
        ->set('catering_category_id', (string) $category->id)
        ->set('school_class_id', (string) $schoolClass->id)
        ->set('gender', Gender::Male->value)
        ->call('save')
        ->assertHasNoErrors();

    $student = CateringMember::where('name', 'Siswa Tetap')->firstOrFail();

    expect($student->school_class_id)->toBe($schoolClass->id)
        ->and($student->cateringCategory->participant_group)->toBe(CateringParticipantGroup::Student)
        ->and($student->employeeAssignment)->toBeNull();
});

it('keeps student attendance working alongside the new table', function () {
    $category = CateringCategory::factory()->student()->create();
    $student = CateringMember::factory()->create(['catering_category_id' => $category->id]);

    CateringAttendance::factory()->for($student)->create([
        'attendance_date' => '2026-09-01',
        'status' => CateringAttendanceStatus::Ikut,
    ]);

    $employee = employeeMember();
    CateringEmployeeAssignment::factory()->general()->create(['catering_member_id' => $employee->id]);

    expect($student->attendances()->count())->toBe(1)
        ->and($student->attendances()->first()->status)->toBe(CateringAttendanceStatus::Ikut)
        ->and($student->employeeAssignment)->toBeNull()
        ->and($employee->attendances()->count())->toBe(0);
});

it('leaves existing members and attendance untouched', function () {
    $category = CateringCategory::factory()->student()->create();
    $student = CateringMember::factory()->create(['catering_category_id' => $category->id]);
    $employee = employeeMember();

    $membersBefore = CateringMember::count();
    $attendancesBefore = CateringAttendance::count();

    CateringEmployeeAssignment::factory()->general()->create(['catering_member_id' => $employee->id]);

    expect(CateringMember::count())->toBe($membersBefore)
        ->and(CateringAttendance::count())->toBe($attendancesBefore)
        ->and(CateringMember::whereKey($student->id)->exists())->toBeTrue()
        ->and(CateringMember::whereKey($employee->id)->exists())->toBeTrue();
});

it('drops the assignment when the category is switched back to student', function () {
    $employeeCategory = employeeCategory();
    $studentCategory = CateringCategory::factory()->student()->create();
    $member = employeeMember($employeeCategory);

    openEmployeeForm($member, $employeeCategory)->call('save')->assertHasNoErrors();

    expect($member->fresh()->employeeAssignment)->not->toBeNull();

    openEmployeeForm($member->fresh(), $studentCategory)
        ->set('catering_category_id', (string) $studentCategory->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($member->fresh()->catering_category_id)->toBe($studentCategory->id)
        ->and($member->fresh()->employeeAssignment)->toBeNull()
        ->and(CateringEmployeeAssignment::query()->count())->toBe(0);
});

it('hides the placement section for a student category and shows it for an employee', function () {
    $studentCategory = CateringCategory::factory()->student()->create();
    $employeeCategory = employeeCategory();

    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMemberIndex::class)
        ->call('create')
        ->set('catering_category_id', (string) $studentCategory->id)
        ->assertDontSeeHtml('Penempatan Pegawai')
        ->set('catering_category_id', (string) $employeeCategory->id)
        ->assertSeeHtml('Penempatan Pegawai');
});

it('binds the category and placement type selects as live so the browser updates immediately', function () {
    $employeeCategory = employeeCategory();

    $html = Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMemberIndex::class)
        ->call('create')
        ->set('catering_category_id', (string) $employeeCategory->id)
        ->html();

    // A plain wire:model on a <select> is deferred by Livewire, so the placement
    // section and target field would not react until a later request in the browser.
    expect($html)->toContain('wire:model.live="catering_category_id"')
        ->and($html)->toContain('wire:model.live="employee_assignment_type"');
});

it('eager loads the placement without n plus one queries', function () {
    $category = employeeCategory();
    $schoolClass = SchoolClass::factory()->create();

    CateringMember::factory()->count(5)->create(['catering_category_id' => $category->id]);

    foreach (CateringMember::participantGroup(CateringParticipantGroup::Employee)->get() as $member) {
        CateringEmployeeAssignment::factory()->schoolClass($schoolClass)->create([
            'catering_member_id' => $member->id,
        ]);
    }

    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMemberIndex::class)
        ->call('create')
        ->set('catering_category_id', (string) $category->id);

    $component->assertSeeHtml('Penempatan Pegawai');

    // Rendering the list touches the placement relation for every row; the
    // query count must not grow with the number of employees.
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $component->call('$refresh');

    expect($queries)->toBeLessThan(25);
});

it('restricts the assignment foreign key so member history is not cascaded', function () {
    $employee = employeeMember();
    CateringEmployeeAssignment::factory()->general()->create(['catering_member_id' => $employee->id]);

    expect(fn () => $employee->forceDelete())
        ->toThrow(QueryException::class);

    expect(CateringMember::whereKey($employee->id)->exists())->toBeTrue();
});

it('exposes assignment types with the expected labels', function () {
    expect(CateringEmployeeAssignmentType::SchoolClass->value)->toBe('class')
        ->and(CateringEmployeeAssignmentType::Level->value)->toBe('level')
        ->and(CateringEmployeeAssignmentType::EducationLevel->value)->toBe('education_level')
        ->and(CateringEmployeeAssignmentType::General->value)->toBe('general')
        ->and(CateringEmployeeAssignmentType::SchoolClass->label())->toBe('Kelas')
        ->and(CateringEmployeeAssignmentType::Level->label())->toBe('Tingkat')
        ->and(CateringEmployeeAssignmentType::EducationLevel->label())->toBe('Jenjang')
        ->and(CateringEmployeeAssignmentType::General->label())->toBe('Umum')
        ->and(CateringEmployeeAssignmentType::options())->toBe([
            'class' => 'Kelas',
            'level' => 'Tingkat',
            'education_level' => 'Jenjang',
            'general' => 'Umum',
        ]);
});

it('offers every canonical level in the level dropdown', function () {
    $category = employeeCategory();

    $html = Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMemberIndex::class)
        ->call('create')
        ->set('catering_category_id', (string) $category->id)
        ->set('employee_assignment_type', CateringEmployeeAssignmentType::Level->value)
        ->html();

    // The level dropdown is driven by SchoolLevel, so it must offer all of them.
    foreach (SchoolLevel::cases() as $level) {
        expect($html)->toContain('value="'.$level->value.'"');
    }

    expect($html)->toContain('Kelas 1')
        ->and($html)->toContain('TK A');
});

it('offers only active classes in the placement class dropdown', function () {
    $category = employeeCategory();
    $activeClass = SchoolClass::factory()->create(['name' => '6A', 'level' => '6']);
    $inactiveClass = SchoolClass::factory()->inactive()->create(['name' => '6Z', 'level' => '6']);

    $html = Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMemberIndex::class)
        ->call('create')
        ->set('catering_category_id', (string) $category->id)
        ->set('employee_assignment_type', CateringEmployeeAssignmentType::SchoolClass->value)
        ->html();

    expect($html)->toContain('value="'.$activeClass->id.'"')
        ->and($html)->not->toContain('value="'.$inactiveClass->id.'"');
});

it('offers every canonical jenjang in the education level dropdown', function () {
    $category = employeeCategory();

    $html = Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMemberIndex::class)
        ->call('create')
        ->set('catering_category_id', (string) $category->id)
        ->set('employee_assignment_type', CateringEmployeeAssignmentType::EducationLevel->value)
        ->html();

    foreach (SchoolLevel::educationLevels() as $educationLevel) {
        expect($html)->toContain('value="'.$educationLevel.'"');
    }
});

it('shows no extra target field for general type', function () {
    $category = employeeCategory();

    $html = Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMemberIndex::class)
        ->call('create')
        ->set('catering_category_id', (string) $category->id)
        ->set('employee_assignment_type', CateringEmployeeAssignmentType::General->value)
        ->html();

    expect($html)->not->toContain('member-assignment-class')
        ->and($html)->not->toContain('member-assignment-level')
        ->and($html)->not->toContain('member-assignment-education-level')
        ->and($html)->toContain('tidak terikat kelas, tingkat, atau jenjang');
});

it('supports all four groupings for the future recap', function () {
    $schoolClass = SchoolClass::factory()->create();

    $classEmployee = employeeMember();
    CateringEmployeeAssignment::factory()->schoolClass($schoolClass)->create([
        'catering_member_id' => $classEmployee->id,
    ]);

    $levelEmployee = employeeMember();
    CateringEmployeeAssignment::factory()->level(SchoolLevel::Grade3)->create([
        'catering_member_id' => $levelEmployee->id,
    ]);

    $jenjangEmployee = employeeMember();
    CateringEmployeeAssignment::factory()->educationLevel('SMA')->create([
        'catering_member_id' => $jenjangEmployee->id,
    ]);

    $generalEmployee = employeeMember();
    CateringEmployeeAssignment::factory()->general()->create([
        'catering_member_id' => $generalEmployee->id,
    ]);

    expect(CateringEmployeeAssignment::ofType(CateringEmployeeAssignmentType::SchoolClass)->count())->toBe(1)
        ->and(CateringEmployeeAssignment::ofType(CateringEmployeeAssignmentType::Level)->count())->toBe(1)
        ->and(CateringEmployeeAssignment::ofType(CateringEmployeeAssignmentType::EducationLevel)->count())->toBe(1)
        ->and(CateringEmployeeAssignment::ofType(CateringEmployeeAssignmentType::General)->count())->toBe(1)
        ->and(CateringEmployeeAssignment::where('school_class_id', $schoolClass->id)->count())->toBe(1)
        ->and(CateringEmployeeAssignment::where('level', '3')->count())->toBe(1)
        ->and(CateringEmployeeAssignment::where('education_level', 'SMA')->count())->toBe(1);
});
