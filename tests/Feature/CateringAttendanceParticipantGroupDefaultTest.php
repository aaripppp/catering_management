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

function openDefaultStudentMatrix(
    User $user,
    SchoolClass $schoolClass,
    int $year = 2026,
    int $month = 9,
): Testable {
    return Livewire::actingAs($user)
        ->test(CateringAttendanceIndex::class)
        ->call('changeFilter', 'year', $year)
        ->call('changeFilter', 'month', $month)
        ->call('changeFilter', 'jenjang', 'SMP')
        ->call('changeFilter', 'schoolClassId', (string) $schoolClass->id);
}

it('defaults the participant group to student on a fresh page', function () {
    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringAttendanceIndex::class)
        ->assertSet('participantGroup', CateringParticipantGroup::Student->value)
        ->assertViewHas('hasValidParticipantGroup', true)
        ->assertViewHas('requiresClassSelection', true);
});

it('loads the student matrix without manually selecting the participant group', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $student = CateringMember::factory()->create(['name' => 'Siswa Default', 'school_class_id' => $schoolClass->id]);

    openDefaultStudentMatrix(User::factory()->admin()->create(), $schoolClass)
        ->assertSet('matrixLoaded', true)
        ->assertSet('loadedClassId', $schoolClass->id)
        ->assertSet('loadedParticipantGroup', CateringParticipantGroup::Student->value)
        ->assertSet(
            'participants',
            fn (array $participants): bool => array_column($participants, 'name') === ['Siswa Default'],
        )
        ->assertSee($student->name);
});

it('loads active student participants after selecting SMP then 7A', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $studentCategory = CateringCategory::factory()->student()->create(['price_per_day' => 17000]);
    CateringMember::factory()->create([
        'name' => 'Siswa Aktif',
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $studentCategory->id,
    ]);
    CateringMember::factory()->inactive()->create([
        'name' => 'Siswa Nonaktif',
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $studentCategory->id,
    ]);
    CateringMember::factory()->withoutClass()->create([
        'name' => 'Pegawai Angka',
        'catering_category_id' => CateringCategory::factory()->employee()->create()->id,
    ]);

    openDefaultStudentMatrix(User::factory()->admin()->create(), $schoolClass)
        ->assertSet('matrixLoaded', true)
        ->assertSet(
            'participants',
            fn (array $participants): bool => array_column($participants, 'name') === ['Siswa Aktif'],
        )
        ->assertDontSee('Siswa Nonaktif')
        ->assertDontSee('Pegawai Angka');
});

it('loads the expected 7A student records for the selected month', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $names = ['Ahmad Zaki', 'Aisyah Putri', 'Budi Santoso'];

    foreach ($names as $name) {
        CateringMember::factory()->create(['name' => $name, 'school_class_id' => $schoolClass->id]);
    }

    openDefaultStudentMatrix(User::factory()->admin()->create(), $schoolClass)
        ->assertSet(
            'participants',
            fn (array $participants): bool => array_column($participants, 'name') === $names,
        )
        ->assertSee('7A');
});

it('keeps the employee flow working when the participant group changes to employee', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    CateringMember::factory()->create(['name' => 'Siswa Kelas', 'school_class_id' => $schoolClass->id]);
    CateringMember::factory()->withoutClass()->create([
        'name' => 'Pegawai Aktif',
        'catering_category_id' => CateringCategory::factory()->employee()->create()->id,
    ]);

    openDefaultStudentMatrix(User::factory()->admin()->create(), $schoolClass)
        ->assertSet('loadedClassId', $schoolClass->id)
        ->call('changeFilter', 'participantGroup', CateringParticipantGroup::Employee->value)
        ->assertSet('loadedClassId', null)
        ->assertSet('matrixLoaded', true)
        ->assertSet('loadedParticipantGroup', CateringParticipantGroup::Employee->value)
        ->assertViewHas('requiresClassSelection', false)
        ->assertSet(
            'participants',
            fn (array $participants): bool => array_column($participants, 'name') === ['Pegawai Aktif'],
        );
});

it('switches back from employee to student and reloads the class', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    CateringMember::factory()->create(['name' => 'Siswa Kembali', 'school_class_id' => $schoolClass->id]);
    CateringMember::factory()->withoutClass()->create([
        'name' => 'Pegawai Tetap',
        'catering_category_id' => CateringCategory::factory()->employee()->create()->id,
    ]);

    openDefaultStudentMatrix(User::factory()->admin()->create(), $schoolClass)
        ->call('changeFilter', 'participantGroup', CateringParticipantGroup::Employee->value)
        ->assertSet(
            'participants',
            fn (array $participants): bool => array_column($participants, 'name') === ['Pegawai Tetap'],
        )
        ->call('changeFilter', 'participantGroup', CateringParticipantGroup::Student->value)
        ->assertSet('matrixLoaded', true)
        ->assertSet('loadedClassId', $schoolClass->id)
        ->assertSet('loadedParticipantGroup', CateringParticipantGroup::Student->value)
        ->assertSet(
            'participants',
            fn (array $participants): bool => array_column($participants, 'name') === ['Siswa Kembali'],
        );
});

it('keeps attendance auto saving and reloading working on the default student flow', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $student = CateringMember::factory()->create(['name' => 'Siswa Simpan', 'school_class_id' => $schoolClass->id]);

    openDefaultStudentMatrix(User::factory()->admin()->create(), $schoolClass)
        ->call('setCellStatus', $student->id, '2026-09-01', 'ikut')
        ->call('setCellStatus', $student->id, '2026-09-02', 'sakit')
        ->assertHasNoErrors()
        ->assertSet('saveState.saved', true);

    expect(CateringAttendance::query()
        ->where('catering_member_id', $student->id)
        ->whereDate('attendance_date', '2026-09-01')
        ->value('status'))->toBe(CateringAttendanceStatus::Ikut);

    expect(CateringAttendance::query()
        ->where('catering_member_id', $student->id)
        ->whereDate('attendance_date', '2026-09-02')
        ->value('status'))->toBe(CateringAttendanceStatus::Sakit);

    openDefaultStudentMatrix(User::factory()->admin()->create(), $schoolClass)
        ->assertSet('attendance.'.$student->id.'.2026-09-01', 'ikut')
        ->assertSet('attendance.'.$student->id.'.2026-09-02', 'sakit');
});

it('keeps the invoice actions available on the default student flow', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    CateringMember::factory()->create(['name' => 'Siswa Invoice', 'school_class_id' => $schoolClass->id]);

    openDefaultStudentMatrix(User::factory()->admin()->create(), $schoolClass)
        ->assertSee('Download Semua Invoice')
        ->assertSee('Cetak Invoice')
        ->assertSee('Cetak Invoice Kelas')
        // Attendance auto saves, so there is no manual save step any more.
        ->assertDontSee('Simpan Absensi');
});

it('shows guidance instead of the misleading empty state when filter state is incomplete', function () {
    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringAttendanceIndex::class)
        ->assertSee('Pilih jenjang dan kelas terlebih dahulu.')
        ->assertDontSee('Pilih kelompok peserta terlebih dahulu.')
        ->assertDontSee('Belum ada peserta catering aktif di kelas ini.')
        ->call('changeFilter', 'participantGroup', '')
        ->assertSee('Pilih kelompok peserta terlebih dahulu.')
        ->assertViewHas('hasValidParticipantGroup', false)
        ->assertViewHas('requiresClassSelection', false)
        ->assertDontSee('Belum ada peserta catering aktif di kelas ini.');
});
