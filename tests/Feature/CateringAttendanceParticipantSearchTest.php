<?php

use App\Enums\CateringAttendanceStatus;
use App\Enums\CateringParticipantGroup;
use App\Livewire\CateringAttendance\Index as CateringAttendanceIndex;
use App\Models\CateringAttendance;
use App\Models\CateringCategory;
use App\Models\CateringEmployeeAssignment;
use App\Models\CateringMember;
use App\Models\CateringMemberPeriod;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function attendanceParticipantSearchComponent(?User $user = null): Testable
{
    return Livewire::actingAs($user ?? User::factory()->admin()->create())
        ->test(CateringAttendanceIndex::class)
        ->call('changeFilter', 'year', 2026)
        ->call('changeFilter', 'month', 9);
}

function attendanceParticipantSearchStatus(int $memberId, string $date): ?CateringAttendanceStatus
{
    return CateringAttendance::query()
        ->where('catering_member_id', $memberId)
        ->get()
        ->first(fn (CateringAttendance $attendance): bool => $attendance->attendance_date->isSameDay($date))
        ?->status;
}

/**
 * Give a member an open participation period from the start of September 2026.
 */
function giveSearchAttendancePeriod(CateringMember $member, string $start = '2026-09-01'): void
{
    CateringMemberPeriod::create([
        'catering_member_id' => $member->id,
        'start_date' => $start,
        'end_date' => null,
    ]);
}

it('searches eligible students and employees globally without class filters', function () {
    $studentCategory = CateringCategory::factory()->student()->create(['name' => 'Siswa Umum']);
    $employeeCategory = CateringCategory::factory()->employee()->create(['name' => 'Guru']);
    $studentClass = SchoolClass::factory()->create(['name' => '9D', 'level' => '9']);
    $student = CateringMember::factory()->create([
        'name' => 'Alyssa Talitha Arifin',
        'school_class_id' => $studentClass->id,
        'catering_category_id' => $studentCategory->id,
    ]);
    $employee = CateringMember::factory()->withoutClass()->create([
        'name' => 'Alyssa Pegawai',
        'catering_category_id' => $employeeCategory->id,
    ]);
    CateringEmployeeAssignment::factory()->for($employee)->general()->create();
    $inactive = CateringMember::factory()->inactive()->create([
        'name' => 'Alyssa Nonaktif',
        'catering_category_id' => $studentCategory->id,
    ]);
    $ineligibleCategory = CateringCategory::factory()->create();
    DB::table('catering_categories')
        ->where('id', $ineligibleCategory->id)
        ->update(['participant_group' => 'tidak_valid']);
    $uncategorized = CateringMember::factory()->create([
        'name' => 'Alyssa Tanpa Kategori Valid',
        'catering_category_id' => $ineligibleCategory->id,
    ]);

    attendanceParticipantSearchComponent()
        ->set('participantSearch', '  Alyssa  ')
        ->assertViewHas('participantSearchResults', function (array $results) use ($student, $employee, $inactive, $uncategorized): bool {
            $ids = array_column($results, 'id');

            return in_array($student->id, $ids, true)
                && in_array($employee->id, $ids, true)
                && ! in_array($inactive->id, $ids, true)
                && ! in_array($uncategorized->id, $ids, true);
        })
        ->assertSeeHtml('9D &middot; Siswa Umum')
        ->assertSeeHtml('Umum &middot; Guru')
        ->assertDontSee('Alyssa Nonaktif')
        ->assertDontSee('Alyssa Tanpa Kategori Valid')
        ->call('selectParticipant', $inactive->id)
        ->assertHasErrors(['participantSearch']);
});

it('enforces minimum search length and escapes wildcard-only input', function () {
    CateringMember::factory()->create(['name' => 'Persen Tidak Cocok']);

    $component = attendanceParticipantSearchComponent()
        ->set('participantSearch', 'P')
        ->assertViewHas('participantSearchResults', [])
        ->set('participantSearch', '  ')
        ->assertViewHas('participantSearchResults', [])
        ->set('participantSearch', '%%')
        ->assertViewHas('participantSearchResults', [])
        ->assertSee('Peserta tidak ditemukan.');

    $component->set('participantSearch', '__')
        ->assertViewHas('participantSearchResults', []);
});

it('limits search results with deterministic name and id ordering', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $category = CateringCategory::factory()->student()->create();

    foreach (range(12, 1) as $number) {
        CateringMember::factory()->create([
            'name' => sprintf('Peserta Cari %02d', $number),
            'school_class_id' => $schoolClass->id,
            'catering_category_id' => $category->id,
        ]);
    }

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    attendanceParticipantSearchComponent()
        ->set('participantSearch', 'Peserta Cari')
        ->assertViewHas('participantSearchResults', function (array $results): bool {
            return count($results) === 10
                && array_column($results, 'name') === array_map(
                    fn (int $number): string => sprintf('Peserta Cari %02d', $number),
                    range(1, 10),
                );
        });

    expect($queries)->toBeLessThan(20);
});

it('selects duplicate names by id and preserves the existing filters', function () {
    $category = CateringCategory::factory()->student()->create(['name' => 'Anak Guru']);
    $originalClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $targetClass = SchoolClass::factory()->create(['name' => '9D', 'level' => '9']);
    CateringMember::factory()->create([
        'name' => 'Alyssa Talitha Arifin',
        'school_class_id' => $originalClass->id,
        'catering_category_id' => $category->id,
    ]);
    $target = CateringMember::factory()->create([
        'name' => 'Alyssa Talitha Arifin',
        'school_class_id' => $targetClass->id,
        'catering_category_id' => $category->id,
    ]);

    $component = attendanceParticipantSearchComponent()
        ->call('changeFilter', 'jenjang', 'SMP')
        ->call('changeFilter', 'schoolClassId', (string) $originalClass->id)
        ->set('participantSearch', 'Alyssa')
        ->call('selectParticipant', $target->id)
        ->assertHasNoErrors()
        ->assertSet('selectedParticipantId', $target->id)
        ->assertSet('participantSearch', '')
        ->assertSet('participantGroup', CateringParticipantGroup::Student->value)
        ->assertSet('jenjang', 'SMP')
        ->assertSet('schoolClassId', (string) $originalClass->id)
        ->assertSet('participants', fn (array $participants): bool => array_column($participants, 'id') === [$target->id])
        ->assertSet('selectedParticipant.context_label', '9D')
        ->assertSet('selectedParticipant.category_name', 'Anak Guru')
        ->assertSee('Peserta dipilih')
        ->assertSeeHtml('9D &middot; Anak Guru')
        ->assertDontSee('Cetak Invoice Kelas')
        ->assertDontSee('Download Semua Invoice');

    expect($component->instance()->memberInvoiceUrl($target->id))->toBe(route('catering-attendance.invoice.member', [
        'member' => $target->id,
        'month' => 9,
        'year' => 2026,
        'participantGroup' => CateringParticipantGroup::Student->value,
        'schoolClassId' => $targetClass->id,
    ]));
});

it('selects an employee while the visible group remains student', function () {
    $employeeCategory = CateringCategory::factory()->employee()->create(['name' => 'Guru']);
    $employee = CateringMember::factory()->withoutClass()->create([
        'name' => 'Budi Santoso',
        'catering_category_id' => $employeeCategory->id,
    ]);
    CateringEmployeeAssignment::factory()->for($employee)->general()->create();

    attendanceParticipantSearchComponent()
        ->set('participantSearch', 'Budi')
        ->call('selectParticipant', $employee->id)
        ->assertHasNoErrors()
        ->assertSet('participantGroup', CateringParticipantGroup::Student->value)
        ->assertSet('selectedParticipantId', $employee->id)
        ->assertSet('selectedParticipant.context_label', 'Umum')
        ->assertSet('participants', fn (array $participants): bool => array_column($participants, 'id') === [$employee->id])
        ->assertSeeHtml('Umum &middot; Guru');
});

it('loads a student without a class using the current category context', function () {
    $category = CateringCategory::factory()->student()->create(['name' => 'Kategori Lama']);
    $member = CateringMember::factory()->withoutClass()->create([
        'name' => 'Siswa Tanpa Kelas',
        'catering_category_id' => $category->id,
    ]);
    $category->update(['name' => 'Kategori Terkini']);

    attendanceParticipantSearchComponent()
        ->set('participantSearch', 'Tanpa Kelas')
        ->assertSeeHtml('Tanpa kelas &middot; Kategori Terkini')
        ->call('selectParticipant', $member->id)
        ->assertSet('selectedParticipantId', $member->id)
        ->assertSet('selectedParticipant.context_label', 'Tanpa kelas')
        ->assertSet('selectedParticipant.category_name', 'Kategori Terkini')
        ->assertSet('participants', fn (array $participants): bool => array_column($participants, 'id') === [$member->id]);
});

it('reuses cell autosave bulk date updates statuses and summaries for the selected participant', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '8C', 'level' => '8']);
    $selected = CateringMember::factory()->create(['school_class_id' => $schoolClass->id]);
    $other = CateringMember::factory()->create(['school_class_id' => $schoolClass->id]);
    giveSearchAttendancePeriod($selected);
    giveSearchAttendancePeriod($other);

    attendanceParticipantSearchComponent()
        ->call('selectParticipant', $selected->id)
        ->call('setCellStatus', $selected->id, '2026-09-01', CateringAttendanceStatus::Sakit->value)
        ->assertSet("attendance.{$selected->id}.2026-09-01", CateringAttendanceStatus::Sakit->value)
        ->call('setDateStatus', '2026-09-02', CateringAttendanceStatus::Izin->value)
        ->assertSet("attendance.{$selected->id}.2026-09-02", CateringAttendanceStatus::Izin->value)
        ->assertViewHas('summary', fn (array $summary): bool => $summary['participants'] === 1
            && $summary['sakit'] === 1
            && $summary['izin'] === 1)
        ->assertSee('Aktif')
        ->assertSee('Sakit')
        ->assertSee('Izin')
        ->assertSee('Budaya Makan')
        ->assertSee('Off')
        ->assertSee('Ujian')
        ->assertSee('Event Unit')
        ->assertSee('Puasa')
        ->assertSee('Libur');

    expect(attendanceParticipantSearchStatus($selected->id, '2026-09-01'))->toBe(CateringAttendanceStatus::Sakit)
        ->and(attendanceParticipantSearchStatus($selected->id, '2026-09-02'))->toBe(CateringAttendanceStatus::Izin)
        ->and(attendanceParticipantSearchStatus($other->id, '2026-09-02'))->toBe(CateringAttendanceStatus::Ikut);
});

it('rejects autosave for a participant outside the selected matrix', function () {
    $selected = CateringMember::factory()->create();
    $outside = CateringMember::factory()->create();
    giveSearchAttendancePeriod($selected);
    giveSearchAttendancePeriod($outside);

    attendanceParticipantSearchComponent()
        ->call('selectParticipant', $selected->id)
        ->call('setCellStatus', $outside->id, '2026-09-01', CateringAttendanceStatus::Sakit->value)
        ->assertHasErrors(['attendance']);

    expect(attendanceParticipantSearchStatus($outside->id, '2026-09-01'))->toBe(CateringAttendanceStatus::Ikut);
});

it('retains the selected participant when month and year change', function () {
    $member = CateringMember::factory()->create();

    attendanceParticipantSearchComponent()
        ->call('selectParticipant', $member->id)
        ->call('changeFilter', 'month', 10)
        ->assertSet('selectedParticipantId', $member->id)
        ->assertSet('loadedMonth', 10)
        ->assertSet('participants', fn (array $participants): bool => array_column($participants, 'id') === [$member->id])
        ->call('changeFilter', 'year', 2027)
        ->assertSet('selectedParticipantId', $member->id)
        ->assertSet('loadedYear', 2027)
        ->assertSet('participants', fn (array $participants): bool => array_column($participants, 'id') === [$member->id]);
});

it('exits selected mode when normal scope filters change', function () {
    $member = CateringMember::factory()->create();
    $otherClass = SchoolClass::factory()->create(['name' => '8A', 'level' => '8']);
    $component = attendanceParticipantSearchComponent();

    $component->call('selectParticipant', $member->id)
        ->call('changeFilter', 'participantGroup', CateringParticipantGroup::Employee->value)
        ->assertSet('selectedParticipantId', null);

    $component->call('selectParticipant', $member->id)
        ->call('changeFilter', 'jenjang', 'SMP')
        ->assertSet('selectedParticipantId', null);

    $component->call('selectParticipant', $member->id)
        ->call('changeFilter', 'schoolClassId', (string) $otherClass->id)
        ->assertSet('selectedParticipantId', null);
});

it('clears selection back to the preserved class matrix', function () {
    $category = CateringCategory::factory()->student()->create();
    $preservedClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $otherClass = SchoolClass::factory()->create(['name' => '9D', 'level' => '9']);
    $classMember = CateringMember::factory()->create([
        'name' => 'Peserta Kelas Awal',
        'school_class_id' => $preservedClass->id,
        'catering_category_id' => $category->id,
    ]);
    $searchedMember = CateringMember::factory()->create([
        'name' => 'Peserta Hasil Cari',
        'school_class_id' => $otherClass->id,
        'catering_category_id' => $category->id,
    ]);
    giveSearchAttendancePeriod($classMember);

    attendanceParticipantSearchComponent()
        ->call('changeFilter', 'jenjang', 'SMP')
        ->call('changeFilter', 'schoolClassId', (string) $preservedClass->id)
        ->call('selectParticipant', $searchedMember->id)
        ->call('clearSelectedParticipant')
        ->assertSet('selectedParticipantId', null)
        ->assertSet('participantSearch', '')
        ->assertSet('schoolClassId', (string) $preservedClass->id)
        ->assertSet('matrixLoaded', true)
        ->assertSet('participants', fn (array $participants): bool => array_column($participants, 'id') === [$classMember->id]);
});

it('clears selection to guidance when normal filters are incomplete', function () {
    $member = CateringMember::factory()->create();

    attendanceParticipantSearchComponent()
        ->call('selectParticipant', $member->id)
        ->call('clearSelectedParticipant')
        ->assertSet('selectedParticipantId', null)
        ->assertSet('matrixLoaded', false)
        ->assertSet('participants', [])
        ->assertSee('Pilih jenjang dan kelas terlebih dahulu.');
});

it('does not disturb a class matrix when only search text is cleared', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $member = CateringMember::factory()->create(['school_class_id' => $schoolClass->id]);
    giveSearchAttendancePeriod($member);

    attendanceParticipantSearchComponent()
        ->call('changeFilter', 'jenjang', 'SMP')
        ->call('changeFilter', 'schoolClassId', (string) $schoolClass->id)
        ->set('participantSearch', 'Tidak Ada')
        ->set('participantSearch', '')
        ->assertSet('matrixLoaded', true)
        ->assertSet('participants', fn (array $participants): bool => array_column($participants, 'id') === [$member->id]);
});

it('keeps generation global while retaining the selected participant matrix', function () {
    $selected = CateringMember::factory()->create();
    giveSearchAttendancePeriod($selected);
    $component = attendanceParticipantSearchComponent()
        ->call('selectParticipant', $selected->id);
    $lateParticipant = CateringMember::factory()->create();
    giveSearchAttendancePeriod($lateParticipant);

    $component->call('generateAttendance')
        ->assertSet('selectedParticipantId', $selected->id)
        ->assertSet('participants', fn (array $participants): bool => array_column($participants, 'id') === [$selected->id]);

    expect(CateringAttendance::query()->where('catering_member_id', $lateParticipant->id)->count())->toBe(30);
});

it('clears a selected participant that becomes inactive', function () {
    $member = CateringMember::factory()->create();
    $component = attendanceParticipantSearchComponent()
        ->call('selectParticipant', $member->id);
    $member->update(['is_active' => false]);

    $component->call('changeFilter', 'month', 10)
        ->assertSet('selectedParticipantId', null)
        ->assertSet('selectedParticipant', null)
        ->assertSet('matrixLoaded', false)
        ->assertDispatched(
            'toast',
            type: 'warning',
            message: 'Peserta yang dipilih sudah tidak aktif atau tidak tersedia.',
        );
});

it('preserves admin and wali kelas access', function () {
    $member = CateringMember::factory()->create();

    foreach ([User::factory()->admin()->create(), User::factory()->waliKelas()->create()] as $user) {
        attendanceParticipantSearchComponent($user)
            ->set('participantSearch', mb_substr($member->name, 0, 3))
            ->call('selectParticipant', $member->id)
            ->assertSet('selectedParticipantId', $member->id)
            ->assertHasNoErrors();
    }
});

it('rejects guests before they can search or select participants', function () {
    $this->get(route('catering-attendance.index'))->assertRedirect(route('login'));
    Livewire::test(CateringAttendanceIndex::class)->assertStatus(403);
});
