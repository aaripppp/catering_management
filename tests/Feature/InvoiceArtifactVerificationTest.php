<?php

use App\Enums\CateringAttendanceStatus;
use App\Enums\CateringParticipantGroup;
use App\Models\CateringAttendance;
use App\Models\CateringCategory;
use App\Models\CateringMember;
use App\Models\SchoolClass;
use App\Services\CateringInvoiceService;
use Carbon\CarbonImmutable;

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
