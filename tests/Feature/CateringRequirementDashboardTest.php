<?php

use App\Enums\CateringAttendanceStatus;
use App\Enums\SchoolLevel;
use App\Livewire\CateringDashboard\Index as CateringDashboardIndex;
use App\Models\CateringAttendance;
use App\Models\CateringCategory;
use App\Models\CateringEmployeeAssignment;
use App\Models\CateringMember;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\CateringRequirementRecapService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

const RECAP_DATE = '2026-09-30';

function recapStudent(string $name, SchoolClass $schoolClass, ?CateringCategory $category = null): CateringMember
{
    return CateringMember::factory()->create([
        'name' => $name,
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => ($category ?? CateringCategory::factory()->student()->create())->id,
    ]);
}

function recapEmployee(string $name, ?CateringCategory $category = null): CateringMember
{
    return CateringMember::factory()->withoutClass()->create([
        'name' => $name,
        'catering_category_id' => ($category ?? CateringCategory::factory()->employee()->create())->id,
    ]);
}

function saveAttendance(CateringMember $member, string $date, CateringAttendanceStatus $status): CateringAttendance
{
    return CateringAttendance::factory()->create([
        'catering_member_id' => $member->id,
        'attendance_date' => $date,
        'status' => $status,
    ]);
}

function recapStoredStatus(CateringMember $member, string $date): ?CateringAttendanceStatus
{
    return CateringAttendance::query()
        ->where('catering_member_id', $member->id)
        ->get()
        ->first(fn (CateringAttendance $attendance): bool => $attendance->attendance_date->isSameDay($date))
        ?->status;
}

function recap(): Testable
{
    return Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringDashboardIndex::class);
}

function recapOn(string $date): Testable
{
    return recap()->call('changeDate', $date);
}

function recapFor(string $date): array
{
    return CateringRequirementRecapService::forDate(CarbonImmutable::parse($date));
}

/*
|--------------------------------------------------------------------------
| Date selection
|--------------------------------------------------------------------------
*/

it('defaults the recap to tomorrow', function () {
    $expected = CarbonImmutable::today(config('app.timezone'))->addDay()->toDateString();

    expect(recap()->get('selectedDate'))->toBe($expected)
        ->and(recapFor($expected)['badge'])->toBe('Besok');
});

it('changes the recap date to yesterday', function () {
    $expected = CarbonImmutable::today(config('app.timezone'))->subDay()->toDateString();

    expect(recap()->call('gotoYesterday')->get('selectedDate'))->toBe($expected)
        ->and(recapFor($expected)['badge'])->toBe('Kemarin');
});

it('changes the recap date to today', function () {
    $expected = CarbonImmutable::today(config('app.timezone'))->toDateString();

    expect(recap()->call('gotoToday')->get('selectedDate'))->toBe($expected)
        ->and(recapFor($expected)['badge'])->toBe('Hari Ini');
});

it('changes the recap date to tomorrow', function () {
    $expected = CarbonImmutable::today(config('app.timezone'))->addDay()->toDateString();

    expect(recap()->call('gotoTomorrow')->get('selectedDate'))->toBe($expected)
        ->and(recapFor($expected)['badge'])->toBe('Besok');
});

it('updates the recap date from the date picker', function () {
    expect(recapOn('2026-10-05')->get('selectedDate'))->toBe('2026-10-05')
        ->and(recapFor('2026-10-05')['date'])->toBe('2026-10-05');
});

it('rejects a malformed date picker value', function () {
    recap()->call('changeDate', 'bukan-tanggal')
        ->assertHasErrors(['selectedDate' => 'date_format']);
});

it('has no badge for a date that is neither today, yesterday nor tomorrow', function () {
    expect(recapFor('2026-10-05')['badge'])->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Data source: saved attendance only, only "ikut"
|--------------------------------------------------------------------------
*/

it('counts only status ikut towards the portion total', function () {
    $class = SchoolClass::factory()->create(['name' => '1A', 'level' => '1']);

    saveAttendance(recapStudent('Siswa Ikut', $class), RECAP_DATE, CateringAttendanceStatus::Ikut);

    $recap = recapFor(RECAP_DATE);

    expect($recap['totalPortions'])->toBe(1)
        ->and($recap['hasSavedData'])->toBeTrue();
});

it('excludes sakit from the portion total', function () {
    $class = SchoolClass::factory()->create(['name' => '1A', 'level' => '1']);

    saveAttendance(recapStudent('Siswa Sakit', $class), RECAP_DATE, CateringAttendanceStatus::Sakit);

    $recap = recapFor(RECAP_DATE);

    expect($recap['totalPortions'])->toBe(0)
        ->and($recap['hasSavedData'])->toBeTrue();
});

it('excludes izin from the portion total', function () {
    $class = SchoolClass::factory()->create(['name' => '1A', 'level' => '1']);

    saveAttendance(recapStudent('Siswa Izin', $class), RECAP_DATE, CateringAttendanceStatus::Izin);

    expect(recapFor(RECAP_DATE)['totalPortions'])->toBe(0);
});

it('counts budaya makan as zero portions while preserving the alfa value', function () {
    $class = SchoolClass::factory()->create(['name' => '1A', 'level' => '1']);

    $student = recapStudent('Siswa Budaya Makan', $class);
    saveAttendance($student, RECAP_DATE, CateringAttendanceStatus::Alfa);

    expect(recapFor(RECAP_DATE)['totalPortions'])->toBe(0)
        ->and(recapStoredStatus($student, RECAP_DATE))->toBe(CateringAttendanceStatus::Alfa);
});

it('excludes tidak ikut from the portion total and counts it separately', function () {
    $class = SchoolClass::factory()->create(['name' => '1A', 'level' => '1']);

    saveAttendance(recapStudent('Siswa Tidak Ikut', $class), RECAP_DATE, CateringAttendanceStatus::TidakIkut);

    $recap = recapFor(RECAP_DATE);

    expect($recap['totalPortions'])->toBe(0)
        ->and($recap['tidakIkutRows'])->toBe(1)
        ->and($recap['liburRows'])->toBe(0);
});

it('excludes libur from the portion total', function () {
    $class = SchoolClass::factory()->create(['name' => '1A', 'level' => '1']);

    saveAttendance(recapStudent('Siswa Libur', $class), RECAP_DATE, CateringAttendanceStatus::Libur);

    $recap = recapFor(RECAP_DATE);

    expect($recap['totalPortions'])->toBe(0)
        ->and($recap['liburRows'])->toBe(1);
});

it('excludes the new non-billable statuses from the portion total', function (CateringAttendanceStatus $status) {
    $class = SchoolClass::factory()->create(['name' => '1A', 'level' => '1']);

    saveAttendance(recapStudent('Siswa Non-Billable', $class), RECAP_DATE, $status);

    expect(recapFor(RECAP_DATE)['totalPortions'])->toBe(0);
})->with([
    'ujian' => CateringAttendanceStatus::Ujian,
    'event unit' => CateringAttendanceStatus::EventUnit,
    'puasa' => CateringAttendanceStatus::Puasa,
]);

it('does not make the recap service infer attendance that was not persisted', function () {
    $class = SchoolClass::factory()->create(['name' => '1A', 'level' => '1']);
    recapStudent('Belum Absen', $class);

    $recap = recapFor(RECAP_DATE);

    expect($recap['totalPortions'])->toBe(0)
        ->and($recap['hasSavedData'])->toBeFalse()
        ->and($recap['activeParticipants'])->toBe(1);
});

it('initialises the selected dashboard date for every active participant', function () {
    $class = SchoolClass::factory()->create(['name' => '1A', 'level' => '1']);
    $student = recapStudent('Siswa Global', $class);
    $employee = recapEmployee('Pegawai Global');
    CateringEmployeeAssignment::factory()->general()->create(['catering_member_id' => $employee->id]);
    $inactive = CateringMember::factory()->inactive()->create([
        'school_class_id' => $class->id,
        'catering_category_id' => CateringCategory::factory()->student()->create()->id,
    ]);

    recapOn(RECAP_DATE)
        ->assertViewHas('recap', fn (array $recap): bool => $recap['totalPortions'] === 2
            && $recap['hasSavedData'] === true);

    expect(recapStoredStatus($student, RECAP_DATE))->toBe(CateringAttendanceStatus::Ikut)
        ->and(recapStoredStatus($employee, RECAP_DATE))->toBe(CateringAttendanceStatus::Ikut)
        ->and(recapStoredStatus($inactive, RECAP_DATE))->toBeNull();
});

it('only reads attendance for the single selected date', function () {
    $class = SchoolClass::factory()->create(['name' => '1A', 'level' => '1']);
    $student = recapStudent('Siswa', $class);

    saveAttendance($student, '2026-09-29', CateringAttendanceStatus::Ikut);
    saveAttendance($student, RECAP_DATE, CateringAttendanceStatus::Ikut);

    expect(recapFor(RECAP_DATE)['totalPortions'])->toBe(1)
        ->and(recapFor('2026-09-29')['totalPortions'])->toBe(1)
        ->and(recapFor('2026-09-28')['totalPortions'])->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Student grouping
|--------------------------------------------------------------------------
*/

it('groups students by their own class inside the right jenjang', function () {
    $grade1 = SchoolClass::factory()->create(['name' => '1A', 'level' => '1']);
    $grade1b = SchoolClass::factory()->create(['name' => '1B', 'level' => '1']);

    saveAttendance(recapStudent('Andi', $grade1), RECAP_DATE, CateringAttendanceStatus::Ikut);
    saveAttendance(recapStudent('Budi', $grade1), RECAP_DATE, CateringAttendanceStatus::Ikut);
    saveAttendance(recapStudent('Citra', $grade1b), RECAP_DATE, CateringAttendanceStatus::Ikut);

    $recap = recapFor(RECAP_DATE);

    expect($recap['jenjangs']['SD']['levels']['1']['classes'][$grade1->id]['students'])->toBe(2)
        ->and($recap['jenjangs']['SD']['levels']['1']['classes'][$grade1->id]['total'])->toBe(2)
        ->and($recap['jenjangs']['SD']['levels']['1']['classes'][$grade1b->id]['students'])->toBe(1)
        ->and($recap['jenjangs']['SD']['levels']['1']['total'])->toBe(3)
        ->and($recap['jenjangs']['SD']['total'])->toBe(3);
});

it('maps every jenjang from the existing SchoolLevel mapping', function () {
    $levels = [
        'Daycare' => 'Daycare',
        'TK' => 'KB',
        'SD' => '1',
        'SMP' => '7',
        'SMA' => '10',
    ];

    foreach ($levels as $jenjang => $level) {
        $class = SchoolClass::factory()->create(['name' => $jenjang.'-X', 'level' => $level]);
        saveAttendance(recapStudent('Siswa '.$jenjang, $class), RECAP_DATE, CateringAttendanceStatus::Ikut);
    }

    $recap = recapFor(RECAP_DATE);

    foreach (array_keys($levels) as $jenjang) {
        expect($recap['jenjangs'][$jenjang]['total'])->toBe(1);
    }

    expect($recap['totalPortions'])->toBe(5);
});

it('keeps the student jenjang order canonical', function () {
    expect(array_keys(recapFor(RECAP_DATE)['jenjangs']))
        ->toBe(SchoolLevel::educationLevels());
});

it('surfaces students without a class instead of dropping them', function () {
    $student = CateringMember::factory()->withoutClass()->create([
        'name' => 'Siswa Tanpa Kelas',
        'catering_category_id' => CateringCategory::factory()->student()->create()->id,
    ]);

    saveAttendance($student, RECAP_DATE, CateringAttendanceStatus::Ikut);

    $recap = recapFor(RECAP_DATE);

    expect($recap['classless']['total'])->toBe(1)
        ->and($recap['totalPortions'])->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Employee assignments
|--------------------------------------------------------------------------
*/

it('adds a class assigned employee to that class total', function () {
    $class = SchoolClass::factory()->create(['name' => '1A', 'level' => '1']);

    saveAttendance(recapStudent('Andi', $class), RECAP_DATE, CateringAttendanceStatus::Ikut);
    saveAttendance(recapStudent('Budi', $class), RECAP_DATE, CateringAttendanceStatus::Ikut);

    $employee = recapEmployee('Bu Guru');
    CateringEmployeeAssignment::factory()->schoolClass($class)->create(['catering_member_id' => $employee->id]);
    saveAttendance($employee, RECAP_DATE, CateringAttendanceStatus::Ikut);

    $recap = recapFor(RECAP_DATE);
    $classNode = $recap['jenjangs']['SD']['levels']['1']['classes'][$class->id];

    expect($classNode['students'])->toBe(2)
        ->and($classNode['employees'])->toBe(1)
        ->and($classNode['total'])->toBe(3)
        ->and($recap['totalPortions'])->toBe(3);
});

it('adds a level assigned employee to the level total and not to a class', function () {
    $classA = SchoolClass::factory()->create(['name' => '3A', 'level' => '3']);
    $classB = SchoolClass::factory()->create(['name' => '3B', 'level' => '3']);

    saveAttendance(recapStudent('Andi', $classA), RECAP_DATE, CateringAttendanceStatus::Ikut);
    saveAttendance(recapStudent('Budi', $classB), RECAP_DATE, CateringAttendanceStatus::Ikut);

    foreach (['Guru Satu', 'Guru Dua'] as $name) {
        $employee = recapEmployee($name);
        CateringEmployeeAssignment::factory()->level('3')->create(['catering_member_id' => $employee->id]);
        saveAttendance($employee, RECAP_DATE, CateringAttendanceStatus::Ikut);
    }

    $recap = recapFor(RECAP_DATE);
    $level = $recap['jenjangs']['SD']['levels']['3'];

    expect($level['levelEmployees'])->toHaveCount(2)
        ->and($level['employeeTotal'])->toBe(2)
        ->and($level['classes'][$classA->id]['total'])->toBe(1)
        ->and($level['classes'][$classB->id]['total'])->toBe(1)
        ->and($level['total'])->toBe(4)
        ->and($recap['jenjangs']['SD']['total'])->toBe(4)
        ->and($recap['totalPortions'])->toBe(4);
});

it('adds an education level assigned employee to the jenjang total only', function () {
    $class = SchoolClass::factory()->create(['name' => '10A', 'level' => '10']);

    saveAttendance(recapStudent('Andi', $class), RECAP_DATE, CateringAttendanceStatus::Ikut);

    $employee = recapEmployee('Guru SMA');
    CateringEmployeeAssignment::factory()->educationLevel('SMA')->create(['catering_member_id' => $employee->id]);
    saveAttendance($employee, RECAP_DATE, CateringAttendanceStatus::Ikut);

    $recap = recapFor(RECAP_DATE);

    // The employee sits at the SMA summary, never inside a class or level, while
    // the student still rolls up through level 10.
    expect($recap['jenjangs']['SMA']['educationEmployees'])->toHaveCount(1)
        ->and($recap['jenjangs']['SMA']['employeeTotal'])->toBe(1)
        ->and($recap['jenjangs']['SMA']['levels']['10']['classes'][$class->id]['total'])->toBe(1)
        ->and($recap['jenjangs']['SMA']['levels']['10']['levelEmployees'])->toBe([])
        ->and($recap['jenjangs']['SMA']['levels']['10']['total'])->toBe(1)
        ->and($recap['jenjangs']['SMA']['total'])->toBe(2)
        ->and($recap['totalPortions'])->toBe(2);
});

it('adds a general employee to the general section only', function () {
    $class = SchoolClass::factory()->create(['name' => '1A', 'level' => '1']);
    saveAttendance(recapStudent('Andi', $class), RECAP_DATE, CateringAttendanceStatus::Ikut);

    $employee = recapEmployee('Bapak Tu');
    CateringEmployeeAssignment::factory()->general()->create(['catering_member_id' => $employee->id]);
    saveAttendance($employee, RECAP_DATE, CateringAttendanceStatus::Ikut);

    $recap = recapFor(RECAP_DATE);

    expect($recap['general']['total'])->toBe(1)
        ->and($recap['jenjangs']['SD']['total'])->toBe(1)
        ->and($recap['totalPortions'])->toBe(2);
});

it('renders employee assignment rows with their own totals while preserving parent subtotals', function () {
    $class = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    saveAttendance(recapStudent('Siswa', $class), RECAP_DATE, CateringAttendanceStatus::Ikut);

    $classEmployee = recapEmployee('Guru Kelas');
    CateringEmployeeAssignment::factory()->schoolClass($class)->create(['catering_member_id' => $classEmployee->id]);
    saveAttendance($classEmployee, RECAP_DATE, CateringAttendanceStatus::Ikut);

    $levelEmployee = recapEmployee('Guru Tingkat');
    CateringEmployeeAssignment::factory()->level('7')->create(['catering_member_id' => $levelEmployee->id]);
    saveAttendance($levelEmployee, RECAP_DATE, CateringAttendanceStatus::Ikut);

    $jenjangEmployee = recapEmployee('Guru Jenjang');
    CateringEmployeeAssignment::factory()->educationLevel('SMP')->create(['catering_member_id' => $jenjangEmployee->id]);
    saveAttendance($jenjangEmployee, RECAP_DATE, CateringAttendanceStatus::Ikut);

    $generalEmployee = recapEmployee('Guru Umum');
    CateringEmployeeAssignment::factory()->general()->create(['catering_member_id' => $generalEmployee->id]);
    saveAttendance($generalEmployee, RECAP_DATE, CateringAttendanceStatus::Ikut);

    $component = recapOn(RECAP_DATE);
    $recap = $component->get('recap');

    expect($recap['jenjangs']['SMP']['levels']['7']['classes'][$class->id]['employees'])->toBe(1)
        ->and($recap['jenjangs']['SMP']['levels']['7']['classes'][$class->id]['total'])->toBe(2)
        ->and($recap['jenjangs']['SMP']['levels']['7']['employeeTotal'])->toBe(1)
        ->and($recap['jenjangs']['SMP']['levels']['7']['total'])->toBe(3)
        ->and($recap['jenjangs']['SMP']['employeeTotal'])->toBe(1)
        ->and($recap['jenjangs']['SMP']['total'])->toBe(4)
        ->and($recap['general']['total'])->toBe(1)
        ->and($recap['totalPortions'])->toBe(5);

    $component
        ->assertSeeHtml('data-employee-assignment="class" data-employee-total="1"')
        ->assertSeeHtml('data-employee-assignment="level" data-employee-total="1"')
        ->assertSeeHtml('data-employee-assignment="education-level" data-employee-total="1"')
        ->assertSeeHtml('data-employee-assignment="general" data-employee-total="1"')
        ->assertSeeHtml('data-parent-subtotal="class" data-parent-total="2"')
        ->assertSeeHtml('data-parent-subtotal="level" data-parent-total="3"');
});

it('never counts an employee twice across the hierarchy', function () {
    $class = SchoolClass::factory()->create(['name' => '3A', 'level' => '3']);
    saveAttendance(recapStudent('Andi', $class), RECAP_DATE, CateringAttendanceStatus::Ikut);

    $classEmployee = recapEmployee('Guru Kelas');
    CateringEmployeeAssignment::factory()->schoolClass($class)->create(['catering_member_id' => $classEmployee->id]);
    saveAttendance($classEmployee, RECAP_DATE, CateringAttendanceStatus::Ikut);

    $levelEmployee = recapEmployee('Guru Tingkat');
    CateringEmployeeAssignment::factory()->level('3')->create(['catering_member_id' => $levelEmployee->id]);
    saveAttendance($levelEmployee, RECAP_DATE, CateringAttendanceStatus::Ikut);

    $jenjangEmployee = recapEmployee('Guru Jenjang');
    CateringEmployeeAssignment::factory()->educationLevel('SD')->create(['catering_member_id' => $jenjangEmployee->id]);
    saveAttendance($jenjangEmployee, RECAP_DATE, CateringAttendanceStatus::Ikut);

    $generalEmployee = recapEmployee('Guru Umum');
    CateringEmployeeAssignment::factory()->general()->create(['catering_member_id' => $generalEmployee->id]);
    saveAttendance($generalEmployee, RECAP_DATE, CateringAttendanceStatus::Ikut);

    $recap = recapFor(RECAP_DATE);
    $sd = $recap['jenjangs']['SD'];

    // One portion per employee, plus the one student, rolled up exactly once each.
    expect($sd['levels']['3']['classes'][$class->id]['total'])->toBe(2)
        ->and($sd['levels']['3']['classes'][$class->id]['employees'])->toBe(1)
        ->and($sd['levels']['3']['employeeTotal'])->toBe(1)
        ->and($sd['levels']['3']['total'])->toBe(3)
        ->and($sd['employeeTotal'])->toBe(1)
        ->and($sd['total'])->toBe(4)
        ->and($recap['general']['total'])->toBe(1)
        ->and($recap['totalPortions'])->toBe(5);

    $summed = array_sum(array_map(
        fn (array $jenjang): int => $jenjang['total'],
        $recap['jenjangs'],
    )) + $recap['general']['total'] + $recap['unplaced']['total'] + $recap['classless']['total'];

    expect($summed)->toBe($recap['totalPortions']);
});

it('excludes an inactive participant from the portion total', function () {
    $class = SchoolClass::factory()->create(['name' => '1A', 'level' => '1']);

    saveAttendance(recapStudent('Aktif', $class), RECAP_DATE, CateringAttendanceStatus::Ikut);

    $inactive = CateringMember::factory()->inactive()->create([
        'name' => 'Nonaktif',
        'school_class_id' => $class->id,
        'catering_category_id' => CateringCategory::factory()->student()->create()->id,
    ]);
    saveAttendance($inactive, RECAP_DATE, CateringAttendanceStatus::Ikut);

    expect(recapFor(RECAP_DATE)['totalPortions'])->toBe(1);
});

it('excludes an inactive employee from the unassigned warning', function () {
    recapEmployee('Guru Aktif');
    CateringMember::factory()->inactive()->withoutClass()->create([
        'name' => 'Guru Nonaktif',
        'catering_category_id' => CateringCategory::factory()->employee()->create()->id,
    ]);

    expect(recapFor(RECAP_DATE)['unassignedEmployees'])->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Unassigned employees
|--------------------------------------------------------------------------
*/

it('does not guess an unassigned employee into a class, level or jenjang', function () {
    $class = SchoolClass::factory()->create(['name' => '1A', 'level' => '1']);
    saveAttendance(recapStudent('Andi', $class), RECAP_DATE, CateringAttendanceStatus::Ikut);

    $unassigned = recapEmployee('Guru Tanpa Penempatan');
    saveAttendance($unassigned, RECAP_DATE, CateringAttendanceStatus::Ikut);

    $recap = recapFor(RECAP_DATE);

    expect($recap['unassignedEmployees'])->toBe(1)
        ->and($recap['unplaced']['total'])->toBe(1)
        ->and($recap['jenjangs']['SD']['total'])->toBe(1)
        ->and($recap['jenjangs']['SD']['levels']['1']['total'])->toBe(1)
        ->and($recap['totalPortions'])->toBe(2);
});

it('warns about unassigned employees and lists their names', function () {
    $unassigned = recapEmployee('Guru Tanpa Penempatan');
    saveAttendance($unassigned, RECAP_DATE, CateringAttendanceStatus::Ikut);

    $component = recapOn(RECAP_DATE);

    $component->assertSee('Pegawai belum memiliki penempatan: 1')
        ->assertSee('Belum Ditempatkan')
        ->call('openDetail', 'unplaced')
        ->assertSee('Detail', false)
        ->assertSee('Guru Tanpa Penempatan');

    $component->call('closeDetail')->assertSet('detailKey', null);
});

it('ignores an unknown detail key', function () {
    recapOn(RECAP_DATE)
        ->call('openDetail', 'class:999999')
        ->assertSet('detailKey', null);
});

/*
|--------------------------------------------------------------------------
| Detail lists
|--------------------------------------------------------------------------
*/

it('renders the required section heading and saved data wording', function () {
    $student = recapStudent('Andi', SchoolClass::factory()->create(['name' => '1A', 'level' => '1']));
    saveAttendance($student, RECAP_DATE, CateringAttendanceStatus::Ikut);

    recapOn(RECAP_DATE)
        ->assertOk()
        ->assertSee('Kebutuhan Catering Per Tanggal')
        ->assertSee('Data Tersimpan')
        // No cutoff or finalisation language, the figure is never a locked total.
        ->assertDontSee('Dikunci')
        ->assertDontSee('Final');
});

it('lists the counted names for a class', function () {
    $class = SchoolClass::factory()->create(['name' => '1A', 'level' => '1']);

    saveAttendance(recapStudent('Andi', $class), RECAP_DATE, CateringAttendanceStatus::Ikut);
    saveAttendance(recapStudent('Budi', $class), RECAP_DATE, CateringAttendanceStatus::Ikut);

    $employee = recapEmployee('Bu Guru');
    CateringEmployeeAssignment::factory()->schoolClass($class)->create(['catering_member_id' => $employee->id]);
    saveAttendance($employee, RECAP_DATE, CateringAttendanceStatus::Ikut);

    // Atez yang tidak ikut tidak boleh muncul di detail porsi.
    saveAttendance(recapStudent('Siswa Sakit', $class), RECAP_DATE, CateringAttendanceStatus::Sakit);

    recapOn(RECAP_DATE)
        ->call('openDetail', 'class:'.$class->id)
        ->assertSee('Andi')
        ->assertSee('Budi')
        ->assertSee('Bu Guru')
        ->assertDontSee('Siswa Sakit')
        ->assertSee('3 peserta dihitung dalam porsi.');
});

it('lists the counted names for a level assigned employee', function () {
    $class = SchoolClass::factory()->create(['name' => '3A', 'level' => '3']);
    saveAttendance(recapStudent('Andi', $class), RECAP_DATE, CateringAttendanceStatus::Ikut);

    $employee = recapEmployee('Guru Tingkat Tiga');
    CateringEmployeeAssignment::factory()->level('3')->create(['catering_member_id' => $employee->id]);
    saveAttendance($employee, RECAP_DATE, CateringAttendanceStatus::Ikut);

    recapOn(RECAP_DATE)
        ->call('openDetail', 'level:SD:3')
        ->assertSee('Pegawai Tingkat Kelas 3')
        ->assertSee('Guru Tingkat Tiga')
        ->assertDontSee('Andi');
});

it('lists the counted names for an education level assigned employee', function () {
    $class = SchoolClass::factory()->create(['name' => '10A', 'level' => '10']);
    saveAttendance(recapStudent('Andi', $class), RECAP_DATE, CateringAttendanceStatus::Ikut);

    $employee = recapEmployee('Guru SMA');
    CateringEmployeeAssignment::factory()->educationLevel('SMA')->create(['catering_member_id' => $employee->id]);
    saveAttendance($employee, RECAP_DATE, CateringAttendanceStatus::Ikut);

    recapOn(RECAP_DATE)
        ->call('openDetail', 'jenjang:SMA')
        ->assertSee('Pegawai Jenjang SMA')
        ->assertSee('Guru SMA')
        ->assertDontSee('Andi');
});

it('lists the counted names for a general employee', function () {
    $employee = recapEmployee('Bapak Tu');
    CateringEmployeeAssignment::factory()->general()->create(['catering_member_id' => $employee->id]);
    saveAttendance($employee, RECAP_DATE, CateringAttendanceStatus::Ikut);

    recapOn(RECAP_DATE)
        ->call('openDetail', 'general')
        ->assertSee('Pegawai Umum')
        ->assertSee('Bapak Tu');
});

/*
|--------------------------------------------------------------------------
| Empty date state
|--------------------------------------------------------------------------
*/

it('shows a proper empty state instead of a misleading zero total', function () {
    recapOn(RECAP_DATE)
        ->assertSee('Belum ada data absensi catering tersimpan untuk tanggal ini.')
        ->assertDontSee('Data Tersimpan');
});

it('reports a zero portion day as libur context when every status is libur', function () {
    $class = SchoolClass::factory()->create(['name' => '1A', 'level' => '1']);
    saveAttendance(recapStudent('Siswa', $class), RECAP_DATE, CateringAttendanceStatus::Libur);

    recapOn(RECAP_DATE)
        ->assertSee('Data Tersimpan')
        ->assertSee('0 Porsi · Libur / tidak ada catering');
});

it('reports tidak ikut separately on a zero portion dashboard day', function () {
    $class = SchoolClass::factory()->create(['name' => '1A', 'level' => '1']);
    saveAttendance(recapStudent('Siswa', $class), RECAP_DATE, CateringAttendanceStatus::TidakIkut);

    recapOn(RECAP_DATE)
        ->assertSee('0 Porsi · Tidak ada peserta yang ikut')
        ->assertSee('1 baris Off')
        ->assertDontSee('0 Porsi · Libur / tidak ada catering');
});

it('initialises switched dashboard dates without overwriting saved statuses', function () {
    $class = SchoolClass::factory()->create(['name' => '1A', 'level' => '1']);
    $student = recapStudent('Andi', $class);
    saveAttendance($student, '2026-10-05', CateringAttendanceStatus::Sakit);

    $membersBefore = CateringMember::count();
    $assignmentsBefore = CateringEmployeeAssignment::count();

    recap()
        ->call('changeDate', '2026-10-05')
        ->call('changeDate', '2026-10-06');

    expect(recapStoredStatus($student, '2026-10-05'))->toBe(CateringAttendanceStatus::Sakit)
        ->and(recapStoredStatus($student, '2026-10-06'))->toBe(CateringAttendanceStatus::Ikut)
        ->and(CateringMember::count())->toBe($membersBefore)
        ->and(CateringEmployeeAssignment::count())->toBe($assignmentsBefore);
});

it('does not grow its query count as the number of classes grows', function () {
    $countQueries = function (): int {
        // Warm any lazily resolved schema or config first.
        CateringRequirementRecapService::forDate(CarbonImmutable::parse(RECAP_DATE));

        DB::enableQueryLog();
        DB::flushQueryLog();
        CateringRequirementRecapService::forDate(CarbonImmutable::parse(RECAP_DATE));
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    $seed = function (int $classCount, string $prefix): void {
        $category = CateringCategory::factory()->student()->create();

        foreach (SchoolLevel::cases() as $offset => $level) {
            for ($index = 0; $index < $classCount; $index++) {
                // Built without the factory so the faker unique pool cannot
                // influence how many classes this test creates.
                $class = SchoolClass::create([
                    'name' => $prefix.$offset.'-'.$index,
                    'level' => $level->value,
                    'is_active' => true,
                ]);

                foreach (range(1, 2) as $studentIndex) {
                    saveAttendance(
                        recapStudent('Siswa '.$prefix.$offset.'-'.$index.'-'.$studentIndex, $class, $category),
                        RECAP_DATE,
                        CateringAttendanceStatus::Ikut,
                    );
                }
            }
        }
    };

    $seed(1, 'A');
    $small = $countQueries();
    $smallClasses = SchoolClass::count();

    $seed(2, 'B');
    $large = $countQueries();
    $largeClasses = SchoolClass::count();

    // Doubling the classes and the portion rows must not add a single query:
    // that is what rules out a per class, per level or per participant query.
    expect($largeClasses)->toBeGreaterThan($smallClasses)
        ->and($large)->toBe($small)
        ->and($small)->toBeLessThanOrEqual(4);
});
