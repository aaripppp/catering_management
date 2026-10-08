<?php

use App\Enums\CateringAttendanceStatus;
use App\Enums\CateringParticipantGroup;
use App\Livewire\CateringAttendanceRecap\Index as CateringAttendanceRecap;
use App\Models\CateringAttendance;
use App\Models\CateringCategory;
use App\Models\CateringEmployeeAssignment;
use App\Models\CateringMember;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\CateringAttendanceRecapExportService;
use App\Services\CateringAttendanceRecapService;
use Carbon\CarbonImmutable;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;

/**
 * @param  array<int, CateringAttendanceStatus>  $statuses
 */
function storeRecapAttendance(CateringMember $member, array $statuses, int $year = 2026, int $month = 10): void
{
    $start = CarbonImmutable::create($year, $month, 1);

    foreach ($statuses as $index => $status) {
        CateringAttendance::factory()->for($member)->create([
            'attendance_date' => $start->addDays($index)->toDateString(),
            'status' => $status,
        ]);
    }
}

/**
 * @param  array<string, int|string>  $overrides
 * @return array{month: int, year: int, participantGroup: string, jenjang: string, schoolClassId: string, search: string}
 */
function recapFilters(array $overrides = []): array
{
    return array_replace([
        'month' => 10,
        'year' => 2026,
        'participantGroup' => '',
        'jenjang' => '',
        'schoolClassId' => '',
        'search' => '',
    ], $overrides);
}

function recapPdfText(string $bytes): string
{
    $text = '';

    if (preg_match_all('/stream\r?\n(.*?)endstream/s', $bytes, $matches) === false) {
        return $text;
    }

    foreach ($matches[1] as $stream) {
        $decoded = @gzuncompress($stream);

        if ($decoded === false || ! str_contains($decoded, 'Tf')) {
            continue;
        }

        if (preg_match_all('/\[(.*?)\]\s*TJ|\((.*?)\)\s*Tj/s', $decoded, $runs, PREG_SET_ORDER) === false) {
            continue;
        }

        foreach ($runs as $run) {
            $text .= $run[2] ?? '';

            if (isset($run[1]) && $run[1] !== '') {
                preg_match_all('/\((.*?)\)/s', $run[1], $fragments);
                $text .= implode('', $fragments[1]);
            }

            $text .= ' ';
        }
    }

    return trim((string) preg_replace('/\s+/', ' ', $text));
}

it('summarizes every stored attendance status in only the selected period', function () {
    $member = CateringMember::factory()->create();
    storeRecapAttendance($member, [
        CateringAttendanceStatus::Ikut,
        CateringAttendanceStatus::Ikut,
        CateringAttendanceStatus::Sakit,
        CateringAttendanceStatus::Izin,
        CateringAttendanceStatus::Alfa,
        CateringAttendanceStatus::TidakIkut,
        CateringAttendanceStatus::Ujian,
        CateringAttendanceStatus::EventUnit,
        CateringAttendanceStatus::Puasa,
        CateringAttendanceStatus::Libur,
    ]);
    storeRecapAttendance($member, [CateringAttendanceStatus::Ikut], year: 2026, month: 9);

    $summary = app(CateringAttendanceRecapService::class)->summary(recapFilters());

    expect($summary)->toMatchArray([
        'participants' => 1,
        'total_records' => 10,
        'ikut' => 2,
        'sakit' => 1,
        'izin' => 1,
        'alfa' => 1,
        'tidak_ikut' => 1,
        'ujian' => 1,
        'event_unit' => 1,
        'puasa' => 1,
        'libur' => 1,
    ]);
});

it('combines group jenjang class and participant name filters', function () {
    $class7A = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $class7B = SchoolClass::factory()->create(['name' => '7B', 'level' => '7']);
    $class5A = SchoolClass::factory()->create(['name' => '5A', 'level' => '5']);
    $studentCategory = CateringCategory::factory()->student()->create();
    $employeeCategory = CateringCategory::factory()->employee()->create();
    $ahmad = CateringMember::factory()->create(['name' => 'Ahmad', 'school_class_id' => $class7A->id, 'catering_category_id' => $studentCategory->id]);
    $budi = CateringMember::factory()->create(['name' => 'Budi', 'school_class_id' => $class7B->id, 'catering_category_id' => $studentCategory->id]);
    $citra = CateringMember::factory()->create(['name' => 'Citra', 'school_class_id' => $class5A->id, 'catering_category_id' => $studentCategory->id]);
    $employee = CateringMember::factory()->withoutClass()->create(['name' => 'Ahmad Pegawai', 'catering_category_id' => $employeeCategory->id]);

    foreach ([$ahmad, $budi, $citra, $employee] as $member) {
        storeRecapAttendance($member, [CateringAttendanceStatus::Ikut]);
    }

    $rows = app(CateringAttendanceRecapService::class)->participantRows(recapFilters([
        'participantGroup' => CateringParticipantGroup::Student->value,
        'jenjang' => 'SMP',
        'schoolClassId' => (string) $class7A->id,
        'search' => 'Ahmad',
    ]));

    expect($rows->pluck('member_name')->all())->toBe(['Ahmad']);
});

it('aggregates participant statuses and includes inactive historical participants', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $member = CateringMember::factory()->inactive()->create([
        'name' => 'Ahmad',
        'school_class_id' => $schoolClass->id,
    ]);
    storeRecapAttendance($member, [
        ...array_fill(0, 20, CateringAttendanceStatus::Ikut),
        CateringAttendanceStatus::Sakit,
        CateringAttendanceStatus::Sakit,
        CateringAttendanceStatus::Izin,
        ...array_fill(0, 8, CateringAttendanceStatus::Libur),
    ]);

    $row = app(CateringAttendanceRecapService::class)->participantRows(recapFilters())->sole();

    expect($row['member_name'])->toBe('Ahmad')
        ->and($row['is_active'])->toBeFalse()
        ->and($row['ikut'])->toBe(20)
        ->and($row['sakit'])->toBe(2)
        ->and($row['izin'])->toBe(1)
        ->and($row['libur'])->toBe(8)
        ->and($row['total_days'])->toBe(31);
});

it('groups student totals by class in natural school order without mixing classes', function () {
    $class10A = SchoolClass::factory()->create(['name' => '10A', 'level' => '10']);
    $class2A = SchoolClass::factory()->create(['name' => '2A', 'level' => '2']);
    $category = CateringCategory::factory()->student()->create();
    $grade10 = CateringMember::factory()->create(['school_class_id' => $class10A->id, 'catering_category_id' => $category->id]);
    $grade2 = CateringMember::factory()->create(['school_class_id' => $class2A->id, 'catering_category_id' => $category->id]);
    storeRecapAttendance($grade10, [CateringAttendanceStatus::Sakit]);
    storeRecapAttendance($grade2, [CateringAttendanceStatus::Ikut, CateringAttendanceStatus::Ikut]);

    $groups = app(CateringAttendanceRecapService::class)->groupRows(recapFilters([
        'participantGroup' => CateringParticipantGroup::Student->value,
    ]));

    expect(array_column($groups, 'label'))->toBe(['2A', '10A'])
        ->and($groups[0]['participant_count'])->toBe(1)
        ->and($groups[0]['ikut'])->toBe(2)
        ->and($groups[0]['sakit'])->toBe(0)
        ->and($groups[1]['ikut'])->toBe(0)
        ->and($groups[1]['sakit'])->toBe(1);
});

it('groups employees by their assignment instead of student class', function () {
    $employeeClass = SchoolClass::factory()->create(['name' => 'Kantor', 'level' => '7']);
    $category = CateringCategory::factory()->employee()->create(['name' => 'Guru']);
    $placed = CateringMember::factory()->withoutClass()->create(['catering_category_id' => $category->id]);
    $general = CateringMember::factory()->withoutClass()->create(['catering_category_id' => $category->id]);
    CateringEmployeeAssignment::factory()->for($placed)->schoolClass($employeeClass)->create();
    CateringEmployeeAssignment::factory()->for($general)->general()->create();
    storeRecapAttendance($placed, [CateringAttendanceStatus::Ikut]);
    storeRecapAttendance($general, [CateringAttendanceStatus::Izin]);

    $groups = app(CateringAttendanceRecapService::class)->groupRows(recapFilters([
        'participantGroup' => CateringParticipantGroup::Employee->value,
    ]));

    expect(array_column($groups, 'label'))->toBe(['Kelas · Kantor', 'Umum']);
});

it('protects the recap page and allows attendance roles to view it', function () {
    $this->get(route('catering-attendance-recap.index'))
        ->assertRedirect(route('login'));

    $this->get(route('catering-attendance-recap.preview', recapFilters()))
        ->assertRedirect(route('login'));

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('catering-attendance-recap.index'))
        ->assertSeeLivewire(CateringAttendanceRecap::class);

    $this->actingAs(User::factory()->waliKelas()->create())
        ->get(route('catering-attendance-recap.index'))
        ->assertSeeLivewire(CateringAttendanceRecap::class);
});

it('renders each recap tab and applies participant filters without changing attendance', function () {
    $user = User::factory()->waliKelas()->create();
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $category = CateringCategory::factory()->student()->create();
    $ahmad = CateringMember::factory()->create([
        'name' => 'Ahmad',
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);
    $budi = CateringMember::factory()->create([
        'name' => 'Budi',
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);
    storeRecapAttendance($ahmad, [CateringAttendanceStatus::Ikut, CateringAttendanceStatus::Sakit]);
    storeRecapAttendance($budi, [CateringAttendanceStatus::Izin]);
    $attendanceBefore = CateringAttendance::query()->orderBy('id')->get()->toArray();

    Livewire::actingAs($user)
        ->test(CateringAttendanceRecap::class)
        ->set('month', 10)
        ->set('year', 2026)
        ->assertSee('Peserta Tercatat')
        ->assertSee('3 catatan absensi')
        ->call('setTab', 'participants')
        ->assertSee('Ahmad')
        ->assertSee('Budi')
        ->set('participantGroup', CateringParticipantGroup::Student->value)
        ->set('jenjang', 'SMP')
        ->set('schoolClassId', (string) $schoolClass->id)
        ->set('search', 'Ahmad')
        ->assertSee('Ahmad')
        ->assertDontSee('Budi')
        ->call('setTab', 'groups')
        ->assertSee('7A');

    expect(CateringAttendance::query()->orderBy('id')->get()->toArray())->toBe($attendanceBefore);
});

it('resets recap filters to the current period', function () {
    $user = User::factory()->admin()->create();

    Livewire::actingAs($user)
        ->test(CateringAttendanceRecap::class)
        ->set('month', 1)
        ->set('year', 2020)
        ->set('participantGroup', CateringParticipantGroup::Student->value)
        ->set('jenjang', 'SMP')
        ->set('schoolClassId', '99')
        ->set('search', 'Ahmad')
        ->call('resetFilters')
        ->assertSet('month', now()->month)
        ->assertSet('year', now()->year)
        ->assertSet('participantGroup', '')
        ->assertSet('jenjang', '')
        ->assertSet('schoolClassId', '')
        ->assertSet('search', '');
});

it('rejects invalid export filters', function () {
    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringAttendanceRecap::class)
        ->set('month', 13)
        ->call('downloadExcel')
        ->assertHasErrors(['month' => 'between']);
});

it('links the current filters to a new tab pdf preview', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $filters = recapFilters([
        'participantGroup' => CateringParticipantGroup::Student->value,
        'jenjang' => 'SMP',
        'schoolClassId' => (string) $schoolClass->id,
        'search' => 'Ahmad',
    ]);

    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringAttendanceRecap::class)
        ->set('month', $filters['month'])
        ->set('year', $filters['year'])
        ->set('participantGroup', $filters['participantGroup'])
        ->set('jenjang', $filters['jenjang'])
        ->set('schoolClassId', $filters['schoolClassId'])
        ->set('search', $filters['search']);
    $markup = html_entity_decode($component->html(), ENT_QUOTES | ENT_HTML5);

    expect($markup)->toContain('target="_blank"')
        ->and($markup)->toContain('Preview PDF')
        ->and($markup)->toContain(route('catering-attendance-recap.preview', $filters));
});

it('renders the filtered recap pdf inline', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $category = CateringCategory::factory()->student()->create();
    $ahmad = CateringMember::factory()->create([
        'name' => 'Ahmad Preview',
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);
    $budi = CateringMember::factory()->create([
        'name' => 'Budi Hidden',
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);
    storeRecapAttendance($ahmad, [CateringAttendanceStatus::Ikut]);
    storeRecapAttendance($budi, [CateringAttendanceStatus::Sakit]);

    $response = $this->actingAs(User::factory()->waliKelas()->create())
        ->get(route('catering-attendance-recap.preview', recapFilters([
            'participantGroup' => CateringParticipantGroup::Student->value,
            'jenjang' => 'SMP',
            'schoolClassId' => (string) $schoolClass->id,
            'search' => 'Ahmad',
        ])));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename="rekap-absensi-catering-oktober-2026.pdf"');

    $pdf = $response->streamedContent();
    $text = recapPdfText($pdf);

    expect($pdf)->toStartWith('%PDF-')
        ->and($text)->toContain('Ahmad Preview')
        ->and($text)->not->toContain('Budi Hidden');
});

it('keeps the excel export as a direct download', function () {
    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringAttendanceRecap::class)
        ->set('month', 10)
        ->set('year', 2026)
        ->call('downloadExcel')
        ->assertFileDownloaded('rekap-absensi-catering-oktober-2026.xlsx');
});

it('exports filtered recap data to xlsx and pdf without changing attendance', function () {
    $member = CateringMember::factory()->create(['name' => 'Ahmad Export']);
    storeRecapAttendance($member, [CateringAttendanceStatus::Ikut, CateringAttendanceStatus::Sakit]);
    $attendanceBefore = CateringAttendance::query()->orderBy('id')->get()->toArray();
    $exportService = app(CateringAttendanceRecapExportService::class);
    $filters = recapFilters(['search' => 'Ahmad Export']);

    $path = $exportService->createExcel($filters);
    $reader = new Reader;
    $reader->open($path);
    $sheets = [];

    try {
        foreach ($reader->getSheetIterator() as $sheet) {
            $sheets[$sheet->getName()] = collect(iterator_to_array($sheet->getRowIterator()))
                ->map(fn (Row $row): array => $row->toArray())
                ->all();
        }
    } finally {
        $reader->close();
        @unlink($path);
    }

    $pdf = $exportService->renderPdf($filters);

    expect(array_keys($sheets))->toBe(['Rekap Per Peserta', 'Per Kelas-Kelompok', 'Ringkasan'])
        ->and($sheets['Rekap Per Peserta'][2][1])->toBe('Ahmad Export')
        ->and($sheets['Rekap Per Peserta'][2][3])->toBe(1)
        ->and($sheets['Rekap Per Peserta'][2][4])->toBe(1)
        ->and($pdf)->toStartWith('%PDF-');
    expect(CateringAttendance::query()->orderBy('id')->get()->toArray())->toBe($attendanceBefore);
});
