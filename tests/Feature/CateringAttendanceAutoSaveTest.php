<?php

use App\Enums\CateringAttendanceStatus;
use App\Enums\CateringParticipantGroup;
use App\Livewire\CateringAttendance\Index as CateringAttendanceIndex;
use App\Livewire\CateringDashboard\Index as CateringDashboardIndex;
use App\Models\CateringAttendance;
use App\Models\CateringCategory;
use App\Models\CateringEmployeeAssignment;
use App\Models\CateringMember;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Open the September 2026 matrix for a class and let it initialise attendance.
 */
function autoSaveMatrix(SchoolClass $schoolClass, ?User $user = null): Testable
{
    return Livewire::actingAs($user ?? User::factory()->admin()->create())
        ->test(CateringAttendanceIndex::class)
        ->call('changeFilter', 'participantGroup', CateringParticipantGroup::Student->value)
        ->call('changeFilter', 'year', 2026)
        ->call('changeFilter', 'month', 9)
        ->call('changeFilter', 'jenjang', 'SMP')
        ->call('changeFilter', 'schoolClassId', (string) $schoolClass->id);
}

function autoSaveClass(string $name = '7A'): SchoolClass
{
    return SchoolClass::factory()->create(['name' => $name, 'level' => '7']);
}

function autoSaveMember(SchoolClass $schoolClass, string $name = 'Siswa'): CateringMember
{
    return CateringMember::factory()->create([
        'name' => $name,
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => CateringCategory::factory()->student()->create()->id,
    ]);
}

/**
 * Read a status back through the date cast so the assertion is driver agnostic.
 */
function storedStatus(int $memberId, string $date): ?CateringAttendanceStatus
{
    return CateringAttendance::query()
        ->where('catering_member_id', $memberId)
        ->get()
        ->first(fn (CateringAttendance $row): bool => $row->attendance_date->isSameDay($date))
        ?->status;
}

function storedRowsFor(int $memberId): array
{
    return CateringAttendance::query()
        ->where('catering_member_id', $memberId)
        ->get()
        ->mapWithKeys(fn (CateringAttendance $row): array => [
            $row->attendance_date->toDateString() => $row->status->value,
        ])
        ->all();
}

/*
|--------------------------------------------------------------------------
| Initialisation
|--------------------------------------------------------------------------
*/

it('creates one attendance row per active participant per day when the context is opened', function () {
    $schoolClass = autoSaveClass();
    $members = CateringMember::factory()->count(3)->create(['school_class_id' => $schoolClass->id]);

    autoSaveMatrix($schoolClass);

    expect(CateringAttendance::query()->count())->toBe(3 * 30);

    foreach ($members as $member) {
        expect(storedRowsFor($member->id))->toHaveCount(30);
    }
});

it('defaults every weekday of an initialised month to ikut', function () {
    $schoolClass = autoSaveClass();
    $member = autoSaveMember($schoolClass);

    autoSaveMatrix($schoolClass);

    $rows = storedRowsFor($member->id);

    expect($rows)->toHaveKey('2026-09-01')
        ->and($rows['2026-09-01'])->toBe(CateringAttendanceStatus::Ikut->value)
        ->and($rows['2026-09-02'])->toBe(CateringAttendanceStatus::Ikut->value)
        ->and($rows['2026-09-04'])->toBe(CateringAttendanceStatus::Ikut->value);
});

it('defaults every weekend of an initialised month to libur', function () {
    $schoolClass = autoSaveClass();
    $member = autoSaveMember($schoolClass);

    autoSaveMatrix($schoolClass);

    $rows = storedRowsFor($member->id);

    expect($rows['2026-09-05'])->toBe(CateringAttendanceStatus::Libur->value)
        ->and($rows['2026-09-06'])->toBe(CateringAttendanceStatus::Libur->value)
        ->and($rows['2026-09-12'])->toBe(CateringAttendanceStatus::Libur->value)
        ->and($rows['2026-09-27'])->toBe(CateringAttendanceStatus::Libur->value);
});

it('never overwrites a status that is already stored', function (CateringAttendanceStatus $status) {
    $schoolClass = autoSaveClass();
    $member = autoSaveMember($schoolClass);
    CateringAttendance::factory()->for($member)->create([
        'attendance_date' => '2026-09-01',
        'status' => $status,
    ]);

    autoSaveMatrix($schoolClass);

    expect(storedStatus($member->id, '2026-09-01'))->toBe($status)
        ->and(CateringAttendance::query()->where('catering_member_id', $member->id)->count())->toBe(30);
})->with([
    'sakit' => [CateringAttendanceStatus::Sakit],
    'izin' => [CateringAttendanceStatus::Izin],
    'alfa' => [CateringAttendanceStatus::Alfa],
    'tidak ikut' => [CateringAttendanceStatus::TidakIkut],
    'ujian' => [CateringAttendanceStatus::Ujian],
    'event unit' => [CateringAttendanceStatus::EventUnit],
    'puasa' => [CateringAttendanceStatus::Puasa],
    'libur' => [CateringAttendanceStatus::Libur],
]);

it('is idempotent when the same context is opened again', function () {
    $schoolClass = autoSaveClass();
    $member = autoSaveMember($schoolClass);

    autoSaveMatrix($schoolClass);
    $first = storedRowsFor($member->id);

    autoSaveMatrix($schoolClass);
    autoSaveMatrix($schoolClass);

    expect(storedRowsFor($member->id))->toBe($first)
        ->and(CateringAttendance::query()->count())->toBe(30);
});

it('clears the saved indicator when the matrix context changes', function () {
    $first = autoSaveClass('7A');
    $second = autoSaveClass('8A');
    $member = autoSaveMember($first);
    autoSaveMember($second);

    $component = autoSaveMatrix($first)
        ->call('setCellStatus', $member->id, '2026-09-01', CateringAttendanceStatus::Sakit->value)
        ->assertSet('saving', false)
        ->assertSet('saveState.saved', true);

    $component->call('changeFilter', 'schoolClassId', (string) $second->id)
        ->assertSet('saveState.saved', false)
        ->assertSet('saveState.at', null)
        ->assertSet('saving', false);
});

it('clears the saved indicator when the matrix is emptied', function () {
    $schoolClass = autoSaveClass();
    $member = autoSaveMember($schoolClass);

    autoSaveMatrix($schoolClass)
        ->call('setCellStatus', $member->id, '2026-09-01', CateringAttendanceStatus::Sakit->value)
        ->assertSet('saveState.saved', true)
        ->call('changeFilter', 'participantGroup', '')
        ->assertSet('matrixLoaded', false)
        ->assertSet('saveState.saved', false)
        ->assertSet('saveState.at', null);
});

it('does not initialise inactive participants', function () {
    $schoolClass = autoSaveClass();
    $active = autoSaveMember($schoolClass, 'Aktif');
    $inactive = CateringMember::factory()->inactive()->create([
        'name' => 'Nonaktif',
        'school_class_id' => $schoolClass->id,
    ]);

    autoSaveMatrix($schoolClass);

    expect(CateringAttendance::query()->where('catering_member_id', $inactive->id)->count())->toBe(0)
        ->and(CateringAttendance::query()->where('catering_member_id', $active->id)->count())->toBe(30);
});

it('initialises active participants from every class when one class is opened', function () {
    $schoolClass = autoSaveClass('7A');
    $otherClass = autoSaveClass('7B');
    $otherMember = autoSaveMember($otherClass, 'Kelas Lain');

    autoSaveMatrix($schoolClass);

    expect(CateringAttendance::query()->where('catering_member_id', $otherMember->id)->count())->toBe(30);
});

it('initialises a participant that joins the class after the month was first opened', function () {
    $schoolClass = autoSaveClass();
    $existing = autoSaveMember($schoolClass, 'Awal');
    autoSaveMatrix($schoolClass);

    $late = autoSaveMember($schoolClass, 'Bergabung');
    autoSaveMatrix($schoolClass);

    expect(CateringAttendance::query()->where('catering_member_id', $late->id)->count())->toBe(30)
        ->and(CateringAttendance::query()->where('catering_member_id', $existing->id)->count())->toBe(30);
});

it('initialises the employee group without needing a class', function () {
    $category = CateringCategory::factory()->employee()->create();
    $employee = CateringMember::factory()->withoutClass()->create([
        'name' => 'Pegawai',
        'catering_category_id' => $category->id,
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringAttendanceIndex::class)
        ->call('changeFilter', 'year', 2026)
        ->call('changeFilter', 'month', 9)
        ->call('changeFilter', 'participantGroup', CateringParticipantGroup::Employee->value)
        ->assertSet('matrixLoaded', true)
        ->assertHasNoErrors();

    expect(CateringAttendance::query()->where('catering_member_id', $employee->id)->count())->toBe(30);
});

it('initialises a different month without touching the first one', function () {
    $schoolClass = autoSaveClass();
    $member = autoSaveMember($schoolClass);
    $component = autoSaveMatrix($schoolClass);

    $component->call('changeFilter', 'month', 10);

    expect(CateringAttendance::query()->where('catering_member_id', $member->id)->count())->toBe(30 + 31)
        ->and(storedStatus($member->id, '2026-10-01'))->toBe(CateringAttendanceStatus::Ikut);
});

it('initialises a different year without touching the first one', function () {
    $schoolClass = autoSaveClass();
    $member = autoSaveMember($schoolClass);
    $component = autoSaveMatrix($schoolClass);

    $component->call('changeFilter', 'year', 2027);

    // The matrix is a month at a time, so a new year initialises that month's
    // September rather than the whole year.
    expect(CateringAttendance::query()->where('catering_member_id', $member->id)->count())->toBe(60)
        ->and(storedStatus($member->id, '2027-09-01'))->toBe(CateringAttendanceStatus::Ikut)
        ->and(storedStatus($member->id, '2026-09-01'))->toBe(CateringAttendanceStatus::Ikut);
});

it('does not initialise anything while the selection is still incomplete', function () {
    $schoolClass = autoSaveClass();
    autoSaveMember($schoolClass);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringAttendanceIndex::class)
        ->call('changeFilter', 'participantGroup', '')
        ->assertHasErrors(['participantGroup']);

    expect(CateringAttendance::query()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Single cell auto save
|--------------------------------------------------------------------------
*/

it('persists a single cell change immediately', function () {
    $schoolClass = autoSaveClass();
    $member = autoSaveMember($schoolClass);
    autoSaveMatrix($schoolClass);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringAttendanceIndex::class)
        ->call('changeFilter', 'participantGroup', CateringParticipantGroup::Student->value)
        ->call('changeFilter', 'year', 2026)
        ->call('changeFilter', 'month', 9)
        ->call('changeFilter', 'jenjang', 'SMP')
        ->call('changeFilter', 'schoolClassId', (string) $schoolClass->id)
        ->call('setCellStatus', $member->id, '2026-09-01', 'sakit')
        ->assertHasNoErrors();

    expect(storedStatus($member->id, '2026-09-01'))->toBe(CateringAttendanceStatus::Sakit);
});

it('auto saves tidak ikut immediately and keeps it after reload', function () {
    $schoolClass = autoSaveClass();
    $member = autoSaveMember($schoolClass);
    $user = User::factory()->admin()->create();

    autoSaveMatrix($schoolClass, $user)
        ->call('setCellStatus', $member->id, '2026-09-01', 'tidak_ikut')
        ->assertSet('saveState.saved', true)
        ->assertHasNoErrors();

    expect(storedStatus($member->id, '2026-09-01'))->toBe(CateringAttendanceStatus::TidakIkut)
        ->and(CateringAttendance::query()->where('catering_member_id', $member->id)->count())->toBe(30);

    autoSaveMatrix($schoolClass, $user)
        ->assertSet("attendance.{$member->id}.2026-09-01", 'tidak_ikut');
});

it('auto saves a manual non-billable status and keeps it after reload', function (CateringAttendanceStatus $status) {
    $schoolClass = autoSaveClass();
    $member = autoSaveMember($schoolClass);
    $user = User::factory()->admin()->create();

    autoSaveMatrix($schoolClass, $user)
        ->call('setCellStatus', $member->id, '2026-09-01', $status->value)
        ->assertSet('saveState.saved', true)
        ->assertHasNoErrors();

    expect(storedStatus($member->id, '2026-09-01'))->toBe($status);

    autoSaveMatrix($schoolClass, $user)
        ->assertSet("attendance.{$member->id}.2026-09-01", $status->value);
})->with([
    'ujian' => [CateringAttendanceStatus::Ujian],
    'event unit' => [CateringAttendanceStatus::EventUnit],
    'puasa' => [CateringAttendanceStatus::Puasa],
]);

it('does not need a manual save step to persist a cell', function () {
    $schoolClass = autoSaveClass();
    $member = autoSaveMember($schoolClass);

    expect(method_exists(CateringAttendanceIndex::class, 'save'))->toBeFalse();

    autoSaveMatrix($schoolClass)
        ->assertDontSee('Simpan Absensi');

    expect(storedRowsFor($member->id))->toHaveCount(30);
});

it('leaves every other cell untouched when one cell changes', function () {
    $schoolClass = autoSaveClass();
    $first = autoSaveMember($schoolClass, 'Satu');
    $second = autoSaveMember($schoolClass, 'Dua');
    $component = autoSaveMatrix($schoolClass);

    $before = storedRowsFor($second->id);
    $component->call('setCellStatus', $first->id, '2026-09-01', 'alfa');

    expect(storedRowsFor($second->id))->toBe($before)
        ->and(storedStatus($first->id, '2026-09-02'))->toBe(CateringAttendanceStatus::Ikut);
});

it('updates an already stored cell in place', function () {
    $schoolClass = autoSaveClass();
    $member = autoSaveMember($schoolClass);
    $component = autoSaveMatrix($schoolClass);

    $component->call('setCellStatus', $member->id, '2026-09-01', 'izin');
    $component->call('setCellStatus', $member->id, '2026-09-01', 'alfa');

    expect(CateringAttendance::query()->where('catering_member_id', $member->id)->count())->toBe(30)
        ->and(storedStatus($member->id, '2026-09-01'))->toBe(CateringAttendanceStatus::Alfa);
});

it('reports a saved state after a cell change', function () {
    $schoolClass = autoSaveClass();
    $member = autoSaveMember($schoolClass);

    autoSaveMatrix($schoolClass)
        ->assertSet('saveState.saved', false)
        ->assertSet('saving', false)
        ->call('setCellStatus', $member->id, '2026-09-01', 'sakit')
        ->assertSet('saveState.saved', true)
        ->assertSet('saving', false);
});

it('rejects a cell that is not part of the loaded matrix', function () {
    $schoolClass = autoSaveClass();
    $member = autoSaveMember($schoolClass);
    $component = autoSaveMatrix($schoolClass);

    $component->call('setCellStatus', $member->id, '2026-12-25', 'sakit')
        ->assertHasErrors(['attendance']);

    expect(storedStatus($member->id, '2026-12-25'))->toBeNull();
});

it('rejects a member that is not part of the loaded matrix', function () {
    $schoolClass = autoSaveClass();
    $otherClass = autoSaveClass('7B');
    $outsider = autoSaveMember($otherClass, 'Luar');
    autoSaveMatrix($schoolClass);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringAttendanceIndex::class)
        ->call('changeFilter', 'participantGroup', CateringParticipantGroup::Student->value)
        ->call('changeFilter', 'year', 2026)
        ->call('changeFilter', 'month', 9)
        ->call('changeFilter', 'jenjang', 'SMP')
        ->call('changeFilter', 'schoolClassId', (string) $schoolClass->id)
        ->call('setCellStatus', $outsider->id, '2026-09-01', 'sakit')
        ->assertHasErrors(['attendance']);

    expect(CateringAttendance::query()->where('catering_member_id', $outsider->id)->count())->toBe(30)
        ->and(storedStatus($outsider->id, '2026-09-01'))->toBe(CateringAttendanceStatus::Ikut);
});

it('rejects an unknown status without writing anything', function () {
    $schoolClass = autoSaveClass();
    $member = autoSaveMember($schoolClass);

    autoSaveMatrix($schoolClass)
        ->call('setCellStatus', $member->id, '2026-09-01', 'hadir-sebagian')
        ->assertHasErrors(['status']);

    expect(storedStatus($member->id, '2026-09-01'))->toBe(CateringAttendanceStatus::Ikut);
});

it('rejects a cell change from a guest', function () {
    $this->get(route('catering-attendance.index'))->assertRedirect(route('login'));
});

/*
|--------------------------------------------------------------------------
| Whole date auto save
|--------------------------------------------------------------------------
*/

it('persists every supported whole date status for every participant in the context', function (CateringAttendanceStatus $status) {
    $schoolClass = autoSaveClass();
    $members = CateringMember::factory()->count(3)->create(['school_class_id' => $schoolClass->id]);

    autoSaveMatrix($schoolClass)
        ->call('setDateStatus', '2026-09-01', $status->value)
        ->assertHasNoErrors();

    foreach ($members as $member) {
        expect(storedStatus($member->id, '2026-09-01'))->toBe($status);
    }
})->with(CateringAttendanceStatus::cases());

it('leaves the neighbouring dates untouched when a whole date changes', function () {
    $schoolClass = autoSaveClass();
    $member = autoSaveMember($schoolClass);

    autoSaveMatrix($schoolClass)
        ->call('setDateStatus', '2026-09-01', 'libur');

    expect(storedStatus($member->id, '2026-09-02'))->toBe(CateringAttendanceStatus::Ikut)
        ->and(storedStatus($member->id, '2026-09-03'))->toBe(CateringAttendanceStatus::Ikut);
});

it('does not create extra rows when a whole date is changed repeatedly', function () {
    $schoolClass = autoSaveClass();
    $member = autoSaveMember($schoolClass);
    $component = autoSaveMatrix($schoolClass);

    $component->call('setDateStatus', '2026-09-01', 'libur');
    $component->call('setDateStatus', '2026-09-01', 'ikut');
    $component->call('setDateStatus', '2026-09-01', 'libur');

    expect(CateringAttendance::query()->where('catering_member_id', $member->id)->count())->toBe(30)
        ->and(storedStatus($member->id, '2026-09-01'))->toBe(CateringAttendanceStatus::Libur);
});

it('rejects a date that is outside the loaded matrix', function () {
    $schoolClass = autoSaveClass();
    $member = autoSaveMember($schoolClass);

    autoSaveMatrix($schoolClass)
        ->call('setDateStatus', '2026-11-11', 'libur')
        ->assertHasErrors(['date']);

    expect(storedStatus($member->id, '2026-11-11'))->toBeNull();
});

it('rejects an unknown whole date status', function () {
    $schoolClass = autoSaveClass();
    $member = autoSaveMember($schoolClass);

    autoSaveMatrix($schoolClass)
        ->call('setDateStatus', '2026-09-01', 'hadir-sebagian')
        ->assertHasErrors(['status']);

    expect(storedStatus($member->id, '2026-09-01'))->toBe(CateringAttendanceStatus::Ikut);
});

it('allows an individual cell to override a whole date status', function () {
    $schoolClass = autoSaveClass();
    $members = CateringMember::factory()->count(2)->create(['school_class_id' => $schoolClass->id]);
    $component = autoSaveMatrix($schoolClass);

    $component
        ->call('setDateStatus', '2026-09-01', 'puasa')
        ->call('setCellStatus', $members->first()->id, '2026-09-01', 'sakit')
        ->assertHasNoErrors();

    expect(storedStatus($members->first()->id, '2026-09-01'))->toBe(CateringAttendanceStatus::Sakit)
        ->and(storedStatus($members->last()->id, '2026-09-01'))->toBe(CateringAttendanceStatus::Puasa);
});

/*
|--------------------------------------------------------------------------
| Failure handling
|--------------------------------------------------------------------------
*/

it('restores the previous value and warns when a cell cannot be written', function () {
    $schoolClass = autoSaveClass();
    $member = autoSaveMember($schoolClass);
    $component = autoSaveMatrix($schoolClass);

    Schema::drop('catering_attendances');

    $component->call('setCellStatus', $member->id, '2026-09-01', 'sakit')
        ->assertSet("attendance.{$member->id}.2026-09-01", 'ikut')
        ->assertSet('saveState.saved', false)
        ->assertSet('saving', false)
        ->assertDispatched(
            'toast',
            type: 'error',
            message: 'Absensi gagal disimpan. Perubahan dikembalikan, silakan coba lagi.',
        );
});

it('restores the whole date when a bulk change cannot be written', function () {
    $schoolClass = autoSaveClass();
    $member = autoSaveMember($schoolClass);
    $component = autoSaveMatrix($schoolClass);

    Schema::drop('catering_attendances');

    $component->call('setDateStatus', '2026-09-01', 'libur')
        ->assertSet("attendance.{$member->id}.2026-09-01", 'ikut')
        ->assertSet('saveState.saved', false)
        ->assertDispatched('toast', type: 'error');
});

/*
|--------------------------------------------------------------------------
| Query cost
|--------------------------------------------------------------------------
*/

it('initialises a large class without a query per participant or per day', function () {
    $schoolClass = autoSaveClass();
    CateringMember::factory()->count(30)->create(['school_class_id' => $schoolClass->id]);

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    autoSaveMatrix($schoolClass);

    // One participant query, one month read and one bulk insert, plus the handful
    // of filter lookups the filter bar needs. It must not scale with 30 x 30.
    expect($queries)->toBeLessThan(15)
        ->and(CateringAttendance::query()->count())->toBe(900);
});

it('changes one cell without a query per participant', function () {
    $schoolClass = autoSaveClass();
    $members = CateringMember::factory()->count(30)->create(['school_class_id' => $schoolClass->id]);
    $component = autoSaveMatrix($schoolClass);

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $component->call('setCellStatus', $members->first()->id, '2026-09-01', 'sakit');

    expect($queries)->toBeLessThan(5);
});

it('changes a whole date with a bounded number of queries', function () {
    $schoolClass = autoSaveClass();
    CateringMember::factory()->count(30)->create(['school_class_id' => $schoolClass->id]);
    $component = autoSaveMatrix($schoolClass);

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $component->call('setDateStatus', '2026-09-01', 'libur');

    expect($queries)->toBeLessThan(5);
});

/*
|--------------------------------------------------------------------------
| Downstream persisted attendance
|--------------------------------------------------------------------------
*/

it('feeds the requirement recap from initialised attendance', function () {
    $schoolClass = autoSaveClass();
    $student = autoSaveMember($schoolClass, 'Siswa Rekap');
    $employee = CateringMember::factory()->withoutClass()->create([
        'name' => 'Pegawai Rekap',
        'catering_category_id' => CateringCategory::factory()->employee()->create()->id,
    ]);

    $employeeMatrix = Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringAttendanceIndex::class)
        ->call('changeFilter', 'year', 2026)
        ->call('changeFilter', 'month', 9)
        ->call('changeFilter', 'participantGroup', CateringParticipantGroup::Employee->value)
        ->assertSet('matrixLoaded', true);

    $employeeMatrix->call('setCellStatus', $employee->id, '2026-09-30', 'alfa');

    autoSaveMatrix($schoolClass)
        ->call('setCellStatus', $student->id, '2026-09-30', 'alfa');

    $recap = Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringDashboardIndex::class)
        ->call('changeDate', '2026-09-30');

    // Both absences are stored, so neither is counted as a required portion.
    $recap->assertViewHas('recap', fn (array $recap): bool => $recap['totalPortions'] === 0
        && $recap['hasSavedData'] === true);
});

it('refreshes the recap on a poll while keeping an open detail modal', function () {
    $schoolClass = autoSaveClass();
    $student = autoSaveMember($schoolClass, 'Siswa Poll');
    $second = autoSaveMember($schoolClass, 'Siswa Tetap');
    autoSaveMatrix($schoolClass);

    $detailKey = 'class:'.$schoolClass->id;
    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringDashboardIndex::class)
        ->call('changeDate', '2026-09-30')
        ->call('openDetail', $detailKey)
        ->assertSet('detailKey', $detailKey);

    expect($component->get('recap')['totalPortions'])->toBe(2);

    markAbsentOn($student, '2026-09-30');

    $component->call('pollRecap')
        ->assertSet('detailKey', $detailKey)
        ->assertViewHas('recap', fn (array $recap): bool => $recap['totalPortions'] === 1);

    // The still counted participant is the one left in the refreshed modal.
    expect($component->get('detailRows'))->toHaveCount(1)
        ->and($component->get('detailRows')[0]['name'])->toBe($second->name);
});

it('closes a detail modal on a poll when its group no longer needs portions', function () {
    $schoolClass = autoSaveClass();
    $student = autoSaveMember($schoolClass, 'Siswa Hilang');
    autoSaveMatrix($schoolClass);

    $detailKey = 'class:'.$schoolClass->id;
    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringDashboardIndex::class)
        ->call('changeDate', '2026-09-30')
        ->call('openDetail', $detailKey)
        ->assertSet('detailKey', $detailKey);

    markAbsentOn($student, '2026-09-30');

    // The class drops out of the recap entirely, so the modal must not stay open.
    $component->call('pollRecap')
        ->assertSet('detailKey', null)
        ->assertViewHas('recap', fn (array $recap): bool => $recap['totalPortions'] === 0);
});

it('keeps a general employee detail modal open across a poll', function () {
    $first = CateringMember::factory()->withoutClass()->create([
        'name' => 'Pegawai A',
        'catering_category_id' => CateringCategory::factory()->employee()->create()->id,
    ]);
    $second = CateringMember::factory()->withoutClass()->create([
        'name' => 'Pegawai B',
        'catering_category_id' => CateringCategory::factory()->employee()->create()->id,
    ]);
    CateringEmployeeAssignment::factory()->general()->create(['catering_member_id' => $first->id]);
    CateringEmployeeAssignment::factory()->general()->create(['catering_member_id' => $second->id]);

    foreach ([$first, $second] as $employee) {
        CateringAttendance::factory()->for($employee)->create([
            'attendance_date' => '2026-09-30',
            'status' => CateringAttendanceStatus::Ikut,
        ]);
    }

    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringDashboardIndex::class)
        ->call('changeDate', '2026-09-30')
        ->call('openDetail', 'general')
        ->assertSet('detailKey', 'general');

    expect($component->get('recap')['totalPortions'])->toBe(2);

    $component->call('pollRecap')
        ->assertSet('detailKey', 'general')
        ->assertViewHas('recap', fn (array $recap): bool => $recap['totalPortions'] === 2);
});

/**
 * Mark a participant absent on a date the way the attendance page would.
 */
function markAbsentOn(CateringMember $member, string $date): void
{
    CateringAttendance::query()
        ->where('catering_member_id', $member->id)
        ->get()
        ->first(fn (CateringAttendance $row): bool => $row->attendance_date->isSameDay($date))
        ?->update(['status' => CateringAttendanceStatus::Alfa]);
}
