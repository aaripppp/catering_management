<?php

use App\Enums\CateringAttendanceStatus;
use App\Enums\CateringParticipantGroup;
use App\Livewire\CateringAttendance\Index as CateringAttendanceIndex;
use App\Models\CateringAttendance;
use App\Models\CateringCategory;
use App\Models\CateringMember;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\CateringInvoiceService;
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
 */
function saveSeptemberAttendance(
    CateringMember $member,
    int $ikut,
    int $sakit = 0,
    int $izin = 0,
    int $alfa = 0,
    int $libur = 0,
): void {
    $statuses = array_merge(
        array_fill(0, $ikut, CateringAttendanceStatus::Ikut),
        array_fill(0, $sakit, CateringAttendanceStatus::Sakit),
        array_fill(0, $izin, CateringAttendanceStatus::Izin),
        array_fill(0, $alfa, CateringAttendanceStatus::Alfa),
        array_fill(0, $libur, CateringAttendanceStatus::Libur),
    );

    if (count($statuses) > 30) {
        throw new InvalidArgumentException('September 2026 only has 30 attendance days.');
    }

    $day = 1;

    foreach ($statuses as $status) {
        CateringAttendance::factory()->for($member)->create([
            'attendance_date' => sprintf('2026-09-%02d', $day),
            'status' => $status,
        ]);
        $day++;
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
 * exercised with 28, 30 and 31 attendance rows.
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
    $day = 1;

    foreach ($plan as [$status, $count]) {
        for ($index = 0; $index < $count && $day <= $days; $index++, $day++) {
            CateringAttendance::factory()->for($member)->create([
                'attendance_date' => sprintf('%04d-%02d-%02d', $year, $month, $day),
                'status' => $status,
            ]);
        }
    }

    return [$start, $start->endOfMonth()];
}

/**
 * A realistic mix that fills the month: 20 billed days and the rest spread
 * across the unpaid statuses.
 *
 * @return array<int, array{0: CateringAttendanceStatus, 1: int}>
 */
function invoiceAttendancePlan(): array
{
    return [
        [CateringAttendanceStatus::Ikut, 20],
        [CateringAttendanceStatus::Sakit, 4],
        [CateringAttendanceStatus::Izin, 3],
        [CateringAttendanceStatus::Alfa, 2],
        [CateringAttendanceStatus::Libur, 2],
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
    saveSeptemberAttendance($member, ikut: 12, sakit: 3, izin: 4, alfa: 2, libur: 6);

    [$start, $end] = invoicePeriod();

    $invoice = invoiceService()->buildMemberInvoice(
        $member->fresh(['cateringCategory', 'schoolClass']),
        $start,
        $end,
    );

    expect($invoice['quantity'])->toBe(12)
        ->and($invoice['savedDays'])->toBe(27);
});

it('computes the total with integer rupiah math from quantity times price', function () {
    $category = CateringCategory::factory()->create(['price_per_day' => 13500]);
    $member = CateringMember::factory()->create(['catering_category_id' => $category->id]);
    saveSeptemberAttendance($member, ikut: 17, sakit: 6);

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
        ->and($queryCount)->toBeLessThanOrEqual(4);
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

it('bills the current category price because attendance stores no price snapshot', function () {
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

it('lists every saved attendance date in the individual invoice detail table', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $category = CateringCategory::factory()->create(['price_per_day' => 17000]);
    $member = CateringMember::factory()->create([
        'name' => 'Ahmad Zaki',
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);
    saveSeptemberAttendance($member, ikut: 1, sakit: 1, izin: 1, alfa: 1, libur: 1);

    $text = invoicePdfText(invoiceService()->renderPdf(
        invoiceService()->buildMemberInvoice($member->fresh(['cateringCategory', 'schoolClass']), ...invoicePeriod()),
    ));

    expect($text)->toContain('RINCIAN ABSENSI')
        ->and($text)->toContain('01/09/2026')
        ->and($text)->toContain('02/09/2026')
        ->and($text)->toContain('03/09/2026')
        ->and($text)->toContain('04/09/2026')
        ->and($text)->toContain('05/09/2026')
        ->and($text)->toContain('TANGGAL')
        ->and($text)->toContain('STATUS')
        ->and($text)->toContain('KETERANGAN')
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
    saveSeptemberAttendance($member, ikut: 1, sakit: 1, izin: 1, alfa: 1, libur: 1);

    [$start, $end] = invoicePeriod();
    $invoice = invoiceService()->buildMemberInvoice($member->fresh(['cateringCategory', 'schoolClass']), $start, $end);
    $rows = collect($invoice['attendanceRows'])->keyBy('date');

    expect($rows['01/09/2026']['status'])->toBe('Ikut')
        ->and($rows['01/09/2026']['note'])->toBe('Dihitung')
        ->and($rows['01/09/2026']['subtotal'])->toBe(17000)
        ->and($rows['01/09/2026']['pricePerDayFormatted'])->toBe('Rp 17.000');

    foreach (['02/09/2026', '03/09/2026', '04/09/2026', '05/09/2026'] as $nonBillable) {
        expect($rows[$nonBillable]['note'])->toBe('Tidak dihitung')
            ->and($rows[$nonBillable]['subtotal'])->toBe(0)
            ->and($rows[$nonBillable]['pricePerDayFormatted'])->toBe('-');
    }

    expect($rows['02/09/2026']['status'])->toBe('Sakit')
        ->and($rows['03/09/2026']['status'])->toBe('Izin')
        ->and($rows['04/09/2026']['status'])->toBe('Alfa')
        ->and($rows['05/09/2026']['status'])->toBe('Libur')
        ->and($invoice['total'])->toBe(17000);
});

it('summarises every status count in the individual invoice', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $category = CateringCategory::factory()->create(['price_per_day' => 17000]);
    $member = CateringMember::factory()->create([
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);
    saveSeptemberAttendance($member, ikut: 18, sakit: 2, izin: 1, alfa: 0, libur: 9);

    $text = invoicePdfText(invoiceService()->renderPdf(
        invoiceService()->buildMemberInvoice($member->fresh(['cateringCategory', 'schoolClass']), ...invoicePeriod()),
    ));

    expect($text)->toContain('REKAP ABSENSI')
        ->and($text)->toContain('TOTAL TAGIHAN')
        ->and($text)->toContain('Rp 306.000')
        ->and($text)->not->toContain('Nasi Ayam')
        ->and($text)->not->toContain('Nasi Telur')
        ->and($text)->not->toContain('Transfer')
        ->and($text)->not->toContain('Transfer bank');
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

it('reports per status counts and the participant total in the class summary', function () {
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $member = CateringMember::factory()->create(['school_class_id' => $schoolClass->id]);
    saveSeptemberAttendance($member, ikut: 5, sakit: 2, izin: 1, alfa: 3, libur: 4);

    [$start, $end] = invoicePeriod();
    $invoice = invoiceService()->buildClassInvoice($schoolClass, $start, $end);
    $row = $invoice['rows'][0];

    expect($row['ikut'])->toBe(5)
        ->and($row['sakit'])->toBe(2)
        ->and($row['izin'])->toBe(1)
        ->and($row['alfa'])->toBe(3)
        ->and($row['libur'])->toBe(4)
        ->and($row['total'])->toBe(5 * $row['pricePerDay'])
        ->and($invoice['totalIkut'])->toBe(5)
        ->and($invoice['totalSakit'])->toBe(2)
        ->and($invoice['totalIzin'])->toBe(1)
        ->and($invoice['totalAlfa'])->toBe(3)
        ->and($invoice['totalLibur'])->toBe(4);

    $text = invoicePdfText(invoiceService()->renderClassPdf($invoice));

    expect($text)->toContain('NAMA SISWA')
        ->and($text)->toContain('HARGA / IKUT')
        ->and($text)->toContain('TOTAL')
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

    expect(count($invoice['attendanceRows']))->toBe(31);

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

it('keeps every attendance date on the page for a 31 day month', function () {
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

    for ($day = 1; $day <= 31; $day++) {
        expect($text)->toContain(sprintf('%02d/01/2026', $day));
    }
});

it('still shows every status and billing indication on one page', function () {
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

    expect($text)->toContain('IKUT')
        ->and($text)->toContain('SAKIT')
        ->and($text)->toContain('IZIN')
        ->and($text)->toContain('ALFA')
        ->and($text)->toContain('LIBUR')
        ->and($text)->toContain('Dihitung')
        ->and($text)->toContain('Tidak dihitung')
        ->and($text)->toContain('RINCIAN ABSENSI')
        ->and($text)->toContain('REKAP ABSENSI')
        ->and($text)->toContain('TOTAL TAGIHAN')
        ->and($text)->toContain('Rp 300.000');
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

    expect($invoice['attendanceRows'])->toHaveCount(31)
        ->and($unpaid)->toHaveCount(11)
        ->and($invoice['total'])->toBe(300000)
        ->and($invoice['totalFormatted'])->toBe('Rp 300.000');

    foreach ($unpaid as $row) {
        expect($row['subtotal'])->toBe(0)
            ->and($row['subtotalFormatted'])->toBe('Rp 0')
            ->and($row['note'])->toBe('Tidak dihitung');
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

        expect(invoicePdfPageCount($pdf))->toBe(1)
            ->and(invoicePdfText($pdf))->toContain('TOTAL TAGIHAN');
    }
});
