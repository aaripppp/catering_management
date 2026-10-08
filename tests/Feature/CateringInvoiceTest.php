<?php

use App\Enums\CateringAttendanceStatus;
use App\Enums\CateringParticipantGroup;
use App\Livewire\CateringAttendance\Index as CateringAttendanceIndex;
use App\Models\CateringAttendance;
use App\Models\CateringBill;
use App\Models\CateringCategory;
use App\Models\CateringMember;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\CateringAttendanceInitializerService;
use App\Services\CateringBillingService;
use App\Services\CateringInvoiceService;
use App\Services\CateringPaymentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * The fixed billing period shared by every invoice test.
 *
 * @return array{0: CarbonImmutable, 1: CarbonImmutable}
 */
function invoicePeriod(): array
{
    $start = CarbonImmutable::create(2026, 9, 1)->startOfMonth();

    return [$start, $start->endOfMonth()];
}

function invoiceService(): CateringInvoiceService
{
    return app(CateringInvoiceService::class);
}

/**
 * The working days of a month as stored attendance dates, so a fixture can lay a
 * plan over them and a test can compare against them without hard coding.
 *
 * The expectation is derived from the calendar itself so month fixtures do not
 * drift when their year or month changes.
 *
 * @return array<int, string>
 */
function invoiceWeekdayDates(int $year = 2026, int $month = 9): array
{
    return invoiceMonthDates($year, $month, false);
}

/**
 * The Saturday and Sunday dates of a month, as stored attendance dates.
 *
 * @return array<int, string>
 */
function invoiceWeekendDates(int $year = 2026, int $month = 9): array
{
    return invoiceMonthDates($year, $month, true);
}

/**
 * @return array<int, string>
 */
function septemberWeekdayDates(): array
{
    return invoiceWeekdayDates(2026, 9);
}

/**
 * @return array<int, string>
 */
function invoiceMonthDates(int $year, int $month, bool $weekend = false): array
{
    $start = CarbonImmutable::create($year, $month, 1)->startOfMonth();
    $dates = [];

    for ($date = $start; $date->lessThanOrEqualTo($start->endOfMonth()); $date = $date->addDay()) {
        if ($date->isWeekend() === $weekend) {
            $dates[] = $date->toDateString();
        }
    }

    return $dates;
}

/**
 * The `d/m/Y` form the invoice prints, used to assert on rendered pdf text.
 */
function invoiceDateLabel(string $date): string
{
    return CarbonImmutable::parse($date)->format('d/m/Y');
}

function openInvoiceAttendanceMatrix(
    ?SchoolClass $schoolClass = null,
    CateringParticipantGroup $group = CateringParticipantGroup::Student,
    string $jenjang = 'SMP',
): Testable {
    return Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringAttendanceIndex::class)
        ->call('changeFilter', 'year', 2026)
        ->call('changeFilter', 'month', 9)
        ->call('changeFilter', 'participantGroup', $group->value)
        ->call('changeFilter', 'jenjang', $jenjang)
        ->call('changeFilter', 'schoolClassId', (string) $schoolClass?->id);
}

/**
 * Select the billing period without ever opening the matrix.
 *
 * Opening a matrix now initialises attendance for every active participant, so a
 * test about who is *not* yet eligible for an invoice must not load one.
 */
function selectInvoicePeriodWithoutLoadingMatrix(SchoolClass $schoolClass): Testable
{
    return Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringAttendanceIndex::class)
        ->set('year', 2026)
        ->set('month', 9)
        ->set('participantGroup', CateringParticipantGroup::Student->value)
        ->set('jenjang', 'SMP')
        ->set('schoolClassId', (string) $schoolClass->id);
}

/**
 * Persist attendance the way the attendance page does, so invoices can only ever
 * read rows the page itself has saved.
 *
 * The plan is laid out over the working days of September. Weekend visibility is
 * covered separately because invoices now filter by status rather than weekday.
 */
function saveSeptemberAttendance(
    CateringMember $member,
    int $ikut,
    int $sakit = 0,
    int $izin = 0,
    int $alfa = 0,
    int $tidakIkut = 0,
    int $ujian = 0,
    int $eventUnit = 0,
    int $puasa = 0,
    int $libur = 0,
): void {
    $statuses = array_merge(
        array_fill(0, $ikut, CateringAttendanceStatus::Ikut),
        array_fill(0, $sakit, CateringAttendanceStatus::Sakit),
        array_fill(0, $izin, CateringAttendanceStatus::Izin),
        array_fill(0, $alfa, CateringAttendanceStatus::Alfa),
        array_fill(0, $tidakIkut, CateringAttendanceStatus::TidakIkut),
        array_fill(0, $ujian, CateringAttendanceStatus::Ujian),
        array_fill(0, $eventUnit, CateringAttendanceStatus::EventUnit),
        array_fill(0, $puasa, CateringAttendanceStatus::Puasa),
        array_fill(0, $libur, CateringAttendanceStatus::Libur),
    );

    $weekdays = septemberWeekdayDates();

    if (count($statuses) > count($weekdays)) {
        throw new InvalidArgumentException('September 2026 only has '.count($weekdays).' working days.');
    }

    foreach ($statuses as $index => $status) {
        CateringAttendance::factory()->for($member)->create([
            'attendance_date' => $weekdays[$index],
            'status' => $status,
        ]);
    }
}

/**
 * @return array<int, string>
 */
function invoiceZipEntries(string $bytes): array
{
    $path = tempnam(sys_get_temp_dir(), 'invoice-zip-');
    file_put_contents($path, $bytes);

    $archive = new ZipArchive;

    if ($archive->open($path) !== true) {
        unlink($path);

        throw new RuntimeException('The generated ZIP could not be opened.');
    }

    $names = [];

    for ($index = 0; $index < $archive->numFiles; $index++) {
        $names[] = $archive->getNameIndex($index);
    }

    $archive->close();
    unlink($path);

    return $names;
}

function invoiceZipEntryContent(string $bytes, string $entry): string
{
    $path = tempnam(sys_get_temp_dir(), 'invoice-zip-');
    file_put_contents($path, $bytes);

    $archive = new ZipArchive;

    if ($archive->open($path) !== true) {
        unlink($path);

        throw new RuntimeException('The generated ZIP could not be opened.');
    }

    $content = $archive->getFromName($entry);
    $archive->close();
    unlink($path);

    return $content === false ? '' : $content;
}

/**
 * Pull the visible text out of a DomPDF page content stream so the rendered
 * wording can be asserted, not just the byte length.
 *
 * DomPDF splits a single visible line across several text runs, so the runs are
 * joined with spaces and then collapsed to keep assertions stable regardless of
 * how the typesetter happened to split the line.
 */
function invoicePdfText(string $bytes): string
{
    $text = '';

    if (preg_match_all('/stream\\r?\\n(.*?)endstream/s', $bytes, $matches) === false) {
        return $text;
    }

    foreach ($matches[1] as $stream) {
        $decoded = @gzuncompress($stream);

        if ($decoded === false || ! str_contains($decoded, 'Tf')) {
            continue;
        }

        if (preg_match_all('/\[(.*?)\]\\s*TJ|\((.*?)\)\\s*Tj/s', $decoded, $runs, PREG_SET_ORDER) === false) {
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

function downloadedContent(Testable $component): ?string
{
    $content = data_get($component->effects, 'download.content');

    return $content === null ? null : base64_decode($content);
}

/**
 * Count the pages of a rendered PDF without pulling in an extra dependency.
 *
 * DomPDF writes one `/Type /Page` object per page, while the tree node uses
 * `/Type /Pages`, so the trailing non-word boundary keeps the tree out.
 */
function invoicePdfPageCount(string $bytes): int
{
    $count = preg_match_all('#/Type\s*/Page[^s]#', $bytes);

    return $count === false ? 0 : $count;
}

/**
 * Save attendance across an arbitrary month so the one page layout can be
 * exercised with the working days of a 28, 30 and 31 day month.
 *
 * @param  array<int, array{0: CateringAttendanceStatus, 1: int}>  $plan
 * @return array{0: CarbonImmutable, 1: CarbonImmutable}
 */
function saveMonthAttendance(
    CateringMember $member,
    int $year,
    int $month,
    int $days,
    array $plan,
): array {
    $start = CarbonImmutable::create($year, $month, 1)->startOfMonth();
    $statuses = [];

    foreach ($plan as [$status, $count]) {
        for ($index = 0; $index < $count; $index++) {
            $statuses[] = $status;
        }
    }

    $weekdays = array_slice(invoiceWeekdayDates($year, $month), 0, $days);

    foreach (array_slice($statuses, 0, count($weekdays)) as $index => $status) {
        CateringAttendance::factory()->for($member)->create([
            'attendance_date' => $weekdays[$index],
            'status' => $status,
        ]);
    }

    return [$start, $start->endOfMonth()];
}

/**
 * A realistic mix that fills the working days of a month: 12 billed days and the
 * rest spread across the unpaid statuses.
 *
 * @return array<int, array{0: CateringAttendanceStatus, 1: int}>
 */
function invoiceAttendancePlan(): array
{
    return [
        [CateringAttendanceStatus::Ikut, 12],
        [CateringAttendanceStatus::Sakit, 2],
        [CateringAttendanceStatus::Izin, 1],
        [CateringAttendanceStatus::Alfa, 1],
        [CateringAttendanceStatus::TidakIkut, 2],
        [CateringAttendanceStatus::Ujian, 2],
        [CateringAttendanceStatus::EventUnit, 1],
        [CateringAttendanceStatus::Puasa, 1],
    ];
}

/**
 * Preview URL of one participant invoice for the shared invoice period.
 */
function memberInvoiceRoute(
    CateringMember $member,
    ?SchoolClass $schoolClass = null,
    CateringParticipantGroup $group = CateringParticipantGroup::Student,
    int $month = 9,
    int $year = 2026,
): string {
    return route('catering-attendance.invoice.member', [
        'member' => $member->id,
        'month' => $month,
        'year' => $year,
        'participantGroup' => $group->value,
        'schoolClassId' => $schoolClass?->id,
    ]);
}

/**
 * Preview URL of a class summary invoice for the shared invoice period.
 */
function classInvoiceRoute(SchoolClass $schoolClass, int $month = 9, int $year = 2026): string
{
    return route('catering-attendance.invoice.class', [
        'schoolClass' => $schoolClass->id,
        'month' => $month,
        'year' => $year,
    ]);
}

/**
 * Rendered markup of the attendance page for the currently loaded matrix.
 */
function attendanceMarkup(Testable $component): string
{
    return html_entity_decode($component->html(), ENT_QUOTES | ENT_HTML5);
}

it('returns an inline pdf preview for a member with saved attendance', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $category = CateringCategory::factory()->create(['name' => 'Siswa Umum', 'price_per_day' => 15000]);
    $member = CateringMember::factory()->create([
        'name' => 'Ahmad Zaki',
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);
    saveSeptemberAttendance($member, ikut: 18);

    $response = $this->actingAs(User::factory()->admin()->create())
        ->get(memberInvoiceRoute($member, $schoolClass));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename="Invoice-Ahmad-Zaki-7A-September-2026.pdf"');

    expect($response->streamedContent())->toStartWith('%PDF-');
});

it('links the individual invoice to a new tab preview endpoint', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $member = CateringMember::factory()->create([
        'name' => 'Ahmad Zaki',
        'school_class_id' => $schoolClass->id,
    ]);
    saveSeptemberAttendance($member, ikut: 18);

    $markup = attendanceMarkup(openInvoiceAttendanceMatrix($schoolClass));

    expect($markup)->toContain('target="_blank"')
        ->and($markup)->toContain(memberInvoiceRoute($member, $schoolClass));
});

it('keeps the individual preview link available right after an auto-saved change', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $member = CateringMember::factory()->create(['school_class_id' => $schoolClass->id]);
    saveSeptemberAttendance($member, ikut: 5);

    $component = openInvoiceAttendanceMatrix($schoolClass)
        ->assertSet('saveState.saved', false)
        ->call('setCellStatus', $member->id, '2026-09-20', 'alfa')
        ->assertSet('saveState.saved', true);

    expect(attendanceMarkup($component))->toContain(memberInvoiceRoute($member, $schoolClass));
});

it('includes an auto-saved change in the bulk invoice download', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $members = CateringMember::factory()->count(2)->create(['school_class_id' => $schoolClass->id]);

    foreach ($members as $member) {
        saveSeptemberAttendance($member, ikut: 5);
    }

    [$start, $end] = invoicePeriod();
    $billed = fn (): int => invoiceService()->buildMemberInvoice(
        $members[0]->fresh(['cateringCategory', 'schoolClass']),
        $start,
        $end,
    )['quantity'];

    // Opening the matrix initialises the month, so the baseline is the whole month.
    $component = openInvoiceAttendanceMatrix($schoolClass);
    $quantityBeforeChange = $billed();

    $component->call('setCellStatus', $members[0]->id, '2026-09-01', 'alfa')
        ->call('downloadAllInvoices')
        ->assertFileDownloaded();

    expect($quantityBeforeChange)->toBeGreaterThan(0)
        ->and($billed())->toBe($quantityBeforeChange - 1);
});

it('excludes every non-ikut status from the billed quantity', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $category = CateringCategory::factory()->create(['price_per_day' => 17000]);
    $member = CateringMember::factory()->create([
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);
    saveSeptemberAttendance($member, ikut: 10, sakit: 3, izin: 3, alfa: 2, tidakIkut: 2, libur: 2);

    [$start, $end] = invoicePeriod();

    $invoice = invoiceService()->buildMemberInvoice(
        $member->fresh(['cateringCategory', 'schoolClass']),
        $start,
        $end,
    );

    expect($invoice['quantity'])->toBe(10)
        ->and($invoice['savedDays'])->toBe(22)
        ->and($invoice['total'])->toBe(10 * 17000);
});

it('computes the total with integer rupiah math from quantity times price', function () {
    $category = CateringCategory::factory()->create(['price_per_day' => 13500]);
    $member = CateringMember::factory()->create(['catering_category_id' => $category->id]);
    saveSeptemberAttendance($member, ikut: 17, sakit: 5);

    [$start, $end] = invoicePeriod();

    $invoice = invoiceService()->buildMemberInvoice(
        $member->fresh(['cateringCategory']),
        $start,
        $end,
    );

    expect($invoice['pricePerDay'])->toBe(13500)
        ->and($invoice['quantity'])->toBe(17)
        ->and($invoice['total'])->toBe(229500)
        ->and($invoice['total'])->toBeInt()
        ->and($invoice['totalFormatted'])->toBe('Rp 229.500');
});

it('refuses a preview for a participant outside the requested class context', function () {
    $selectedClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $otherClass = SchoolClass::factory()->create(['name' => '7B', 'level' => '7']);
    $otherMember = CateringMember::factory()->create([
        'name' => 'Peserta Kelas Lain',
        'school_class_id' => $otherClass->id,
    ]);
    saveSeptemberAttendance($otherMember, ikut: 10);

    $this->actingAs(User::factory()->admin()->create())
        ->get(memberInvoiceRoute($otherMember, $selectedClass))
        ->assertNotFound();
});

it('refuses a member preview when the month has no saved attendance', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $member = CateringMember::factory()->create(['school_class_id' => $schoolClass->id]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(memberInvoiceRoute($member, $schoolClass))
        ->assertNotFound();
});

it('blocks the bulk download when no attendance is saved for the month', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    CateringMember::factory()->count(2)->create(['school_class_id' => $schoolClass->id]);

    $component = selectInvoicePeriodWithoutLoadingMatrix($schoolClass)
        ->call('downloadAllInvoices')
        ->assertNoFileDownloaded();

    $component->assertDispatched('toast', type: 'warning', message: 'Absensi peserta untuk periode ini belum disimpan.');
});

it('sanitizes unsafe characters out of the individual invoice filename', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $member = CateringMember::factory()->create([
        'name' => 'Ahmad/Zaki: "*|<>?',
        'school_class_id' => $schoolClass->id,
    ]);
    saveSeptemberAttendance($member, ikut: 3);

    $this->actingAs(User::factory()->admin()->create())
        ->get(memberInvoiceRoute($member, $schoolClass))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'inline; filename="Invoice-Ahmad-Zaki-7A-September-2026.pdf"');
});

it('uses the correct bulk zip filename for a class', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $member = CateringMember::factory()->create(['school_class_id' => $schoolClass->id]);
    saveSeptemberAttendance($member, ikut: 4);

    openInvoiceAttendanceMatrix($schoolClass)
        ->call('downloadAllInvoices')
        ->assertFileDownloaded('Invoice-Catering-7A-September-2026.zip', contentType: 'application/zip');
});

it('builds one bulk zip containing one individual pdf per eligible participant', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $members = CateringMember::factory()->count(3)->create(['school_class_id' => $schoolClass->id]);
    $withoutAttendance = CateringMember::factory()->create([
        'name' => 'Belum Absen',
        'school_class_id' => $schoolClass->id,
    ]);

    foreach ($members as $index => $member) {
        saveSeptemberAttendance($member, ikut: 5 + $index);
    }

    $component = selectInvoicePeriodWithoutLoadingMatrix($schoolClass)->call('downloadAllInvoices');
    $entries = invoiceZipEntries(downloadedContent($component));

    expect($entries)->toHaveCount(3)
        ->and(implode('|', $entries))->not->toContain('Belum-Absen');

    foreach ($entries as $entry) {
        expect($entry)->toStartWith('Invoice-')->toEndWith('-7A-September-2026.pdf');
    }

    foreach ($members as $member) {
        expect(implode('|', $entries))->toContain(trim((string) preg_replace('/[^\p{L}\p{N}]+/u', '-', $member->name), '-'));
    }
});

it('keeps bulk pdf filenames unique when two participants share a name', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $first = CateringMember::factory()->create(['name' => 'Ahmad Zaki', 'school_class_id' => $schoolClass->id]);
    $second = CateringMember::factory()->create(['name' => 'Ahmad Zaki', 'school_class_id' => $schoolClass->id]);
    saveSeptemberAttendance($first, ikut: 4);
    saveSeptemberAttendance($second, ikut: 6);

    $component = openInvoiceAttendanceMatrix($schoolClass)->call('downloadAllInvoices');
    $entries = invoiceZipEntries(downloadedContent($component));

    expect($entries)->toHaveCount(2)
        ->and(array_unique($entries))->toHaveCount(2)
        ->and($entries)->toContain('Invoice-Ahmad-Zaki-7A-September-2026.pdf')
        ->and($entries)->toContain('Invoice-Ahmad-Zaki-7A-September-2026-'.$second->id.'.pdf');
});

it('bulk pdf count equals the number of active participants with saved attendance', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $otherClass = SchoolClass::factory()->create(['name' => '7B', 'level' => '7']);
    $inactive = CateringMember::factory()->inactive()->create(['school_class_id' => $schoolClass->id]);
    $otherClassMember = CateringMember::factory()->create(['school_class_id' => $otherClass->id]);
    $members = CateringMember::factory()->count(4)->create(['school_class_id' => $schoolClass->id]);

    saveSeptemberAttendance($inactive, ikut: 5);
    saveSeptemberAttendance($otherClassMember, ikut: 5);

    foreach ($members as $member) {
        saveSeptemberAttendance($member, ikut: 7);
    }

    $component = openInvoiceAttendanceMatrix($schoolClass)->call('downloadAllInvoices');

    expect(invoiceZipEntries(downloadedContent($component)))->toHaveCount(4);
});

it('does not introduce an n+1 pattern when building bulk invoices', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $members = CateringMember::factory()->count(12)->create(['school_class_id' => $schoolClass->id]);

    foreach ($members as $member) {
        saveSeptemberAttendance($member, ikut: 3);
    }

    [$start, $end] = invoicePeriod();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $invoices = invoiceService()->buildBulkInvoices(
        CateringParticipantGroup::Student,
        $schoolClass,
        $start,
        $end,
    );

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($invoices)->toHaveCount(12)
        ->and($queryCount)->toBeLessThanOrEqual(5);
});

it('uses employee wording for the employee participant group', function () {
    $category = CateringCategory::factory()->employee()->create(['name' => 'Guru', 'price_per_day' => 20000]);
    $member = CateringMember::factory()->withoutClass()->create([
        'name' => 'Budi Santoso',
        'catering_category_id' => $category->id,
    ]);
    saveSeptemberAttendance($member, ikut: 20);

    [$start, $end] = invoicePeriod();

    $invoice = invoiceService()->buildMemberInvoice(
        $member->fresh(['cateringCategory', 'schoolClass']),
        $start,
        $end,
    );

    expect($invoice['contextLabel'])->toBe('CATERING PEGAWAI')
        ->and($invoice['contextLabel'])->not->toContain('SISWA')
        ->and($invoice['identityValue'])->toBe('Pegawai')
        ->and($invoice['total'])->toBe(400000);
});

it('downloads the employee bulk zip without a class and with employee wording', function () {
    $category = CateringCategory::factory()->employee()->create();
    $members = CateringMember::factory()->count(2)->withoutClass()->create([
        'catering_category_id' => $category->id,
    ]);

    foreach ($members as $member) {
        saveSeptemberAttendance($member, ikut: 9);
    }

    $component = openInvoiceAttendanceMatrix(null, CateringParticipantGroup::Employee)
        ->assertSet('schoolClassId', '')
        ->assertSet('matrixLoaded', true)
        ->call('downloadAllInvoices')
        ->assertFileDownloaded('Invoice-Catering-Pegawai-September-2026.zip');

    $bytes = downloadedContent($component);
    $entries = invoiceZipEntries($bytes);

    expect($entries)->toHaveCount(2)
        ->and(invoiceZipEntryContent($bytes, $entries[0]))->toStartWith('%PDF-');
});

it('does not modify saved attendance while generating invoices', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $members = CateringMember::factory()->count(3)->create(['school_class_id' => $schoolClass->id]);

    foreach ($members as $member) {
        saveSeptemberAttendance($member, ikut: 11, sakit: 2);
    }

    $snapshot = fn (): array => CateringAttendance::query()
        ->orderBy('id')
        ->get(['catering_member_id', 'attendance_date', 'status', 'updated_at'])
        ->toArray();

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get(memberInvoiceRoute($members[0], $schoolClass));

    // Opening the matrix initialises the gaps it finds; that is the page doing its
    // job, not the invoice. Both invoice steps must stay read only after that.
    $component = openInvoiceAttendanceMatrix($schoolClass);
    $afterOpening = $snapshot();
    $component->call('downloadAllInvoices');

    expect($snapshot())->toEqual($afterOpening)
        ->and($component->get('saving'))->toBeFalse();
});

it('derives the invoice number without storing it in a new table', function () {
    $member = CateringMember::factory()->create();

    [$start, $end] = invoicePeriod();

    $invoiceNumber = invoiceService()->buildMemberInvoice(
        $member->fresh(['cateringCategory']),
        $start,
        $end,
    )['invoiceNumber'];

    expect($invoiceNumber)->toMatch('#^INV/CAF/\d{2}/\d{4}/\d+$#')
        ->and(Schema::hasTable('catering_invoices'))->toBeFalse()
        ->and(Schema::hasTable('invoices'))->toBeFalse();
});

it('renders the same shared template for individual and bulk pdf output', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $category = CateringCategory::factory()->create(['name' => 'Siswa Umum', 'price_per_day' => 15000]);
    $members = CateringMember::factory()->count(2)->create([
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);

    foreach ($members as $member) {
        saveSeptemberAttendance($member, ikut: 8);
    }

    $individual = $this->actingAs(User::factory()->admin()->create())
        ->get(memberInvoiceRoute($members[0], $schoolClass))
        ->assertOk()
        ->streamedContent();

    $component = openInvoiceAttendanceMatrix($schoolClass);
    $component->call('downloadAllInvoices');
    $entries = invoiceZipEntries(downloadedContent($component));

    expect($individual)->toStartWith('%PDF-')
        ->and($entries)->toHaveCount(2);

    $fromZip = invoiceZipEntryContent(downloadedContent($component), $entries[0]);

    expect($fromZip)->toStartWith('%PDF-')
        ->and(strlen($fromZip))->toBeGreaterThan(1000)
        ->and(invoicePdfText($fromZip))->toContain('INVOICE CATERING SISWA')
        ->and(invoicePdfText($fromZip))->toContain('RINCIAN ABSENSI');
});

it('rejects a preview for a member outside the requested participant group', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $employeeCategory = CateringCategory::factory()->employee()->create();
    $employee = CateringMember::factory()->withoutClass()->create([
        'name' => 'Guru Pindah Kelas',
        'catering_category_id' => $employeeCategory->id,
    ]);
    saveSeptemberAttendance($employee, ikut: 12);

    $this->actingAs(User::factory()->admin()->create())
        ->get(memberInvoiceRoute($employee, $schoolClass, CateringParticipantGroup::Student))
        ->assertNotFound();
});

it('rejects a preview for an inactive participant', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $member = CateringMember::factory()->inactive()->create(['school_class_id' => $schoolClass->id]);
    saveSeptemberAttendance($member, ikut: 12);

    $this->actingAs(User::factory()->admin()->create())
        ->get(memberInvoiceRoute($member, $schoolClass))
        ->assertNotFound();
});

it('requires authentication to preview an invoice', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $member = CateringMember::factory()->create(['school_class_id' => $schoolClass->id]);
    saveSeptemberAttendance($member, ikut: 12);

    $this->get(memberInvoiceRoute($member, $schoolClass))->assertRedirect(route('login'));
    $this->get(classInvoiceRoute($schoolClass))->assertRedirect(route('login'));
});

it('rejects an invalid invoicing period', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $member = CateringMember::factory()->create(['school_class_id' => $schoolClass->id]);
    saveSeptemberAttendance($member, ikut: 12);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('catering-attendance.invoice.member', [
            'member' => $member->id,
            'month' => 13,
            'year' => 2026,
            'participantGroup' => CateringParticipantGroup::Student->value,
            'schoolClassId' => $schoolClass->id,
        ]))
        ->assertSessionHasErrors('month');

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('catering-attendance.invoice.class', [
            'schoolClass' => $schoolClass->id,
            'month' => 9,
            'year' => 2026,
        ]))
        ->assertOk();
});

it('ignores attendance rows outside the invoiced month', function () {
    $category = CateringCategory::factory()->create(['price_per_day' => 15000]);
    $member = CateringMember::factory()->create(['catering_category_id' => $category->id]);

    saveSeptemberAttendance($member, ikut: 4);

    foreach (['2026-08-31', '2026-10-01', '2026-10-02'] as $outsideDay) {
        CateringAttendance::factory()->for($member)->create([
            'attendance_date' => $outsideDay,
            'status' => CateringAttendanceStatus::Ikut,
        ]);
    }

    [$start, $end] = invoicePeriod();

    $invoice = invoiceService()->buildMemberInvoice(
        $member->fresh(['cateringCategory']),
        $start,
        $end,
    );

    expect($invoice['quantity'])->toBe(4)
        ->and($invoice['savedDays'])->toBe(4)
        ->and($invoice['total'])->toBe(60000);
});

it('uses the current category price when the monthly bill has not been generated', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $category = CateringCategory::factory()->create(['price_per_day' => 14000]);
    $member = CateringMember::factory()->create([
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);
    saveSeptemberAttendance($member, ikut: 10);

    [$start, $end] = invoicePeriod();
    $service = invoiceService();

    $before = $service->buildMemberInvoice($member->fresh(['cateringCategory']), $start, $end);

    $category->update(['price_per_day' => 16000]);
    $after = $service->buildMemberInvoice($member->fresh(['cateringCategory']), $start, $end);

    expect($before['total'])->toBe(140000)
        ->and($after['total'])->toBe(160000)
        ->and($after['quantity'])->toBe($before['quantity'])
        ->and(Schema::getColumnListing('catering_attendances'))
        ->not->toContain('price_per_day');
});

it('uses generated bill snapshots for invoice financial values', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $category = CateringCategory::factory()->create(['price_per_day' => 14000]);
    $member = CateringMember::factory()->create([
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);
    saveSeptemberAttendance($member, ikut: 10, sakit: 2);

    app(CateringBillingService::class)->generate(
        year: 2026,
        month: 9,
        group: CateringParticipantGroup::Student,
        actor: User::factory()->admin()->create(),
    );

    $category->update(['price_per_day' => 16000]);
    CateringAttendance::query()
        ->where('catering_member_id', $member->id)
        ->where('status', CateringAttendanceStatus::Ikut)
        ->firstOrFail()
        ->update(['status' => CateringAttendanceStatus::Izin]);

    [$start, $end] = invoicePeriod();
    $invoice = invoiceService()->buildMemberInvoice(
        $member->fresh(['cateringCategory']),
        $start,
        $end,
    );

    expect(CateringBill::query()->sole()->gross_amount)->toBe(140000)
        ->and($invoice['pricePerDay'])->toBe(14000)
        ->and($invoice['quantity'])->toBe(10)
        ->and($invoice['countSakit'])->toBe(2)
        ->and($invoice['countIzin'])->toBe(0)
        ->and($invoice['total'])->toBe(140000);
});

it('reflects the latest resynced bill and payment balance in the invoice', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $category = CateringCategory::factory()->create(['price_per_day' => 14000]);
    $member = CateringMember::factory()->create([
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);
    saveSeptemberAttendance($member, ikut: 10);
    $admin = User::factory()->admin()->create();
    $billing = app(CateringBillingService::class);
    $billing->generate(2026, 9, CateringParticipantGroup::Student, $schoolClass, actor: $admin);
    $bill = CateringBill::query()->sole();
    app(CateringPaymentService::class)->record($bill, 140000, $admin);
    CateringAttendance::factory()->for($member)->create([
        'attendance_date' => '2026-09-26',
        'status' => CateringAttendanceStatus::Ikut,
    ]);
    $billing->generate(2026, 9, CateringParticipantGroup::Student, $schoolClass, actor: $admin);

    [$start, $end] = invoicePeriod();
    $invoice = invoiceService()->buildMemberInvoice($member->fresh(['cateringCategory']), $start, $end);

    expect($invoice['quantity'])->toBe(11)
        ->and($invoice['total'])->toBe(154000)
        ->and($invoice['paidAmount'])->toBe(140000)
        ->and($invoice['remainingAmount'])->toBe(14000)
        ->and($invoice['paymentStatusLabel'])->toBe('Sebagian');
});

it('lists every non-libur attendance date in the individual invoice detail table', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $category = CateringCategory::factory()->create(['price_per_day' => 17000]);
    $member = CateringMember::factory()->create([
        'name' => 'Ahmad Zaki',
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);
    saveSeptemberAttendance($member, ikut: 1, sakit: 1, izin: 1, alfa: 1, tidakIkut: 1, libur: 1);

    $text = invoicePdfText(invoiceService()->renderPdf(
        invoiceService()->buildMemberInvoice($member->fresh(['cateringCategory', 'schoolClass']), ...invoicePeriod()),
    ));

    expect($text)->toContain('RINCIAN ABSENSI')
        ->and($text)->toContain('Aktif')
        ->and($text)->toContain('Off')
        ->and($text)->not->toContain('Tidak Ikut')
        ->and($text)->toContain('01/09/2026')
        ->and($text)->toContain('02/09/2026')
        ->and($text)->toContain('03/09/2026')
        ->and($text)->toContain('04/09/2026')
        ->and($text)->toContain('07/09/2026')
        ->and($text)->not->toContain('08/09/2026')
        ->and($text)->toContain('TANGGAL')
        ->and($text)->toContain('STATUS')
        ->and($text)->not->toContain('KETERANGAN')
        ->and($text)->toContain('HARGA / PORSI')
        ->and($text)->toContain('SUBTOTAL');
});

it('bills only the ikut row in the individual invoice detail table', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $category = CateringCategory::factory()->create(['price_per_day' => 17000]);
    $member = CateringMember::factory()->create([
        'name' => 'Ahmad Zaki',
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);
    saveSeptemberAttendance($member, ikut: 1, sakit: 1, izin: 1, alfa: 1, tidakIkut: 1, libur: 1);

    [$start, $end] = invoicePeriod();
    $invoice = invoiceService()->buildMemberInvoice($member->fresh(['cateringCategory', 'schoolClass']), $start, $end);
    $rows = collect($invoice['attendanceRows'])->keyBy('date');

    expect($rows['01/09/2026']['status'])->toBe('Aktif')
        ->and($rows['01/09/2026']['subtotal'])->toBe(17000)
        ->and($rows['01/09/2026']['pricePerDayFormatted'])->toBe('Rp 17.000')
        ->and($rows['01/09/2026'])->not->toHaveKey('note');

    foreach (['02/09/2026', '03/09/2026', '04/09/2026', '07/09/2026'] as $nonBillable) {
        expect($rows[$nonBillable]['subtotal'])->toBe(0)
            ->and($rows[$nonBillable]['pricePerDayFormatted'])->toBe('-');
    }

    expect($rows['02/09/2026']['status'])->toBe('Sakit')
        ->and($rows['03/09/2026']['status'])->toBe('Izin')
        ->and($rows['04/09/2026']['status'])->toBe('Budaya Makan')
        ->and($rows['04/09/2026']['pricePerDayFormatted'])->toBe('-')
        ->and($rows['04/09/2026']['subtotal'])->toBe(0)
        ->and($rows['04/09/2026']['subtotalFormatted'])->toBe('Rp 0')
        ->and($rows['07/09/2026']['status'])->toBe('Off')
        ->and($rows['07/09/2026']['pricePerDayFormatted'])->toBe('-')
        ->and($rows['07/09/2026']['subtotal'])->toBe(0)
        ->and($rows['07/09/2026']['subtotalFormatted'])->toBe('Rp 0')
        ->and($rows)->not->toHaveKey('08/09/2026')
        ->and($invoice['total'])->toBe(17000);
});

it('summarises every status count in the individual invoice', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $category = CateringCategory::factory()->create(['price_per_day' => 17000]);
    $member = CateringMember::factory()->create([
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);
    saveSeptemberAttendance($member, ikut: 8, sakit: 2, izin: 2, alfa: 2, tidakIkut: 2, ujian: 2, eventUnit: 1, puasa: 1, libur: 1);

    $invoice = invoiceService()->buildMemberInvoice($member->fresh(['cateringCategory', 'schoolClass']), ...invoicePeriod());

    $text = invoicePdfText(invoiceService()->renderPdf(
        $invoice,
    ));

    expect($invoice['countTidakIkut'])->toBe(2)
        ->and($invoice['countUjian'])->toBe(2)
        ->and($invoice['countEventUnit'])->toBe(1)
        ->and($invoice['countPuasa'])->toBe(1)
        ->and($invoice['countLibur'])->toBe(0)
        ->and($text)->toContain('REKAP ABSENSI')
        ->and($text)->toContain('BUDAYA MAKAN')
        ->and($text)->not->toContain('ALFA')
        ->and($text)->toContain('OFF')
        ->and($text)->not->toContain('TIDAK IKUT')
        ->and($text)->toContain('UJIAN')
        ->and($text)->toContain('EVENT UNIT')
        ->and($text)->toContain('PUASA')
        ->and($text)->toContain('TOTAL TAGIHAN')
        ->and($text)->toContain('Rp 136.000')
        ->and($text)->not->toContain('Nasi Ayam')
        ->and($text)->not->toContain('Nasi Telur');
});

it('renders official payment and dynamic confirmation details on an individual invoice', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $category = CateringCategory::factory()->create(['price_per_day' => 17000]);
    $member = CateringMember::factory()->create([
        'name' => 'Ahmad Zaki',
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);
    saveSeptemberAttendance($member, ikut: 2, ujian: 1, eventUnit: 1, puasa: 1, libur: 1);

    $invoice = invoiceService()->buildMemberInvoice(
        $member->fresh(['cateringCategory', 'schoolClass']),
        ...invoicePeriod(),
    );
    $text = invoicePdfText(invoiceService()->renderPdf($invoice));

    expect($invoice['total'])->toBe(34000)
        ->and($invoice['quantity'])->toBe(2)
        ->and($invoice['confirmationReference'])->toBe('AHMAD ZAKI - 7A - SUDAH TRANSFER')
        ->not->toContain('INV/')
        ->and($text)->toContain('PEMBAYARAN')
        ->and($text)->toContain('KONFIRMASI PEMBAYARAN')
        ->and($text)->toContain('BCA')
        ->and($text)->toContain('5222129702')
        ->and($text)->toContain('Rara Nurfatimah')
        ->and($text)->toContain('0813-9994-7699')
        ->and($text)->toContain('AHMAD ZAKI - 7A - SUDAH TRANSFER')
        ->and($text)->toContain('INV/CAF/09/2026/'.$member->id)
        ->and($text)->toContain('Ujian')
        ->and($text)->toContain('Event Unit')
        ->and($text)->toContain('Puasa')
        ->and($text)->not->toContain('KETERANGAN')
        ->and($text)->not->toContain('08/09/2026 Libur');
});

it('shows guardian, invoice number and period identity in the individual invoice', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $category = CateringCategory::factory()->create(['price_per_day' => 17000]);
    $member = CateringMember::factory()->create([
        'name' => 'Ahmad Zaki',
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
        'guardian_name' => 'Bapak Zaki',
        'guardian_phone' => '081234567890',
    ]);
    saveSeptemberAttendance($member, ikut: 5);

    $text = invoicePdfText(invoiceService()->renderPdf(
        invoiceService()->buildMemberInvoice($member->fresh(['cateringCategory', 'schoolClass']), ...invoicePeriod()),
    ));

    expect($text)->toContain('Ahmad Zaki')
        ->and($text)->toContain('7A')
        ->and($text)->toContain('Siswa')
        ->and($text)->toContain('Bapak Zaki')
        ->and($text)->toContain('081234567890')
        ->and($text)->toContain('INV/CAF/09/2026/'.$member->id)
        ->and($text)->toContain('01 - 30 September 2026');
});

it('omits guardian rows with a dash when guardian data is missing', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $category = CateringCategory::factory()->create(['price_per_day' => 15000]);
    $member = CateringMember::factory()->create([
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
        'guardian_name' => null,
        'guardian_phone' => null,
    ]);
    saveSeptemberAttendance($member, ikut: 5);

    $text = invoicePdfText(invoiceService()->renderPdf(
        invoiceService()->buildMemberInvoice($member->fresh(['cateringCategory', 'schoolClass']), ...invoicePeriod()),
    ));

    expect($text)->toContain('Wali -')
        ->and($text)->toContain('Telp. Wali -')
        ->and($text)->toContain('Rp 75.000');
});

it('returns an inline class summary pdf for the selected class', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $member = CateringMember::factory()->create(['school_class_id' => $schoolClass->id]);
    saveSeptemberAttendance($member, ikut: 6, sakit: 1);

    $response = $this->actingAs(User::factory()->admin()->create())
        ->get(classInvoiceRoute($schoolClass));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename="Invoice-Catering-Kelas-7A-September-2026.pdf"');

    expect($response->streamedContent())->toStartWith('%PDF-');
});

it('includes only the selected class participants in the class summary pdf', function () {
    $selectedClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $otherClass = SchoolClass::factory()->create(['name' => '7B', 'level' => '7']);
    $inside = CateringMember::factory()->create(['name' => 'Ahmad Zaki', 'school_class_id' => $selectedClass->id]);
    $outside = CateringMember::factory()->create(['name' => 'Budi Luar Kelas', 'school_class_id' => $otherClass->id]);
    saveSeptemberAttendance($inside, ikut: 4);
    saveSeptemberAttendance($outside, ikut: 9);

    [$start, $end] = invoicePeriod();
    $invoice = invoiceService()->buildClassInvoice($selectedClass, $start, $end);

    expect($invoice['className'])->toBe('7A')
        ->and($invoice['participants'])->toBe(1)
        ->and(array_column($invoice['rows'], 'memberName'))->toBe(['Ahmad Zaki']);

    $text = invoicePdfText(invoiceService()->renderClassPdf($invoice));

    expect($text)->toContain('INVOICE CATERING KELAS')
        ->and($text)->toContain('Ahmad Zaki')
        ->and($text)->not->toContain('Budi Luar Kelas')
        ->and($text)->toContain('INV/CAF/09/2026/CLASS-'.$selectedClass->id);
});

it('renders official payment and class confirmation details on the class invoice', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $member = CateringMember::factory()->create([
        'name' => 'Ahmad Zaki',
        'school_class_id' => $schoolClass->id,
    ]);
    saveSeptemberAttendance($member, ikut: 3);

    $invoice = invoiceService()->buildClassInvoice($schoolClass, ...invoicePeriod());
    $text = invoicePdfText(invoiceService()->renderClassPdf($invoice));

    expect($invoice['confirmationReference'])->toBe('7A - SEPTEMBER 2026 - SUDAH TRANSFER')
        ->not->toContain('INV/');

    expect($text)->toContain('PEMBAYARAN')
        ->and($text)->toContain('KONFIRMASI PEMBAYARAN')
        ->and($text)->toContain('BCA')
        ->and($text)->toContain('5222129702')
        ->and($text)->toContain('Rara Nurfatimah')
        ->and($text)->toContain('0813-9994-7699')
        ->and($text)->toContain('7A - SEPTEMBER 2026 - SUDAH TRANSFER')
        ->and($text)->toContain('INV/CAF/09/2026/CLASS-'.$schoolClass->id)
        ->and($text)->not->toContain('AHMAD ZAKI - 7A - SUDAH TRANSFER');
});

it('reports per status counts and the participant total in the class summary', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $member = CateringMember::factory()->create(['school_class_id' => $schoolClass->id]);
    saveSeptemberAttendance($member, ikut: 5, sakit: 2, izin: 1, alfa: 2, tidakIkut: 2, ujian: 2, eventUnit: 1, puasa: 1, libur: 4);

    [$start, $end] = invoicePeriod();
    $invoice = invoiceService()->buildClassInvoice($schoolClass, $start, $end);
    $row = $invoice['rows'][0];

    expect($row['ikut'])->toBe(5)
        ->and($row['sakit'])->toBe(2)
        ->and($row['izin'])->toBe(1)
        ->and($row['alfa'])->toBe(2)
        ->and($row['tidakIkut'])->toBe(2)
        ->and($row['ujian'])->toBe(2)
        ->and($row['eventUnit'])->toBe(1)
        ->and($row['puasa'])->toBe(1)
        ->and($row['libur'])->toBe(0)
        ->and($row['total'])->toBe(5 * $row['pricePerDay'])
        ->and($invoice['totalIkut'])->toBe(5)
        ->and($invoice['totalSakit'])->toBe(2)
        ->and($invoice['totalIzin'])->toBe(1)
        ->and($invoice['totalAlfa'])->toBe(2)
        ->and($invoice['totalTidakIkut'])->toBe(2)
        ->and($invoice['totalUjian'])->toBe(2)
        ->and($invoice['totalEventUnit'])->toBe(1)
        ->and($invoice['totalPuasa'])->toBe(1)
        ->and($invoice['totalLibur'])->toBe(0);

    $text = invoicePdfText(invoiceService()->renderClassPdf($invoice));

    expect($text)->toContain('NAMA SISWA')
        ->and($text)->toContain('HARGA / AKTIF')
        ->and($text)->toContain('TOTAL')
        ->and($text)->toContain('BUDAYA MAKAN')
        ->and($text)->not->toContain('ALFA')
        ->and($text)->toContain('OFF')
        ->and($text)->not->toContain('TDK IKUT')
        ->and($text)->toContain('UJIAN')
        ->and($text)->toContain('EVENT')
        ->and($text)->toContain('PUASA')
        ->and($text)->toContain('LIBUR')
        ->and($text)->not->toContain('Nasi Ayam');
});

it('uses each participant own category price in the class summary total', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $expensive = CateringCategory::factory()->create(['price_per_day' => 20000]);
    $cheap = CateringCategory::factory()->create(['price_per_day' => 10000]);
    $first = CateringMember::factory()->create([
        'name' => 'Ahmad Zaki',
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $expensive->id,
    ]);
    $second = CateringMember::factory()->create([
        'name' => 'Aisyah Putri',
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $cheap->id,
    ]);
    saveSeptemberAttendance($first, ikut: 3);
    saveSeptemberAttendance($second, ikut: 4);

    [$start, $end] = invoicePeriod();
    $invoice = invoiceService()->buildClassInvoice($schoolClass, $start, $end);
    $rows = collect($invoice['rows'])->keyBy('memberName');

    expect($rows['Ahmad Zaki']['pricePerDay'])->toBe(20000)
        ->and($rows['Ahmad Zaki']['total'])->toBe(60000)
        ->and($rows['Aisyah Putri']['pricePerDay'])->toBe(10000)
        ->and($rows['Aisyah Putri']['total'])->toBe(40000)
        ->and($invoice['grandTotal'])->toBe(100000)
        ->and($invoice['grandTotalFormatted'])->toBe('Rp 100.000');
});

it('does not average participant prices in the class summary grand total', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $expensive = CateringCategory::factory()->create(['price_per_day' => 30000]);
    $cheap = CateringCategory::factory()->create(['price_per_day' => 10000]);
    $first = CateringMember::factory()->create([
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $expensive->id,
    ]);
    $second = CateringMember::factory()->create([
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $cheap->id,
    ]);
    saveSeptemberAttendance($first, ikut: 2);
    saveSeptemberAttendance($second, ikut: 2);

    [$start, $end] = invoicePeriod();
    $invoice = invoiceService()->buildClassInvoice($schoolClass, $start, $end);

    expect($invoice['grandTotal'])->toBe(80000)
        ->and($invoice['totalIkut'])->toBe(4);
});

it('refuses a class summary when the class has no saved attendance', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    CateringMember::factory()->create(['school_class_id' => $schoolClass->id]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(classInvoiceRoute($schoolClass))
        ->assertNotFound();
});

it('refuses a class summary for an inactive class', function () {
    $schoolClass = SchoolClass::factory()->inactive()->create(['name' => '7A', 'level' => '7']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(classInvoiceRoute($schoolClass))
        ->assertNotFound();
});

it('links the class summary invoice to a new tab preview endpoint', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $member = CateringMember::factory()->create(['school_class_id' => $schoolClass->id]);
    saveSeptemberAttendance($member, ikut: 4);

    $markup = attendanceMarkup(openInvoiceAttendanceMatrix($schoolClass));

    expect($markup)->toContain('Cetak Invoice Kelas')
        ->and($markup)->toContain(classInvoiceRoute($schoolClass))
        ->and($markup)->toContain('target="_blank"');
});

it('keeps the class summary link available right after an auto-saved change', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $member = CateringMember::factory()->create(['school_class_id' => $schoolClass->id]);
    saveSeptemberAttendance($member, ikut: 4);

    $component = openInvoiceAttendanceMatrix($schoolClass)
        ->call('setCellStatus', $member->id, '2026-09-20', 'alfa')
        ->assertSet('saveState.saved', true);

    expect(attendanceMarkup($component))->toContain(classInvoiceRoute($schoolClass));
});

it('hides the class summary action for the employee participant group', function () {
    $employeeCategory = CateringCategory::factory()->employee()->create();
    CateringMember::factory()->withoutClass()->create(['catering_category_id' => $employeeCategory->id]);

    $markup = attendanceMarkup(openInvoiceAttendanceMatrix(
        null,
        CateringParticipantGroup::Employee,
    ));

    expect($markup)->not->toContain('Cetak Invoice Kelas');
});

it('does not introduce an n+1 pattern when building a class summary invoice', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $category = CateringCategory::factory()->create(['price_per_day' => 15000]);
    $members = CateringMember::factory()->count(6)->create([
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);

    foreach ($members as $member) {
        saveSeptemberAttendance($member, ikut: 4);
    }

    DB::enableQueryLog();
    invoiceService()->buildClassInvoice($schoolClass, ...invoicePeriod());
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBeLessThanOrEqual(4);
});

it('does not modify saved attendance while generating a class summary invoice', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $members = CateringMember::factory()->count(2)->create(['school_class_id' => $schoolClass->id]);

    foreach ($members as $member) {
        saveSeptemberAttendance($member, ikut: 5, sakit: 1);
    }

    $before = CateringAttendance::query()->orderBy('id')->get(['catering_member_id', 'attendance_date', 'status'])->toArray();

    $this->actingAs(User::factory()->admin()->create())->get(classInvoiceRoute($schoolClass))->assertOk();

    expect(CateringAttendance::query()->orderBy('id')->get(['catering_member_id', 'attendance_date', 'status'])->toArray())
        ->toEqual($before);
});

it('leaves employees out of the class summary even when they carry a class', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $studentCategory = CateringCategory::factory()->create(['name' => 'Siswa Umum', 'price_per_day' => 15000]);
    $employeeCategory = CateringCategory::factory()->employee()->create(['name' => 'Guru', 'price_per_day' => 20000]);
    $student = CateringMember::factory()->create([
        'name' => 'Ahmad Zaki',
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $studentCategory->id,
    ]);
    $employee = CateringMember::factory()->create([
        'name' => 'Guru Pindah Kelas',
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $employeeCategory->id,
    ]);
    saveSeptemberAttendance($student, ikut: 5);
    saveSeptemberAttendance($employee, ikut: 4);

    [$start, $end] = invoicePeriod();
    $invoice = invoiceService()->buildClassInvoice($schoolClass, $start, $end);

    expect($invoice['participants'])->toBe(1)
        ->and($invoice['grandTotal'])->toBe(75000)
        ->and(array_column($invoice['rows'], 'memberId'))->toBe([$student->id]);

    $text = invoicePdfText(invoiceService()->renderClassPdf($invoice));

    expect($text)->toContain('Ahmad Zaki')
        ->and($text)->not->toContain('Guru Pindah Kelas');
});

it('writes student wording into the rendered pdf', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $category = CateringCategory::factory()->create(['name' => 'Siswa Umum', 'price_per_day' => 15000]);
    $member = CateringMember::factory()->create([
        'name' => 'Ahmad Zaki',
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);
    saveSeptemberAttendance($member, ikut: 18, sakit: 2);

    [$start, $end] = invoicePeriod();
    $invoice = invoiceService()->buildMemberInvoice(
        $member->fresh(['cateringCategory', 'schoolClass']),
        $start,
        $end,
    );

    $text = invoicePdfText(invoiceService()->renderPdf($invoice));

    expect($text)->toContain('CATERING SISWA')
        ->and($text)->toContain('Ahmad Zaki')
        ->and($text)->toContain('Kelas')
        ->and($text)->toContain('7A')
        ->and($text)->toContain('Rp 270.000')
        ->and($text)->not->toContain('CATERING PEGAWAI')
        ->and($text)->not->toContain('Transfer');
});

it('writes employee wording into the rendered pdf', function () {
    $category = CateringCategory::factory()->employee()->create(['name' => 'Guru', 'price_per_day' => 20000]);
    $member = CateringMember::factory()->withoutClass()->create([
        'name' => 'Budi Santoso',
        'catering_category_id' => $category->id,
    ]);
    saveSeptemberAttendance($member, ikut: 20, sakit: 1);

    [$start, $end] = invoicePeriod();
    $invoice = invoiceService()->buildMemberInvoice(
        $member->fresh(['cateringCategory', 'schoolClass']),
        $start,
        $end,
    );

    $text = invoicePdfText(invoiceService()->renderPdf($invoice));

    expect($text)->toContain('CATERING PEGAWAI')
        ->and($text)->toContain('Budi Santoso')
        ->and($text)->toContain('Kelompok')
        ->and($text)->toContain('Pegawai')
        ->and($text)->toContain('Rp 400.000')
        ->and($text)->not->toContain('CATERING SISWA')
        ->and($text)->not->toContain('Kelas');
});

it('counts pdf pages so the one page layout can be asserted', function () {
    $single = Pdf::loadHTML('<html><body><p>one</p></body></html>')->setPaper('a4')->output();
    $double = Pdf::loadHTML('<html><body><p>one</p><div style="page-break-after: always"></div><p>two</p></body></html>')
        ->setPaper('a4')
        ->output();

    expect(invoicePdfPageCount($single))->toBe(1)
        ->and(invoicePdfPageCount($double))->toBe(2);
});

it('fits a 31 day individual invoice on a single a4 page', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $category = CateringCategory::factory()->create(['price_per_day' => 15000]);
    $member = CateringMember::factory()->create([
        'name' => 'Ahmad Zaki',
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);

    [$start, $end] = saveMonthAttendance($member, 2026, 1, 31, invoiceAttendancePlan());

    $invoice = invoiceService()->buildMemberInvoice(
        $member->fresh(['cateringCategory', 'schoolClass']),
        $start,
        $end,
    );

    expect(count($invoice['attendanceRows']))->toBe(22);

    expect(invoicePdfPageCount(invoiceService()->renderPdf($invoice)))->toBe(1);
});

it('fits a shorter month individual invoice on a single a4 page', function (int $month, int $days) {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A-'.$days, 'level' => '7']);
    $category = CateringCategory::factory()->create(['price_per_day' => 17000]);
    $member = CateringMember::factory()->create([
        'name' => 'Ahmad Zaki',
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);

    [$start, $end] = saveMonthAttendance($member, 2026, $month, $days, invoiceAttendancePlan());

    expect(invoicePdfPageCount(invoiceService()->renderPdf(
        invoiceService()->buildMemberInvoice($member->fresh(['cateringCategory', 'schoolClass']), $start, $end),
    )))->toBe(1);
})->with([
    '28 day month' => [2, 28],
    '30 day month' => [4, 30],
]);

it('prints every saved non-libur day regardless of weekday', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $category = CateringCategory::factory()->create(['price_per_day' => 15000]);
    $member = CateringMember::factory()->create([
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);

    [$start, $end] = saveMonthAttendance($member, 2026, 1, 31, invoiceAttendancePlan());

    $text = invoicePdfText(invoiceService()->renderPdf(
        invoiceService()->buildMemberInvoice($member->fresh(['cateringCategory', 'schoolClass']), $start, $end),
    ));

    foreach (invoiceWeekdayDates(2026, 1) as $weekday) {
        expect($text)->toContain(invoiceDateLabel($weekday));
    }
});

it('still shows every invoice-visible status on one page', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $category = CateringCategory::factory()->create(['price_per_day' => 15000]);
    $member = CateringMember::factory()->create([
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);

    [$start, $end] = saveMonthAttendance($member, 2026, 1, 31, invoiceAttendancePlan());

    $text = invoicePdfText(invoiceService()->renderPdf(
        invoiceService()->buildMemberInvoice($member->fresh(['cateringCategory', 'schoolClass']), $start, $end),
    ));

    expect($text)->toContain('AKTIF')
        ->and($text)->toContain('SAKIT')
        ->and($text)->toContain('IZIN')
        ->and($text)->toContain('BUDAYA MAKAN')
        ->and($text)->not->toContain('ALFA')
        ->and($text)->toContain('OFF')
        ->and($text)->not->toContain('TIDAK IKUT')
        ->and($text)->toContain('UJIAN')
        ->and($text)->toContain('EVENT UNIT')
        ->and($text)->toContain('PUASA')
        ->and($text)->not->toContain('KETERANGAN')
        ->and($text)->toContain('RINCIAN ABSENSI')
        ->and($text)->toContain('REKAP ABSENSI')
        ->and($text)->toContain('TOTAL TAGIHAN')
        ->and($text)->toContain('Rp 180.000');
});

it('keeps the unpaid statuses at zero on a one page invoice', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $category = CateringCategory::factory()->create(['price_per_day' => 15000]);
    $member = CateringMember::factory()->create([
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);

    [$start, $end] = saveMonthAttendance($member, 2026, 1, 31, invoiceAttendancePlan());

    $invoice = invoiceService()->buildMemberInvoice(
        $member->fresh(['cateringCategory', 'schoolClass']),
        $start,
        $end,
    );

    $unpaid = array_values(array_filter(
        $invoice['attendanceRows'],
        fn (array $row): bool => ! $row['isBillable'],
    ));

    expect($invoice['attendanceRows'])->toHaveCount(22)
        ->and($unpaid)->toHaveCount(10)
        ->and($invoice['total'])->toBe(180000)
        ->and($invoice['totalFormatted'])->toBe('Rp 180.000');

    foreach ($unpaid as $row) {
        expect($row['subtotal'])->toBe(0)
            ->and($row['subtotalFormatted'])->toBe('Rp 0')
            ->and($row)->not->toHaveKey('note');
    }
});

it('still renders the one page layout for an employee invoice without a class', function () {
    $category = CateringCategory::factory()->employee()->create(['price_per_day' => 20000]);
    $member = CateringMember::factory()->withoutClass()->create([
        'name' => 'Budi Santoso',
        'catering_category_id' => $category->id,
    ]);

    [$start, $end] = saveMonthAttendance($member, 2026, 3, 31, invoiceAttendancePlan());

    $bytes = invoiceService()->renderPdf(
        invoiceService()->buildMemberInvoice($member->fresh(['cateringCategory', 'schoolClass']), $start, $end),
    );

    expect(invoicePdfPageCount($bytes))->toBe(1)
        ->and(invoicePdfText($bytes))->toContain('CATERING PEGAWAI');
});

it('renders the one page layout for a member with no saved attendance', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $member = CateringMember::factory()->create(['school_class_id' => $schoolClass->id]);

    $bytes = invoiceService()->renderPdf(
        invoiceService()->buildMemberInvoice(
            $member->fresh(['cateringCategory', 'schoolClass']),
            ...invoicePeriod(),
        ),
    );

    expect(invoicePdfPageCount($bytes))->toBe(1)
        ->and(invoicePdfText($bytes))->toContain('Belum ada absensi tersimpan untuk periode ini.');
});

it('serves the one page layout through the inline preview endpoint', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $category = CateringCategory::factory()->create(['price_per_day' => 15000]);
    $member = CateringMember::factory()->create([
        'name' => 'Ahmad Zaki',
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);
    saveMonthAttendance($member, 2026, 1, 31, invoiceAttendancePlan());

    $response = $this->actingAs(User::factory()->admin()->create())
        ->get(memberInvoiceRoute($member, $schoolClass, CateringParticipantGroup::Student, 1, 2026));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename="Invoice-Ahmad-Zaki-7A-Januari-2026.pdf"');

    expect(invoicePdfPageCount($response->streamedContent()))->toBe(1);
});

it('keeps bulk zip entries on the same one page layout', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $category = CateringCategory::factory()->create(['price_per_day' => 15000]);
    $members = CateringMember::factory()->count(2)->create([
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);

    foreach ($members as $member) {
        saveMonthAttendance($member, 2026, 1, 31, invoiceAttendancePlan());
    }

    $component = openInvoiceAttendanceMatrix($schoolClass);
    $component->call('changeFilter', 'month', 1);
    $component->call('downloadAllInvoices');

    $zip = downloadedContent($component);
    $entries = invoiceZipEntries($zip);

    expect($entries)->toHaveCount(2);

    foreach ($entries as $entry) {
        $pdf = invoiceZipEntryContent($zip, $entry);
        $text = invoicePdfText($pdf);

        expect(invoicePdfPageCount($pdf))->toBe(1)
            ->and($text)->toContain('TOTAL TAGIHAN')
            ->and($text)->toContain('PEMBAYARAN')
            ->and($text)->toContain('KONFIRMASI PEMBAYARAN')
            ->and($text)->toContain('BCA')
            ->and($text)->toContain('5222129702')
            ->and($text)->toContain('Rara Nurfatimah')
            ->and($text)->toContain('0813-9994-7699')
            ->and($text)->toContain('SUDAH TRANSFER');
    }
});

/*
|--------------------------------------------------------------------------
 | Only libur rows stay stored without reaching an invoice
|--------------------------------------------------------------------------
|
 | October 2026 starts on a Thursday. This fixture includes libur on a weekday
 | and weekend, plus non-libur statuses on weekends, proving visibility follows
 | the saved status and not the calendar day.
|
*/

/**
 * @return array<string, CateringAttendanceStatus>
 */
function octoberInvoiceVisibilityAttendance(): array
{
    return [
        '2026-10-01' => CateringAttendanceStatus::Ikut,
        '2026-10-02' => CateringAttendanceStatus::Libur,
        '2026-10-03' => CateringAttendanceStatus::Ujian,
        '2026-10-04' => CateringAttendanceStatus::EventUnit,
        '2026-10-05' => CateringAttendanceStatus::Ikut,
        '2026-10-06' => CateringAttendanceStatus::Sakit,
        '2026-10-07' => CateringAttendanceStatus::Izin,
        '2026-10-08' => CateringAttendanceStatus::Alfa,
        '2026-10-09' => CateringAttendanceStatus::TidakIkut,
        '2026-10-10' => CateringAttendanceStatus::Puasa,
        '2026-10-11' => CateringAttendanceStatus::Libur,
    ];
}

/**
 * @return array{0: CarbonImmutable, 1: CarbonImmutable}
 */
function octoberPeriod(): array
{
    $start = CarbonImmutable::create(2026, 10, 1)->startOfMonth();

    return [$start, $start->endOfMonth()];
}

/**
 * A member carrying the visibility fixture, already refreshed for invoicing.
 *
 * @return array{0: CateringMember, 1: SchoolClass, 2: CateringCategory}
 */
function octoberInvoiceFixture(int $pricePerDay = 17000): array
{
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $category = CateringCategory::factory()->create(['name' => 'Siswa Umum', 'price_per_day' => $pricePerDay]);
    $member = CateringMember::factory()->create([
        'name' => 'Ahmad Zaki',
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);

    foreach (octoberInvoiceVisibilityAttendance() as $date => $status) {
        CateringAttendance::factory()->for($member)->create([
            'attendance_date' => $date,
            'status' => $status,
        ]);
    }

    return [$member->fresh(['cateringCategory', 'schoolClass']), $schoolClass, $category];
}

it('omits libur from the individual invoice regardless of weekday', function (string $liburDate) {
    [$member] = octoberInvoiceFixture();

    $invoice = invoiceService()->buildMemberInvoice($member, ...octoberPeriod());
    $text = invoicePdfText(invoiceService()->renderPdf($invoice));

    expect(array_column($invoice['attendanceRows'], 'date'))->not->toContain($liburDate)
        ->and($text)->not->toContain($liburDate.' Libur');
})->with([
    'weekday libur' => ['02/10/2026'],
    'weekend libur' => ['11/10/2026'],
]);

it('keeps every non-libur row of the individual invoice', function () {
    [$member] = octoberInvoiceFixture();

    $invoice = invoiceService()->buildMemberInvoice($member, ...octoberPeriod());

    expect(array_column($invoice['attendanceRows'], 'date'))->toBe([
        '01/10/2026',
        '03/10/2026',
        '04/10/2026',
        '05/10/2026',
        '06/10/2026',
        '07/10/2026',
        '08/10/2026',
        '09/10/2026',
        '10/10/2026',
    ]);
});

it('keeps each non-libur unpaid status visible in the individual invoice', function (CateringAttendanceStatus $status, string $date) {
    [$member] = octoberInvoiceFixture();

    $rows = collect(invoiceService()->buildMemberInvoice($member, ...octoberPeriod())['attendanceRows'])
        ->keyBy('date');

    expect($rows[$date]['status'])->toBe($status->label())
        ->and($rows[$date]['isBillable'])->toBeFalse()
        ->and($rows[$date]['subtotal'])->toBe(0)
        ->and($rows[$date]['subtotalFormatted'])->toBe('Rp 0')
        ->and($rows[$date])->not->toHaveKey('note');
})->with([
    'sakit' => [CateringAttendanceStatus::Sakit, '06/10/2026'],
    'izin' => [CateringAttendanceStatus::Izin, '07/10/2026'],
    'alfa' => [CateringAttendanceStatus::Alfa, '08/10/2026'],
    'tidak ikut' => [CateringAttendanceStatus::TidakIkut, '09/10/2026'],
    'ujian on saturday' => [CateringAttendanceStatus::Ujian, '03/10/2026'],
    'event unit on sunday' => [CateringAttendanceStatus::EventUnit, '04/10/2026'],
    'puasa on saturday' => [CateringAttendanceStatus::Puasa, '10/10/2026'],
]);

it('recaps only statuses visible in the individual invoice', function () {
    [$member] = octoberInvoiceFixture();

    $invoice = invoiceService()->buildMemberInvoice($member, ...octoberPeriod());

    expect($invoice['countLibur'])->toBe(0)
        ->and($invoice['countIkut'])->toBe(2)
        ->and($invoice['countSakit'])->toBe(1)
        ->and($invoice['countIzin'])->toBe(1)
        ->and($invoice['countAlfa'])->toBe(1)
        ->and($invoice['countTidakIkut'])->toBe(1)
        ->and($invoice['countUjian'])->toBe(1)
        ->and($invoice['countEventUnit'])->toBe(1)
        ->and($invoice['countPuasa'])->toBe(1);
});

it('keeps the bill equal to the printed rows while libur stays hidden', function () {
    [$member, , $category] = octoberInvoiceFixture();

    $invoice = invoiceService()->buildMemberInvoice($member, ...octoberPeriod());
    $printedSubtotal = array_sum(array_column($invoice['attendanceRows'], 'subtotal'));

    expect($invoice['quantity'])->toBe(2)
        ->and($invoice['total'])->toBe(2 * $category->price_per_day)
        ->and($invoice['totalFormatted'])->toBe('Rp 34.000')
        ->and($printedSubtotal)->toBe($invoice['total'])
        ->and($invoice['savedDays'])->toBe(11);
});

it('numbers the printed rows without gaps left by hidden libur dates', function () {
    [$member] = octoberInvoiceFixture();

    $rows = invoiceService()->buildMemberInvoice($member, ...octoberPeriod())['attendanceRows'];

    expect(array_column($rows, 'number'))->toBe([1, 2, 3, 4, 5, 6, 7, 8, 9]);
});

it('includes a weekend marked as ikut in both the rows and the bill', function () {
    [$member, , $category] = octoberInvoiceFixture();

    CateringAttendance::query()
        ->where('catering_member_id', $member->id)
        ->whereDate('attendance_date', '2026-10-11')
        ->update(['status' => CateringAttendanceStatus::Ikut->value]);

    $invoice = invoiceService()->buildMemberInvoice($member->fresh(['cateringCategory']), ...octoberPeriod());

    expect($invoice['quantity'])->toBe(3)
        ->and($invoice['total'])->toBe(3 * $category->price_per_day)
        ->and(array_column($invoice['attendanceRows'], 'date'))->toContain('11/10/2026');
});

it('prints only the working days of a fully initialised october', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $category = CateringCategory::factory()->create(['price_per_day' => 15000]);
    $member = CateringMember::factory()->create([
        'name' => 'Ahmad Zaki',
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);

    CateringAttendanceInitializerService::ensureMonth(2026, 10);

    $invoice = invoiceService()->buildMemberInvoice($member->fresh(['cateringCategory', 'schoolClass']), ...octoberPeriod());
    $bytes = invoiceService()->renderPdf($invoice);
    $text = invoicePdfText($bytes);

    expect(CateringAttendance::query()->where('catering_member_id', $member->id)->count())->toBe(31)
        ->and(array_column($invoice['attendanceRows'], 'date'))
        ->toBe(array_map('invoiceDateLabel', invoiceWeekdayDates(2026, 10)))
        ->and($invoice['quantity'])->toBe(22)
        ->and($invoice['countLibur'])->toBe(0)
        ->and($invoice['total'])->toBe(22 * 15000)
        ->and(invoicePdfPageCount($bytes))->toBe(1);

    foreach (invoiceWeekendDates(2026, 10) as $weekend) {
        expect($text)->not->toContain(invoiceDateLabel($weekend).' Libur');
    }
});

it('keeps libur out of class status counts while preserving saved day totals', function () {
    [$member, $schoolClass] = octoberInvoiceFixture();

    $invoice = invoiceService()->buildClassInvoice($schoolClass, ...octoberPeriod());
    $row = $invoice['rows'][0];

    expect($row['ikut'])->toBe(2)
        ->and($row['sakit'])->toBe(1)
        ->and($row['izin'])->toBe(1)
        ->and($row['alfa'])->toBe(1)
        ->and($row['tidakIkut'])->toBe(1)
        ->and($row['ujian'])->toBe(1)
        ->and($row['eventUnit'])->toBe(1)
        ->and($row['puasa'])->toBe(1)
        ->and($row['libur'])->toBe(0)
        ->and($row['savedDays'])->toBe(11)
        ->and($row['total'])->toBe(2 * $row['pricePerDay'])
        ->and($invoice['totalIkut'])->toBe(2)
        ->and($invoice['totalUjian'])->toBe(1)
        ->and($invoice['totalEventUnit'])->toBe(1)
        ->and($invoice['totalPuasa'])->toBe(1)
        ->and($invoice['totalLibur'])->toBe(0)
        ->and($invoice['grandTotal'])->toBe(2 * 17000);
});

it('leaves all attendance rows stored while invoicing', function () {
    [$member, $schoolClass] = octoberInvoiceFixture();

    $snapshot = fn (): array => CateringAttendance::query()
        ->orderBy('id')
        ->get(['catering_member_id', 'attendance_date', 'status', 'updated_at'])
        ->toArray();

    $before = $snapshot();

    invoiceService()->buildMemberInvoice($member, ...octoberPeriod());
    invoiceService()->buildClassInvoice($schoolClass, ...octoberPeriod());

    $this->actingAs(User::factory()->admin()->create())
        ->get(memberInvoiceRoute($member, $schoolClass, CateringParticipantGroup::Student, 10, 2026))
        ->assertOk();

    expect($snapshot())->toEqual($before)
        ->and(CateringAttendance::query()
            ->get(['attendance_date', 'status'])
            ->filter(fn (CateringAttendance $row): bool => $row->status === CateringAttendanceStatus::Libur)
            ->count())->toBe(2);
});

/*
|--------------------------------------------------------------------------
 | Every delivery of an individual invoice shares one visibility rule
|--------------------------------------------------------------------------
|
| The preview endpoint, the service and the bulk archive all print the same
| prepared rows through the same template, so these tests walk each way an
| individual invoice reaches a parent instead of trusting a single code path.
|
*/

/**
 * The dates the rendered pdf bills, read back from the visible detail rows.
 *
 * @return array<int, string>
 */
function invoiceBilledDatesFromText(string $text): array
{
    $billableLabel = preg_quote(CateringAttendanceStatus::Ikut->label(), '/');
    preg_match_all('/(\d{2}\/\d{2}\/\d{4})\s+'.$billableLabel.'\s+Rp/', $text, $matches);

    return $matches[1];
}

it('applies libur-only visibility to the inline preview', function () {
    [$member, $schoolClass, $category] = octoberInvoiceFixture();

    $response = $this->actingAs(User::factory()->admin()->create())
        ->get(memberInvoiceRoute($member, $schoolClass, CateringParticipantGroup::Student, 10, 2026));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');

    $bytes = $response->streamedContent();
    $text = invoicePdfText($bytes);

    expect(invoicePdfPageCount($bytes))->toBe(1)
        ->and(invoiceBilledDatesFromText($text))->toBe(['01/10/2026', '05/10/2026'])
        ->and($text)->not->toContain('02/10/2026 Libur')
        ->and($text)->toContain('03/10/2026')
        ->and($text)->toContain('04/10/2026')
        ->and($text)->toContain('06/10/2026')
        ->and($text)->toContain('10/10/2026')
        ->and($text)->not->toContain('11/10/2026')
        ->and($text)->toContain('2 hari')
        ->and($text)->toContain('Rp '.number_format(2 * $category->price_per_day, 0, ',', '.'));
});

it('applies libur-only visibility to the service built individual pdf', function () {
    [$member] = octoberInvoiceFixture();

    $invoice = invoiceService()->buildMemberInvoice($member, ...octoberPeriod());
    $text = invoicePdfText(invoiceService()->renderPdf($invoice));

    expect(array_column($invoice['attendanceRows'], 'date'))->toBe([
        '01/10/2026', '03/10/2026', '04/10/2026', '05/10/2026', '06/10/2026', '07/10/2026', '08/10/2026', '09/10/2026', '10/10/2026',
    ])->and($text)->not->toContain('02/10/2026 Libur')
        ->and($text)->not->toContain('11/10/2026 Libur');
});

it('keeps every pdf inside the class zip free of libur rows', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $category = CateringCategory::factory()->create(['name' => 'Siswa Umum', 'price_per_day' => 15000]);
    $members = CateringMember::factory()->count(3)->create([
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);

    // Opening the attendance matrix is what stores the month: weekdays "ikut", weekends "libur".
    $component = openInvoiceAttendanceMatrix($schoolClass)
        ->call('changeFilter', 'month', 10);

    foreach ($members as $member) {
        $component->call('setCellStatus', $member->id, '2026-10-09', CateringAttendanceStatus::Libur->value);
    }

    $attendanceRowsBeforeZip = CateringAttendance::query()->count();

    $component->call('downloadAllInvoices')
        ->assertFileDownloaded('Invoice-Catering-7A-Oktober-2026.zip', contentType: 'application/zip');

    $zip = downloadedContent($component);
    $entries = invoiceZipEntries($zip);

    expect($entries)->toHaveCount(3);

    foreach ($entries as $entry) {
        $bytes = invoiceZipEntryContent($zip, $entry);
        $text = invoicePdfText($bytes);

        foreach (invoiceWeekendDates(2026, 10) as $weekend) {
            expect($text)->not->toContain(invoiceDateLabel($weekend).' Libur');
        }

        foreach (invoiceWeekdayDates(2026, 10) as $weekday) {
            if ($weekday === '2026-10-09') {
                expect($text)->not->toContain(invoiceDateLabel($weekday));
            } else {
                expect($text)->toContain(invoiceDateLabel($weekday));
            }
        }

        expect(invoicePdfPageCount($bytes))->toBe(1)
            ->and($text)->toContain('TOTAL TAGIHAN')
            ->and($text)->not->toContain('09/10/2026')
            ->and($text)->not->toContain('KETERANGAN')
            ->and(invoiceBilledDatesFromText($text))->toHaveCount(21)
            ->and($text)->toContain('21 hari')
            ->and($text)->toContain('Rp '.number_format(21 * $category->price_per_day, 0, ',', '.'));
    }

    expect(CateringAttendance::query()->count())->toBe($attendanceRowsBeforeZip);
});

it('prints the same rows and the same total in the class zip as in a direct pdf', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $category = CateringCategory::factory()->create(['name' => 'Siswa Umum', 'price_per_day' => 15000]);
    $member = CateringMember::factory()->create([
        'name' => 'Ahmad Zaki',
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);

    CateringAttendanceInitializerService::ensureMonth(2026, 10);
    CateringAttendance::query()
        ->where('catering_member_id', $member->id)
        ->whereDate('attendance_date', '2026-10-05')
        ->update(['status' => CateringAttendanceStatus::TidakIkut->value]);
    CateringAttendance::query()
        ->where('catering_member_id', $member->id)
        ->whereDate('attendance_date', '2026-10-06')
        ->update(['status' => CateringAttendanceStatus::Alfa->value]);

    $invoice = invoiceService()->buildMemberInvoice($member->fresh(['cateringCategory', 'schoolClass']), ...octoberPeriod());

    $direct = invoicePdfText(invoiceService()->renderPdf($invoice));

    $component = openInvoiceAttendanceMatrix($schoolClass)
        ->call('changeFilter', 'month', 10)
        ->call('downloadAllInvoices');
    $zip = downloadedContent($component);
    $entries = invoiceZipEntries($zip);
    $entry = $entries[0];
    $fromZip = invoicePdfText(invoiceZipEntryContent($zip, $entry));

    $printedDates = array_values(array_filter(
        array_map('invoiceDateLabel', invoiceMonthDates(2026, 10)),
        fn (string $date): bool => str_contains($fromZip, $date),
    ));

    expect($entries)->toHaveCount(1)
        ->and($entry)->toBe(invoiceService()->individualPdfFilename($invoice))
        ->and($printedDates)->toBe(array_column($invoice['attendanceRows'], 'date'))
        ->and(invoiceBilledDatesFromText($fromZip))->toBe(invoiceBilledDatesFromText($direct))
        ->and($direct)->toContain('Aktif')
        ->and($direct)->toContain('Off')
        ->and($direct)->toContain('Budaya Makan')
        ->and($fromZip)->toContain('Aktif')
        ->and($fromZip)->toContain('Off')
        ->and($fromZip)->toContain('Budaya Makan')
        ->and($fromZip)->not->toContain('Alfa')
        ->and($fromZip)->not->toContain('Tidak Ikut')
        ->and($invoice['confirmationReference'])->not->toContain('INV/')
        ->and($fromZip)->toContain($invoice['confirmationReference'])
        ->and($invoice['quantity'])->toBe(20)
        ->and($invoice['total'])->toBe(20 * $category->price_per_day)
        ->and($fromZip)->toContain('Rp '.number_format(20 * $category->price_per_day, 0, ',', '.'));
});
