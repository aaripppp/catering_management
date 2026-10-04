<?php

use App\Enums\CateringAttendanceStatus;
use App\Enums\CateringParticipantGroup;
use App\Models\CateringAttendance;
use App\Models\CateringCategory;
use App\Models\CateringMember;
use App\Models\SchoolClass;
use App\Services\CateringAttendanceInitializerService;
use App\Services\CateringInvoiceService;
use Carbon\CarbonImmutable;

function artifactPdfText(string $bytes): string
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

it('produces real pdf and zip artifacts for review', function () {
    $outputDir = sys_get_temp_dir().'/cattering-invoice-verification';

    if (! is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }

    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $category = CateringCategory::factory()->create(['name' => 'Siswa Umum', 'price_per_day' => 15000]);
    $members = CateringMember::factory()->count(3)->create([
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);

    foreach ($members as $member) {
        for ($day = 1; $day <= 20; $day++) {
            CateringAttendance::factory()->for($member)->create([
                'attendance_date' => sprintf('2026-09-%02d', $day),
                'status' => $day % 5 === 0 ? CateringAttendanceStatus::Sakit : CateringAttendanceStatus::Ikut,
            ]);
        }
    }

    $start = CarbonImmutable::create(2026, 9, 1)->startOfMonth();
    $service = app(CateringInvoiceService::class);
    $snapshot = fn (): array => CateringAttendance::query()
        ->orderBy('id')
        ->get(['catering_member_id', 'attendance_date', 'status', 'updated_at'])
        ->toArray();

    $before = $snapshot();
    $report = [];

    foreach ($members as $member) {
        $invoice = $service->buildMemberInvoice(
            $member->fresh(['cateringCategory', 'schoolClass']),
            $start,
            $start->endOfMonth(),
        );
        $filename = $service->individualPdfFilename($invoice);
        $bytes = $service->renderPdf($invoice);
        file_put_contents($outputDir.'/'.$filename, $bytes);

        $report[] = sprintf(
            'PDF  %-52s %8d B  ikut=%d/%d  %s x %s = %s',
            $filename,
            strlen($bytes),
            $invoice['quantity'],
            $invoice['savedDays'],
            $invoice['quantity'],
            $invoice['pricePerDayFormatted'],
            $invoice['totalFormatted'],
        );
    }

    $bulk = array_values(array_filter(
        $service->buildBulkInvoices(CateringParticipantGroup::Student, $schoolClass, $start, $start->endOfMonth()),
        fn (array $invoice): bool => $invoice['savedDays'] > 0,
    ));

    $zipResponse = $service->bulkZipResponse($bulk, $schoolClass->name, $start);
    ob_start();
    $zipResponse->sendContent();
    $zipBytes = ob_get_clean();

    $zipName = $service->bulkZipFilename($schoolClass->name, $start);
    file_put_contents($outputDir.'/'.$zipName, $zipBytes);

    $archive = new ZipArchive;
    $archive->open($outputDir.'/'.$zipName);
    $report[] = sprintf('ZIP  %-52s %8d B  entries=%d', $zipName, strlen($zipBytes), $archive->numFiles);

    for ($index = 0; $index < $archive->numFiles; $index++) {
        $report[] = sprintf('   entry %-48s %8d B', $archive->getNameIndex($index), $archive->statIndex($index)['size']);
    }

    $archive->close();
    $report[] = 'attendance rows unchanged: '.($before === $snapshot() ? 'YES' : 'NO');
    $report[] = 'artifacts in: '.$outputDir;

    fwrite(STDERR, "\n".implode("\n", $report)."\n");

    expect($before)->toEqual($snapshot())
        ->and($bulk)->toHaveCount(3);
});

it('fails loudly instead of writing a broken archive when no invoice qualifies', function () {
    $service = app(CateringInvoiceService::class);
    $start = CarbonImmutable::create(2026, 9, 1);

    expect(fn (): mixed => $service->bulkZipResponse([], '7A', $start))
        ->toThrow(RuntimeException::class, 'tidak ada invoice yang dihasilkan');
});

it('writes reviewable direct and zip pdfs using libur-only visibility', function () {
    $outputDir = sys_get_temp_dir().'/cattering-invoice-verification';

    if (! is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }

    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $category = CateringCategory::factory()->create(['name' => 'Siswa Umum', 'price_per_day' => 15000]);
    $members = CateringMember::factory()->count(2)->create([
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);

    // The attendance page initialises the whole month: weekdays "ikut", weekends "libur".
    CateringAttendanceInitializerService::ensureMonth(2026, 10);

    foreach ($members as $index => $member) {
        CateringAttendance::query()
            ->where('catering_member_id', $member->id)
            ->whereDate('attendance_date', '2026-10-09')
            ->update(['status' => CateringAttendanceStatus::Libur->value]);

        CateringAttendance::query()
            ->where('catering_member_id', $member->id)
            ->whereDate('attendance_date', '2026-10-03')
            ->update(['status' => CateringAttendanceStatus::Ujian->value]);

        if ($index === 1) {
            CateringAttendance::query()
                ->where('catering_member_id', $member->id)
                ->whereDate('attendance_date', '2026-10-08')
                ->update(['status' => CateringAttendanceStatus::Sakit->value]);
        }
    }

    $start = CarbonImmutable::create(2026, 10, 1)->startOfMonth();
    $end = $start->endOfMonth();
    $service = app(CateringInvoiceService::class);
    $weekends = array_values(array_filter(
        range(1, $start->daysInMonth),
        fn (int $day): bool => $start->setDay($day)->isWeekend(),
    ));
    $report = [];
    $persisted = CateringAttendance::query()->count();
    $printedWeekends = 0;
    $directUjianRows = 0;
    $directLiburRows = 0;
    $directPaymentSections = 0;
    $directConfirmations = 0;

    foreach ($members as $member) {
        $invoice = $service->buildMemberInvoice($member->fresh(['cateringCategory', 'schoolClass']), $start, $end);
        $filename = $service->individualPdfFilename($invoice);
        $bytes = $service->renderPdf($invoice);
        file_put_contents($outputDir.'/'.$filename, $bytes);
        $text = artifactPdfText($bytes);
        $directUjianRows += str_contains($text, '03/10/2026 Ujian - Rp 0') ? 1 : 0;
        $directLiburRows += str_contains($text, '09/10/2026 Libur') ? 1 : 0;
        $directPaymentSections += str_contains($text, 'PEMBAYARAN')
            && str_contains($text, 'BCA')
            && str_contains($text, '5222129702')
            && str_contains($text, 'Rara Nurfatimah')
            && str_contains($text, '0813-9994-7699') ? 1 : 0;
        $directConfirmations += ! str_contains($invoice['confirmationReference'], 'INV/')
            && str_contains($text, $invoice['confirmationReference']) ? 1 : 0;

        $dates = array_column($invoice['attendanceRows'], 'date');
        $printedWeekends += count(array_filter(
            $weekends,
            fn (int $day): bool => in_array($start->setDay($day)->format('d/m/Y'), $dates, true),
        ));

        $report[] = sprintf(
            'PDF  %-52s %8d B  rows=%d  %s x %s = %s  libur recap=%d  saved rows=%d',
            $filename,
            strlen($bytes),
            count($invoice['attendanceRows']),
            $invoice['quantity'],
            $invoice['pricePerDayFormatted'],
            $invoice['totalFormatted'],
            $invoice['countLibur'],
            $invoice['savedDays'],
        );
    }

    $classInvoice = $service->buildClassInvoice($schoolClass, $start, $end);
    $classFilename = $service->classPdfFilename($classInvoice);
    $classBytes = $service->renderClassPdf($classInvoice);
    file_put_contents($outputDir.'/'.$classFilename, $classBytes);
    $classText = artifactPdfText($classBytes);

    $bulk = $service->buildBulkInvoices(CateringParticipantGroup::Student, $schoolClass, $start, $end);
    $zipResponse = $service->bulkZipResponse($bulk, $schoolClass->name, $start);
    ob_start();
    $zipResponse->sendContent();
    $zipBytes = ob_get_clean();
    $zipName = $service->bulkZipFilename($schoolClass->name, $start);
    file_put_contents($outputDir.'/'.$zipName, $zipBytes);

    $bulkWeekendRows = 0;
    $zipReport = [];

    foreach ($bulk as $bulkInvoice) {
        $bulkWeekendRows += count(array_filter(
            array_column($bulkInvoice['attendanceRows'], 'date'),
            fn (string $date): bool => in_array($date, array_map(
                fn (int $day): string => $start->setDay($day)->format('d/m/Y'),
                $weekends,
            ), true),
        ));
    }

    $archive = new ZipArchive;
    $archive->open($outputDir.'/'.$zipName);
    $zipUjianRows = 0;
    $zipLiburRows = 0;
    $zipPaymentSections = 0;
    $zipConfirmations = 0;

    for ($index = 0; $index < $archive->numFiles; $index++) {
        $entryName = $archive->getNameIndex($index);
        $entryBytes = $archive->getFromIndex($index);
        $entryText = $entryBytes === false ? '' : artifactPdfText($entryBytes);
        $zipInvoice = collect($bulk)->first(
            fn (array $invoice): bool => $service->individualPdfFilename($invoice) === $entryName,
        );
        $zipUjianRows += str_contains($entryText, '03/10/2026 Ujian - Rp 0') ? 1 : 0;
        $zipLiburRows += str_contains($entryText, '09/10/2026 Libur') ? 1 : 0;
        $zipPaymentSections += str_contains($entryText, 'PEMBAYARAN')
            && str_contains($entryText, 'BCA')
            && str_contains($entryText, '5222129702')
            && str_contains($entryText, 'Rara Nurfatimah')
            && str_contains($entryText, '0813-9994-7699') ? 1 : 0;
        $zipConfirmations += $zipInvoice !== null
            && ! str_contains($zipInvoice['confirmationReference'], 'INV/')
            && str_contains($entryText, $zipInvoice['confirmationReference']) ? 1 : 0;
        $zipReport[] = sprintf('   entry %-48s %8d B', $entryName, $archive->statIndex($index)['size']);
    }

    $archive->close();
    $report[] = sprintf('ZIP  %-52s %8d B  entries=%d', $zipName, strlen($zipBytes), count($zipReport));
    $report = array_merge($report, $zipReport);
    $report[] = sprintf(
        'PDF  %-52s %8d B  ikut=%d  libur=%d  %s',
        $classFilename,
        strlen($classBytes),
        $classInvoice['totalIkut'],
        $classInvoice['totalLibur'],
        $classInvoice['grandTotalFormatted'],
    );
    $report[] = 'visible weekend rows in individual pdfs: '.$printedWeekends;
    $report[] = 'october weekend rows inside the class zip: '.$bulkWeekendRows;
    $report[] = 'attendance rows in database: '.CateringAttendance::query()->count().' of '.$persisted;
    $report[] = 'artifacts in: '.$outputDir;

    fwrite(STDERR, "\n".implode("\n", $report)."\n");

    expect($printedWeekends)->toBe(2)
        ->and($bulkWeekendRows)->toBe(2)
        ->and($directUjianRows)->toBe(2)
        ->and($directLiburRows)->toBe(0)
        ->and($directPaymentSections)->toBe(2)
        ->and($directConfirmations)->toBe(2)
        ->and($zipUjianRows)->toBe(2)
        ->and($zipLiburRows)->toBe(0)
        ->and($zipPaymentSections)->toBe(2)
        ->and($zipConfirmations)->toBe(2)
        ->and($classText)->toContain('PEMBAYARAN')
        ->and($classText)->toContain('KONFIRMASI PEMBAYARAN')
        ->and($classText)->toContain('BCA')
        ->and($classText)->toContain('5222129702')
        ->and($classText)->toContain('Rara Nurfatimah')
        ->and($classText)->toContain('0813-9994-7699')
        ->and($classInvoice['confirmationReference'])->not->toContain('INV/')
        ->and($classText)->toContain($classInvoice['confirmationReference'])
        ->and($classText)->toContain('SUDAH TRANSFER')
        ->and(CateringAttendance::query()->count())->toBe($persisted)
        ->and($classInvoice['totalUjian'])->toBe(2)
        ->and($classInvoice['totalLibur'])->toBe(0);
});
