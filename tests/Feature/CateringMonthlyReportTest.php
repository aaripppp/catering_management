<?php

use App\Enums\CateringBillStatus;
use App\Enums\CateringParticipantGroup;
use App\Livewire\CateringMonthlyReport\Index as CateringMonthlyReport;
use App\Models\CateringBill;
use App\Models\CateringCategory;
use App\Models\CateringMember;
use App\Models\CateringPayment;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\CateringMonthlyReportExportService;
use App\Services\CateringMonthlyReportService;
use App\Services\CateringPaymentService;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;

/**
 * @param  array<string, int|string>  $overrides
 * @return array{month: int, year: int, participantGroup: string, jenjang: string, schoolClassId: string}
 */
function monthlyReportFilters(array $overrides = []): array
{
    return array_replace([
        'month' => 10,
        'year' => 2026,
        'participantGroup' => '',
        'jenjang' => '',
        'schoolClassId' => '',
    ], $overrides);
}

/**
 * @return array{class2A: SchoolClass, class10A: SchoolClass, octoberCreditBill: CateringBill, partialBill: CateringBill, unpaidBill: CateringBill, employeeBill: CateringBill, overpaidBill: CateringBill}
 */
function monthlyReportScenario(): array
{
    $class2A = SchoolClass::factory()->create(['name' => '2A', 'level' => '2']);
    $class10A = SchoolClass::factory()->create(['name' => '10A', 'level' => '10']);
    $creditMember = CateringMember::factory()->create(['school_class_id' => $class2A->id]);
    $sourceBill = CateringBill::factory()->create([
        'catering_member_id' => $creditMember->id,
        'school_class_id' => $class2A->id,
        'school_class_name' => '2A',
        'school_level' => '2',
        'period_month' => 9,
        'gross_amount' => 400000,
    ]);
    $octoberCreditBill = CateringBill::factory()->create([
        'catering_member_id' => $creditMember->id,
        'school_class_id' => $class2A->id,
        'school_class_name' => '2A',
        'school_level' => '2',
        'member_name' => 'Aisyah Kredit',
        'gross_amount' => 400000,
    ]);
    $partialBill = CateringBill::factory()->create([
        'school_class_id' => $class2A->id,
        'school_class_name' => '2A',
        'school_level' => '2',
        'member_name' => 'Budi Sebagian',
        'category_name' => 'Siswa Beasiswa',
        'gross_amount' => 400000,
    ]);
    $unpaidBill = CateringBill::factory()->create([
        'school_class_id' => $class10A->id,
        'school_class_name' => '10A',
        'school_level' => '10',
        'member_name' => 'Citra Belum Bayar',
        'gross_amount' => 400000,
    ]);
    $employeeBill = CateringBill::factory()->create([
        'school_class_id' => null,
        'school_class_name' => null,
        'school_level' => null,
        'participant_group' => CateringParticipantGroup::Employee,
        'category_name' => 'Pegawai Umum',
        'member_name' => 'Dedi Pegawai',
        'gross_amount' => 200000,
    ]);
    $overpaidBill = CateringBill::factory()->create([
        'school_class_id' => $class10A->id,
        'school_class_name' => '10A',
        'school_level' => '10',
        'member_name' => 'Eka Lebih Bayar',
        'gross_amount' => 400000,
    ]);
    $paymentService = app(CateringPaymentService::class);
    $paymentService->record($sourceBill, 500000);
    $paymentService->record($octoberCreditBill, 300000);
    $paymentService->record($partialBill, 200000);
    $paymentService->record($employeeBill, 200000);
    $paymentService->record($overpaidBill, 500000);

    return compact('class2A', 'class10A', 'octoberCreditBill', 'partialBill', 'unpaidBill', 'employeeBill', 'overpaidBill');
}

function monthlyReportPdfText(string $bytes): string
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
            $fragment = $run[2] ?? '';

            if (isset($run[1]) && $run[1] !== '') {
                preg_match_all('/\((.*?)\)/s', $run[1], $fragments);
                $fragment .= implode('', $fragments[1]);
            }

            $text .= (str_contains($fragment, "\0") ? mb_convert_encoding($fragment, 'UTF-8', 'UTF-16BE') : $fragment).' ';
        }
    }

    return trim((string) preg_replace('/\s+/', ' ', $text));
}

function monthlyReportStatisticsMarkup(Testable $component): string
{
    $html = html_entity_decode($component->html(), ENT_QUOTES | ENT_HTML5);
    preg_match('/<section id="student-payment-statistics".*?<\/section>/s', $html, $matches);

    return $matches[0] ?? '';
}

function monthlyReportStatisticsText(Testable $component): string
{
    return trim((string) preg_replace('/\s+/', ' ', strip_tags(monthlyReportStatisticsMarkup($component))));
}

it('summarizes monthly bill cash credit outstanding overpayment and statuses separately', function () {
    monthlyReportScenario();

    $summary = app(CateringMonthlyReportService::class)->summary(monthlyReportFilters());

    expect($summary)->toBe([
        'bill_count' => 5,
        'gross_amount' => 1800000,
        'money_in' => 1200000,
        'credit_used' => 100000,
        'paid_amount' => 1300000,
        'outstanding_amount' => 600000,
        'overpayment_amount' => 100000,
        'paid_count' => 3,
        'partial_count' => 1,
        'unpaid_count' => 1,
    ]);
});

it('applies period group jenjang and class filters to the same financial scope', function () {
    ['class2A' => $class2A] = monthlyReportScenario();

    $summary = app(CateringMonthlyReportService::class)->summary(monthlyReportFilters([
        'participantGroup' => CateringParticipantGroup::Student->value,
        'jenjang' => 'SD',
        'schoolClassId' => (string) $class2A->id,
    ]));

    expect($summary)->toMatchArray([
        'bill_count' => 2,
        'gross_amount' => 800000,
        'money_in' => 500000,
        'credit_used' => 100000,
        'outstanding_amount' => 200000,
        'overpayment_amount' => 0,
        'paid_count' => 1,
        'partial_count' => 1,
        'unpaid_count' => 0,
    ]);

    expect(app(CateringMonthlyReportService::class)->summary(monthlyReportFilters(['year' => 2025]))['bill_count'])->toBe(0)
        ->and(app(CateringMonthlyReportService::class)->summary(monthlyReportFilters(['month' => 9]))['bill_count'])->toBe(1)
        ->and(app(CateringMonthlyReportService::class)->summary(monthlyReportFilters([
            'participantGroup' => CateringParticipantGroup::Employee->value,
        ]))['bill_count'])->toBe(1);
});

it('aggregates classes in natural order and keeps employee categories as groups', function () {
    monthlyReportScenario();

    $groups = app(CateringMonthlyReportService::class)->groupRows(monthlyReportFilters());

    expect(array_column($groups, 'label'))->toBe(['2A', '10A', 'Pegawai Umum'])
        ->and($groups[0])->toMatchArray([
            'participant_count' => 2,
            'gross_amount' => 800000,
            'money_in' => 500000,
            'credit_used' => 100000,
            'outstanding_amount' => 200000,
            'overpayment_amount' => 0,
            'paid_count' => 1,
            'partial_count' => 1,
            'unpaid_count' => 0,
        ])
        ->and($groups[1])->toMatchArray([
            'participant_count' => 2,
            'gross_amount' => 800000,
            'money_in' => 500000,
            'credit_used' => 0,
            'outstanding_amount' => 400000,
            'overpayment_amount' => 100000,
            'paid_count' => 1,
            'partial_count' => 0,
            'unpaid_count' => 1,
        ]);
});

it('returns detail rows with cash credit overpayment remaining and status kept separate', function () {
    monthlyReportScenario();

    $rows = app(CateringMonthlyReportService::class)->detailRows(monthlyReportFilters())->keyBy('member_name');

    expect($rows['Aisyah Kredit'])->toMatchArray([
        'gross_amount' => 400000,
        'paid_amount' => 300000,
        'credit_applied_amount' => 100000,
        'generated_credit_amount' => 0,
        'outstanding_amount' => 0,
        'payment_status_label' => 'Lunas',
    ])->and($rows['Eka Lebih Bayar'])->toMatchArray([
        'gross_amount' => 400000,
        'paid_amount' => 500000,
        'credit_applied_amount' => 0,
        'generated_credit_amount' => 100000,
        'outstanding_amount' => 0,
        'payment_status_label' => 'Lunas',
    ])->and($rows['Budi Sebagian'])->toMatchArray([
        'paid_amount' => 200000,
        'outstanding_amount' => 200000,
        'payment_status_label' => 'Sebagian',
    ])->and($rows['Citra Belum Bayar'])->toMatchArray([
        'paid_amount' => 0,
        'outstanding_amount' => 400000,
        'payment_status' => CateringBillStatus::Unpaid->value,
        'payment_status_label' => 'Belum Bayar',
    ]);
});

it('loads detailed financial rows without per-bill queries', function () {
    $schoolClass = SchoolClass::factory()->create();
    $category = CateringCategory::factory()->student()->create();
    $members = CateringMember::factory()->count(25)->create([
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);
    $bills = $members->map(fn (CateringMember $member): CateringBill => CateringBill::factory()->create([
        'catering_member_id' => $member->id,
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]));

    foreach ($bills as $bill) {
        CateringPayment::factory()->create([
            'catering_bill_id' => $bill->id,
            'catering_member_id' => $bill->catering_member_id,
            'amount' => 100000,
        ]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $rows = app(CateringMonthlyReportService::class)->detailRows(monthlyReportFilters());
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($rows)->toHaveCount(25)
        ->and($queryCount)->toBe(1);
});

it('protects monthly financial report data for administrators', function () {
    $this->get(route('catering-monthly-report.index'))->assertRedirect(route('login'));
    $this->get(route('catering-monthly-report.preview', monthlyReportFilters()))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->waliKelas()->create())
        ->get(route('catering-monthly-report.index'))
        ->assertForbidden();

    $this->get(route('catering-monthly-report.preview', monthlyReportFilters()))->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('catering-monthly-report.index'))
        ->assertSeeLivewire(CateringMonthlyReport::class)
        ->assertSee('Laporan')
        ->assertSee(route('catering-monthly-report.index'), false);
});

it('renders header actions above filters summary and exactly three report tabs', function () {
    monthlyReportScenario();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMonthlyReport::class)
        ->set('month', 10)
        ->set('year', 2026)
        ->assertSeeInOrder(['Laporan Bulanan', 'Export Excel', 'Preview PDF', 'Bulan'])
        ->assertSee('Rekap tagihan dan pembayaran catering per periode.')
        ->assertSeeInOrder(['Ringkasan', 'Rekap Per Kelas', 'Detail Pembayaran'])
        ->assertSee('Rp 1.800.000')
        ->assertSee('Rp 1.300.000')
        ->assertSee('Rp 100.000')
        ->assertSee('Rp 600.000')
        ->assertSee('Statistik Pembayaran Siswa')
        ->assertSee('Persentase Lunas');
});

it('replaces the old financial cards with exactly five responsive student payment statistics cards', function () {
    monthlyReportScenario();
    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMonthlyReport::class)
        ->set('month', 10)
        ->set('year', 2026)
        ->set('participantGroup', CateringParticipantGroup::Student->value);

    $markup = monthlyReportStatisticsMarkup($component);

    expect($markup)->toContain(
        'Statistik Pembayaran Siswa',
        'Mengikuti filter peserta, jenjang, dan kelas yang dipilih.',
        'grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-5',
    )->not->toContain(
        'Total Tagihan',
        'Jumlah Uang Masuk',
        'Kredit Digunakan',
        'Total Tunggakan',
        'Total Lebih Bayar',
    );

    expect(substr_count($markup, 'class="card flex min-h-40 flex-col p-5"'))->toBe(5)
        ->and(substr_count($markup, '>Peserta Ditagih<'))->toBe(1)
        ->and(substr_count($markup, '>Jumlah Lunas<'))->toBe(1)
        ->and(substr_count($markup, '>Jumlah Sebagian<'))->toBe(1)
        ->and(substr_count($markup, '>Jumlah Belum Bayar<'))->toBe(1)
        ->and(substr_count($markup, '>Persentase Lunas<'))->toBe(1);
});

it('shows filtered participant payment statuses and a clean paid percentage', function () {
    monthlyReportScenario();
    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMonthlyReport::class)
        ->set('month', 10)
        ->set('year', 2026)
        ->set('participantGroup', CateringParticipantGroup::Student->value);

    $studentStatistics = monthlyReportStatisticsText($component);
    $component->set('participantGroup', CateringParticipantGroup::Employee->value);
    $employeeStatistics = monthlyReportStatisticsText($component);

    expect($studentStatistics)->toContain(
        'Peserta Ditagih 4 peserta',
        'Jumlah Lunas 2 peserta',
        'Jumlah Sebagian 1 peserta',
        'Jumlah Belum Bayar 1 peserta',
        'Persentase Lunas 50%',
    );
    expect($employeeStatistics)->toContain(
        'Peserta Ditagih 1 peserta',
        'Jumlah Lunas 1 peserta',
        'Jumlah Sebagian 0 peserta',
        'Jumlah Belum Bayar 0 peserta',
        'Persentase Lunas 100%',
    );
});

it('shows zero-safe student payment statistics for a period without participants', function () {
    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMonthlyReport::class)
        ->set('month', 10)
        ->set('year', 2025)
        ->set('participantGroup', CateringParticipantGroup::Student->value);

    $statistics = monthlyReportStatisticsText($component);

    expect($statistics)->toContain(
        'Peserta Ditagih 0 peserta',
        'Jumlah Lunas 0 peserta',
        'Jumlah Sebagian 0 peserta',
        'Jumlah Belum Bayar 0 peserta',
        'Persentase Lunas 0%',
    )->not->toContain('NAN', 'INF');
});

it('updates all student payment statistics for class and jenjang filters', function () {
    ['class2A' => $class2A, 'class10A' => $class10A] = monthlyReportScenario();
    $additionalPaidBill = CateringBill::factory()->create([
        'school_class_id' => $class2A->id,
        'school_class_name' => '2A',
        'school_level' => '2',
        'member_name' => 'Fani Lunas',
        'gross_amount' => 400000,
    ]);
    app(CateringPaymentService::class)->record($additionalPaidBill, 400000);
    CateringBill::factory()->count(2)->create([
        'school_class_id' => $class10A->id,
        'school_class_name' => '10A',
        'school_level' => '10',
        'gross_amount' => 400000,
    ]);
    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMonthlyReport::class)
        ->set('month', 10)
        ->set('year', 2026)
        ->set('participantGroup', CateringParticipantGroup::Student->value)
        ->set('schoolClassId', (string) $class2A->id);

    $class2AStatistics = monthlyReportStatisticsText($component);
    $component->set('schoolClassId', (string) $class10A->id);
    $class10AStatistics = monthlyReportStatisticsText($component);
    $component->set('schoolClassId', '')->set('jenjang', 'SD');
    $sdStatistics = monthlyReportStatisticsText($component);
    $component->set('jenjang', 'SMA');
    $smaStatistics = monthlyReportStatisticsText($component);

    expect($class2AStatistics)->toContain(
        'Peserta Ditagih 3 peserta',
        'Jumlah Lunas 2 peserta',
        'Jumlah Sebagian 1 peserta',
        'Jumlah Belum Bayar 0 peserta',
        'Persentase Lunas 66,7%',
    );
    expect($class10AStatistics)->toContain(
        'Peserta Ditagih 4 peserta',
        'Jumlah Lunas 1 peserta',
        'Jumlah Sebagian 0 peserta',
        'Jumlah Belum Bayar 3 peserta',
        'Persentase Lunas 25%',
    );
    expect($sdStatistics)->toContain(
        'Peserta Ditagih 3 peserta',
        'Jumlah Lunas 2 peserta',
        'Jumlah Sebagian 1 peserta',
        'Jumlah Belum Bayar 0 peserta',
        'Persentase Lunas 66,7%',
    );
    expect($smaStatistics)->toContain(
        'Peserta Ditagih 4 peserta',
        'Jumlah Lunas 1 peserta',
        'Jumlah Sebagian 0 peserta',
        'Jumlah Belum Bayar 3 peserta',
        'Persentase Lunas 25%',
    );
});

it('updates all student payment statistics for month and year filters', function () {
    monthlyReportScenario();
    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMonthlyReport::class)
        ->set('participantGroup', CateringParticipantGroup::Student->value)
        ->set('month', 10)
        ->set('year', 2026);

    $octoberStatistics = monthlyReportStatisticsText($component);
    $component->set('month', 9);
    $septemberStatistics = monthlyReportStatisticsText($component);
    $component->set('year', 2025);
    $emptyYearStatistics = monthlyReportStatisticsText($component);

    expect($octoberStatistics)->toContain(
        'Peserta Ditagih 4 peserta',
        'Jumlah Lunas 2 peserta',
        'Jumlah Sebagian 1 peserta',
        'Jumlah Belum Bayar 1 peserta',
        'Persentase Lunas 50%',
    );
    expect($septemberStatistics)->toContain(
        'Peserta Ditagih 1 peserta',
        'Jumlah Lunas 1 peserta',
        'Jumlah Sebagian 0 peserta',
        'Jumlah Belum Bayar 0 peserta',
        'Persentase Lunas 100%',
    );
    expect($emptyYearStatistics)->toContain(
        'Peserta Ditagih 0 peserta',
        'Jumlah Lunas 0 peserta',
        'Jumlah Sebagian 0 peserta',
        'Jumlah Belum Bayar 0 peserta',
        'Persentase Lunas 0%',
    );
});

it('refreshes every tab using the selected combined scope', function () {
    ['class2A' => $class2A] = monthlyReportScenario();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMonthlyReport::class)
        ->set('month', 10)
        ->set('year', 2026)
        ->set('participantGroup', CateringParticipantGroup::Student->value)
        ->set('jenjang', 'SD')
        ->set('schoolClassId', (string) $class2A->id)
        ->assertSee('Rp 800.000')
        ->call('setTab', 'groups')
        ->assertSee('2A')
        ->assertDontSee('10A')
        ->assertDontSee('Pegawai Umum')
        ->call('setTab', 'details')
        ->assertSee('Aisyah Kredit')
        ->assertSee('Budi Sebagian')
        ->assertDontSee('Citra Belum Bayar')
        ->assertDontSee('Dedi Pegawai')
        ->assertDontSee('Eka Lebih Bayar');
});

it('shows employee grouping terminology and the report empty state', function () {
    monthlyReportScenario();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMonthlyReport::class)
        ->set('month', 10)
        ->set('year', 2026)
        ->set('participantGroup', CateringParticipantGroup::Employee->value)
        ->assertSee('Rekap Per Kelompok')
        ->call('setTab', 'groups')
        ->assertSee('Pegawai Umum')
        ->set('year', 2025)
        ->assertSee('Belum ada data laporan untuk periode/filter ini.');
});

it('downloads a filtered three-sheet excel report', function () {
    ['class2A' => $class2A] = monthlyReportScenario();
    $filters = monthlyReportFilters([
        'participantGroup' => CateringParticipantGroup::Student->value,
        'jenjang' => 'SD',
        'schoolClassId' => (string) $class2A->id,
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMonthlyReport::class)
        ->set('month', 10)
        ->set('year', 2026)
        ->set('participantGroup', $filters['participantGroup'])
        ->set('jenjang', $filters['jenjang'])
        ->set('schoolClassId', $filters['schoolClassId'])
        ->call('downloadExcel')
        ->assertFileDownloaded('laporan-bulanan-catering-oktober-2026.xlsx');

    $path = app(CateringMonthlyReportExportService::class)->createExcel($filters);
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

    expect(array_keys($sheets))->toBe(['Ringkasan', 'Rekap Per Kelas-Kelompok', 'Detail Pembayaran'])
        ->and($sheets['Ringkasan'][5])->toBe(['Total Tagihan', 800000])
        ->and($sheets['Ringkasan'][6])->toBe(['Jumlah Uang Masuk', 500000])
        ->and($sheets['Ringkasan'][7])->toBe(['Kredit Digunakan', 100000])
        ->and($sheets['Rekap Per Kelas-Kelompok'][2][1])->toBe('2A')
        ->and(collect($sheets['Detail Pembayaran'])->flatten()->all())->toContain('Aisyah Kredit', 'Budi Sebagian')
        ->not->toContain('Citra Belum Bayar', 'Dedi Pegawai', 'Eka Lebih Bayar');
});

it('previews a filtered monthly report pdf inline in a new tab', function () {
    ['class2A' => $class2A] = monthlyReportScenario();
    $filters = monthlyReportFilters([
        'participantGroup' => CateringParticipantGroup::Student->value,
        'jenjang' => 'SD',
        'schoolClassId' => (string) $class2A->id,
    ]);
    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMonthlyReport::class)
        ->set('month', 10)
        ->set('year', 2026)
        ->set('participantGroup', $filters['participantGroup'])
        ->set('jenjang', $filters['jenjang'])
        ->set('schoolClassId', $filters['schoolClassId']);
    $markup = html_entity_decode($component->html(), ENT_QUOTES | ENT_HTML5);

    expect($markup)->toContain('target="_blank"')
        ->and($markup)->toContain(route('catering-monthly-report.preview', $filters));

    $response = $this->actingAs(User::factory()->admin()->create())
        ->get(route('catering-monthly-report.preview', $filters));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename="laporan-bulanan-catering-oktober-2026.pdf"');

    $pdf = $response->streamedContent();
    $text = monthlyReportPdfText($pdf);

    expect($pdf)->toStartWith('%PDF-')
        ->and($text)->toContain('Laporan Bulanan Catering', 'Oktober 2026', 'Aisyah Kredit', 'Budi Sebagian')
        ->not->toContain('Citra Belum Bayar', 'Dedi Pegawai', 'Eka Lebih Bayar');
});
