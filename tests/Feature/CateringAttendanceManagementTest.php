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

function openCateringAttendanceMatrix(
    User $user,
    SchoolClass $schoolClass,
    int $year = 2026,
    int $month = 9,
    string $jenjang = 'SMP',
): Testable {
    return Livewire::actingAs($user)
        ->test(CateringAttendanceIndex::class)
        ->call('changeFilter', 'participantGroup', CateringParticipantGroup::Student->value)
        ->call('changeFilter', 'year', $year)
        ->call('changeFilter', 'month', $month)
        ->call('changeFilter', 'jenjang', $jenjang)
        ->call('changeFilter', 'schoolClassId', (string) $schoolClass->id);
}

/**
 * Read a single stored status back through the model's date cast.
 *
 * Asserting on the raw column would be driver specific: the `date` cast stores a
 * full timestamp on SQLite while a MySQL `DATE` column keeps only the date.
 */
function attendanceStatusOn(int $memberId, string $date): ?CateringAttendanceStatus
{
    $row = CateringAttendance::query()
        ->where('catering_member_id', $memberId)
        ->get()
        ->first(fn (CateringAttendance $attendance): bool => $attendance->attendance_date->isSameDay($date));

    return $row?->status;
}

it('redirects guests away from the catering attendance page', function () {
    $this->get(route('catering-attendance.index'))->assertRedirect(route('login'));
});

it('allows an admin to open the catering attendance page', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('catering-attendance.index'))
        ->assertSee('Absensi Catering');
});

it('allows a wali kelas to open the catering attendance page', function () {
    $this->actingAs(User::factory()->waliKelas()->create())
        ->get(route('catering-attendance.index'))
        ->assertSee('Absensi Catering');
});

it('lists only active classes from the selected jenjang in canonical order', function () {
    SchoolClass::factory()->create(['name' => '7B', 'level' => '7']);
    SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    SchoolClass::factory()->create(['name' => 'A-1', 'level' => 'TK A']);
    SchoolClass::factory()->inactive()->create(['name' => '7C', 'level' => '7']);

    Livewire::actingAs(User::factory()->waliKelas()->create())
        ->test(CateringAttendanceIndex::class)
        ->call('changeFilter', 'jenjang', 'SMP')
        ->assertSet(
            'classOptions',
            fn (array $classes): bool => array_column($classes, 'name') === ['7A', '7B'],
        );
});

it('resets an incompatible class and clears its matrix when jenjang changes', function () {
    $smpClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    SchoolClass::factory()->create(['name' => 'A-1', 'level' => 'TK A']);
    CateringMember::factory()->create(['school_class_id' => $smpClass->id]);

    openCateringAttendanceMatrix(User::factory()->admin()->create(), $smpClass)
        ->call('changeFilter', 'jenjang', 'TK')
        ->assertSet('schoolClassId', '')
        ->assertSet('loadedClassId', null)
        ->assertSet('participants', []);
});

it('rejects an inactive class when loading the matrix', function () {
    $inactiveClass = SchoolClass::factory()->inactive()->create(['name' => '7A', 'level' => '7']);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringAttendanceIndex::class)
        ->call('changeFilter', 'participantGroup', CateringParticipantGroup::Student->value)
        ->call('changeFilter', 'jenjang', 'SMP')
        ->call('changeFilter', 'schoolClassId', (string) $inactiveClass->id)
        ->assertHasErrors(['schoolClassId'])
        ->assertSet('loadedClassId', null);
});

it('loads active participants from the selected class in name order', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    CateringMember::factory()->create(['name' => 'Zaki', 'school_class_id' => $schoolClass->id]);
    CateringMember::factory()->create(['name' => 'Ahmad', 'school_class_id' => $schoolClass->id]);

    openCateringAttendanceMatrix(User::factory()->admin()->create(), $schoolClass)
        ->assertSet(
            'participants',
            fn (array $participants): bool => array_column($participants, 'name') === ['Ahmad', 'Zaki'],
        );
});

it('does not load participants from another class', function () {
    $selectedClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $otherClass = SchoolClass::factory()->create(['name' => '7B', 'level' => '7']);
    CateringMember::factory()->create(['name' => 'Peserta 7A', 'school_class_id' => $selectedClass->id]);
    CateringMember::factory()->create(['name' => 'Peserta 7B', 'school_class_id' => $otherClass->id]);

    openCateringAttendanceMatrix(User::factory()->admin()->create(), $selectedClass)
        ->assertSet(
            'participants',
            fn (array $participants): bool => array_column($participants, 'name') === ['Peserta 7A'],
        );
});

it('does not load inactive catering participants', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    CateringMember::factory()->create(['name' => 'Peserta Aktif', 'school_class_id' => $schoolClass->id]);
    CateringMember::factory()->inactive()->create(['name' => 'Peserta Nonaktif', 'school_class_id' => $schoolClass->id]);

    openCateringAttendanceMatrix(User::factory()->admin()->create(), $schoolClass)
        ->assertSet(
            'participants',
            fn (array $participants): bool => array_column($participants, 'name') === ['Peserta Aktif'],
        );
});

it('shows the empty state when a selected class has no active participants', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);

    openCateringAttendanceMatrix(User::factory()->waliKelas()->create(), $schoolClass)
        ->assertSee('Belum ada peserta catering aktif di kelas ini.');
});

it('generates the actual number of dates for each month', function (int $year, int $month, int $expectedDays) {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);

    openCateringAttendanceMatrix(User::factory()->admin()->create(), $schoolClass, $year, $month)
        ->assertSet('dates', fn (array $dates): bool => count($dates) === $expectedDays);
})->with([
    'February 2026' => [2026, 2, 28],
    'leap-year February 2024' => [2024, 2, 29],
    'April 2026' => [2026, 4, 30],
    'January 2026' => [2026, 1, 31],
]);

it('uses the correct default status for weekdays and weekends', function (string $date, string $expectedStatus) {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $member = CateringMember::factory()->create(['school_class_id' => $schoolClass->id]);

    openCateringAttendanceMatrix(User::factory()->admin()->create(), $schoolClass)
        ->assertSet("attendance.{$member->id}.{$date}", $expectedStatus);
})->with([
    'weekday Tuesday' => ['2026-09-01', 'ikut'],
    'Saturday' => ['2026-09-05', 'libur'],
    'Sunday' => ['2026-09-06', 'libur'],
]);

it('uses saved attendance instead of the weekday default', function (CateringAttendanceStatus $status) {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $member = CateringMember::factory()->create(['school_class_id' => $schoolClass->id]);
    CateringAttendance::factory()->for($member)->create([
        'attendance_date' => '2026-09-01',
        'status' => $status,
    ]);

    openCateringAttendanceMatrix(User::factory()->admin()->create(), $schoolClass)
        ->assertSet("attendance.{$member->id}.2026-09-01", $status->value);
})->with([
    'saved sakit' => [CateringAttendanceStatus::Sakit],
    'saved izin' => [CateringAttendanceStatus::Izin],
    'saved alfa' => [CateringAttendanceStatus::Alfa],
    'saved tidak ikut' => [CateringAttendanceStatus::TidakIkut],
    'saved ujian' => [CateringAttendanceStatus::Ujian],
    'saved event unit' => [CateringAttendanceStatus::EventUnit],
    'saved puasa' => [CateringAttendanceStatus::Puasa],
]);

it('marks and restores a whole weekday column', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $members = CateringMember::factory()->count(2)->create(['school_class_id' => $schoolClass->id]);
    $component = openCateringAttendanceMatrix(User::factory()->admin()->create(), $schoolClass);

    $component->call('setDateStatus', '2026-09-01', 'libur');

    foreach ($members as $member) {
        $component->assertSet("attendance.{$member->id}.2026-09-01", 'libur');
    }

    $component->call('setDateStatus', '2026-09-01', 'ikut');

    foreach ($members as $member) {
        $component->assertSet("attendance.{$member->id}.2026-09-01", 'ikut');
    }
});

it('auto saves a single cell and dispatches no manual save toast', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $members = CateringMember::factory()->count(2)->create(['school_class_id' => $schoolClass->id]);

    openCateringAttendanceMatrix(User::factory()->admin()->create(), $schoolClass)
        ->call('setCellStatus', $members[0]->id, '2026-09-01', 'sakit')
        ->assertHasNoErrors()
        ->assertSet('saveState.saved', true)
        ->assertNotDispatched('toast');

    expect(CateringAttendance::query()->count())->toBe(60);
    expect(attendanceStatusOn($members[0]->id, '2026-09-01'))->toBe(CateringAttendanceStatus::Sakit);
});

it('updates an existing cell without creating a duplicate row', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $member = CateringMember::factory()->create(['school_class_id' => $schoolClass->id]);
    $component = openCateringAttendanceMatrix(User::factory()->admin()->create(), $schoolClass);

    $component->call('setCellStatus', $member->id, '2026-09-01', 'tidak_ikut')
        ->assertHasNoErrors();

    expect(CateringAttendance::query()->count())->toBe(30);
    expect(attendanceStatusOn($member->id, '2026-09-01'))->toBe(CateringAttendanceStatus::TidakIkut);
});

it('does not change attendance belonging to another class', function () {
    $selectedClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $otherClass = SchoolClass::factory()->create(['name' => '7B', 'level' => '7']);
    CateringMember::factory()->create(['school_class_id' => $selectedClass->id]);
    $otherMember = CateringMember::factory()->create(['school_class_id' => $otherClass->id]);
    $otherAttendance = CateringAttendance::factory()->for($otherMember)->create([
        'attendance_date' => '2026-09-01',
        'status' => CateringAttendanceStatus::Alfa,
    ]);

    openCateringAttendanceMatrix(User::factory()->admin()->create(), $selectedClass)
        ->call('setDateStatus', '2026-09-01', 'libur')
        ->assertHasNoErrors();

    expect($otherAttendance->fresh()->status)->toBe(CateringAttendanceStatus::Alfa);
});

it('does not change attendance belonging to another month', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $member = CateringMember::factory()->create(['school_class_id' => $schoolClass->id]);
    $otherMonthAttendance = CateringAttendance::factory()->for($member)->create([
        'attendance_date' => '2026-10-01',
        'status' => CateringAttendanceStatus::Sakit,
    ]);

    openCateringAttendanceMatrix(User::factory()->admin()->create(), $schoolClass)
        ->call('setDateStatus', '2026-09-01', 'libur')
        ->assertHasNoErrors();

    expect($otherMonthAttendance->fresh()->status)->toBe(CateringAttendanceStatus::Sakit);
});

it('rejects an invalid attendance status before writing', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $member = CateringMember::factory()->create(['school_class_id' => $schoolClass->id]);

    openCateringAttendanceMatrix(User::factory()->admin()->create(), $schoolClass)
        ->call('setCellStatus', $member->id, '2026-09-01', 'hadir-sebagian')
        ->assertHasErrors(['status']);

    expect(CateringAttendance::query()->where('status', 'hadir-sebagian')->count())->toBe(0);
});

it('reloads all previously saved statuses for the same class and month', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $member = CateringMember::factory()->create(['school_class_id' => $schoolClass->id]);
    $user = User::factory()->admin()->create();

    openCateringAttendanceMatrix($user, $schoolClass)
        ->call('setCellStatus', $member->id, '2026-09-01', 'sakit')
        ->call('setCellStatus', $member->id, '2026-09-02', 'izin')
        ->call('setCellStatus', $member->id, '2026-09-03', 'alfa')
        ->call('setCellStatus', $member->id, '2026-09-04', 'tidak_ikut')
        ->assertHasNoErrors();

    openCateringAttendanceMatrix($user, $schoolClass)
        ->assertSet("attendance.{$member->id}.2026-09-01", 'sakit')
        ->assertSet("attendance.{$member->id}.2026-09-02", 'izin')
        ->assertSet("attendance.{$member->id}.2026-09-03", 'alfa')
        ->assertSet("attendance.{$member->id}.2026-09-04", 'tidak_ikut');
});

it('renders tidak ikut in the attendance modal with violet styling', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $member = CateringMember::factory()->create(['school_class_id' => $schoolClass->id]);

    openCateringAttendanceMatrix(User::factory()->admin()->create(), $schoolClass)
        ->call('openStatusMenu', $member->id, '2026-09-01')
        ->assertSee('Tidak Ikut')
        ->assertSeeHtml('border-violet-800 bg-violet-600 text-white hover:bg-violet-700 focus-visible:ring-violet-300')
        ->assertSeeHtml('scale-[1.03] shadow-md ring-4 ring-emerald-300 ring-offset-2');
});

it('renders the manual non-billable statuses with distinct high contrast styling', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $member = CateringMember::factory()->create(['school_class_id' => $schoolClass->id]);

    openCateringAttendanceMatrix(User::factory()->admin()->create(), $schoolClass)
        ->call('openStatusMenu', $member->id, '2026-09-01')
        ->assertSee('Ujian')
        ->assertSee('Event Unit')
        ->assertSee('Puasa')
        ->assertSeeHtml('border-cyan-700 bg-cyan-500 text-white hover:bg-cyan-600 focus-visible:ring-cyan-300')
        ->assertSeeHtml('border-fuchsia-800 bg-fuchsia-600 text-white hover:bg-fuchsia-700 focus-visible:ring-fuchsia-300')
        ->assertSeeHtml('border-indigo-800 bg-indigo-600 text-white hover:bg-indigo-700 focus-visible:ring-indigo-300');
});

it('requires a participant group before a matrix can be loaded', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    CateringMember::factory()->create(['school_class_id' => $schoolClass->id]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringAttendanceIndex::class)
        ->call('changeFilter', 'jenjang', 'SMP')
        ->call('changeFilter', 'schoolClassId', (string) $schoolClass->id)
        ->assertSet('matrixLoaded', true)
        ->call('changeFilter', 'participantGroup', '')
        ->assertHasErrors(['participantGroup' => 'required'])
        ->assertSet('matrixLoaded', false)
        ->assertSet('participants', [])
        ->call('loadMatrix')
        ->assertHasErrors(['participantGroup' => 'required']);
});

it('groups participants by the category participant group instead of the category name', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $studentCategory = CateringCategory::factory()->create([
        'name' => 'Siswa Umum',
        'participant_group' => CateringParticipantGroup::Student,
    ]);
    $employeeCategory = CateringCategory::factory()->create([
        'name' => 'Guru',
        'participant_group' => CateringParticipantGroup::Employee,
    ]);

    CateringMember::factory()->create([
        'name' => 'Siswa Kelas Ini',
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $studentCategory->id,
    ]);
    CateringMember::factory()->create([
        'name' => 'Pegawai Kelas Ini',
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $employeeCategory->id,
    ]);

    openCateringAttendanceMatrix(User::factory()->admin()->create(), $schoolClass)
        ->assertSet(
            'participants',
            fn (array $participants): bool => array_column($participants, 'name') === ['Siswa Kelas Ini'],
        );
});

it('keeps the student and employee groups apart when a category is renamed', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $employeeCategory = CateringCategory::factory()->create([
        'name' => 'Guru',
        'participant_group' => CateringParticipantGroup::Employee,
    ]);
    CateringMember::factory()->create([
        'name' => 'Pegawai Tetap',
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $employeeCategory->id,
    ]);
    CateringMember::factory()->create(['name' => 'Siswa Tetap', 'school_class_id' => $schoolClass->id]);

    $employeeCategory->update(['name' => 'Nama Baru Totally']);

    openCateringAttendanceMatrix(User::factory()->admin()->create(), $schoolClass)
        ->assertSet(
            'participants',
            fn (array $participants): bool => array_column($participants, 'name') === ['Siswa Tetap'],
        );
});

it('loads employee participants without jenjang or class selection', function () {
    $employeeCategory = CateringCategory::factory()->employee()->create(['name' => 'Guru']);
    CateringMember::factory()->withoutClass()->create([
        'name' => 'Pegawai Tanpa Kelas',
        'catering_category_id' => $employeeCategory->id,
    ]);
    CateringMember::factory()->create([
        'name' => 'Siswa Ada Kelas',
        'catering_category_id' => CateringCategory::factory()->student()->create(),
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringAttendanceIndex::class)
        ->call('changeFilter', 'year', 2026)
        ->call('changeFilter', 'month', 9)
        ->call('changeFilter', 'participantGroup', CateringParticipantGroup::Employee->value)
        ->assertSet('schoolClassId', '')
        ->assertSet('loadedClassId', null)
        ->assertSet('matrixLoaded', true)
        ->assertSet(
            'participants',
            fn (array $participants): bool => array_column($participants, 'name') === ['Pegawai Tanpa Kelas'],
        );

    expect(CateringAttendance::query()->count())->toBe(60);
});

it('switches straight to the employee matrix when the group changes', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    CateringMember::factory()->create(['school_class_id' => $schoolClass->id]);

    openCateringAttendanceMatrix(User::factory()->admin()->create(), $schoolClass)
        ->assertSet('loadedParticipantGroup', CateringParticipantGroup::Student->value)
        ->call('changeFilter', 'participantGroup', CateringParticipantGroup::Employee->value)
        ->assertSet('participants', [])
        ->assertSet('loadedClassId', null)
        ->assertSet('loadedParticipantGroup', CateringParticipantGroup::Employee->value);
});

it('summarizes every attendance status in the current matrix', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $member = CateringMember::factory()->create(['school_class_id' => $schoolClass->id]);

    openCateringAttendanceMatrix(User::factory()->admin()->create(), $schoolClass)
        ->call('setCellStatus', $member->id, '2026-09-01', 'sakit')
        ->call('setCellStatus', $member->id, '2026-09-02', 'tidak_ikut')
        ->call('setCellStatus', $member->id, '2026-09-03', 'ujian')
        ->call('setCellStatus', $member->id, '2026-09-04', 'event_unit')
        ->call('setCellStatus', $member->id, '2026-09-07', 'puasa')
        ->assertViewHas('summary', fn (array $summary): bool => $summary === [
            'participants' => 1,
            'ikut' => 17,
            'sakit' => 1,
            'izin' => 0,
            'alfa' => 0,
            'tidak_ikut' => 1,
            'ujian' => 1,
            'event_unit' => 1,
            'puasa' => 1,
            'libur' => 8,
        ]);
});
