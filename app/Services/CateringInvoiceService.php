<?php

namespace App\Services;

use App\Enums\CateringAttendanceStatus;
use App\Enums\CateringParticipantGroup;
use App\Models\CateringAttendance;
use App\Models\CateringMember;
use App\Models\SchoolClass;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use ZipArchive;

class CateringInvoiceService
{
    private ?string $logoDataUri = null;

    /**
     * Only the "ikut" status contributes to the billed catering quantity.
     */
    private const BILLABLE_STATUS = CateringAttendanceStatus::Ikut;

    private const LOGO_MAX_WIDTH = 160;

    /**
     * @var array<int, string>
     */
    private const MONTH_NAMES = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    /**
     * Build the invoice payload for a single member from saved attendance.
     *
     * @return array<string, mixed>
     */
    public function buildMemberInvoice(
        CateringMember $member,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): array {
        $rowsByMember = $this->attendanceRowsForMembers([$member->id], $start, $end);

        return $this->composeInvoice($member, $rowsByMember[$member->id] ?? [], $start, $end);
    }

    /**
     * Build the class summary invoice payload for every active student of a class.
     *
     * Participants and their attendance are each loaded in a single query so the
     * per-row price and totals never trigger an N+1 pattern.
     *
     * @return array<string, mixed>
     */
    public function buildClassInvoice(
        SchoolClass $schoolClass,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): array {
        $members = CateringMember::query()
            ->select(['id', 'name', 'school_class_id', 'catering_category_id'])
            ->with(['cateringCategory:id,price_per_day,participant_group'])
            ->active()
            ->participantGroup(CateringParticipantGroup::Student)
            ->where('school_class_id', $schoolClass->id)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $rowsByMember = $members->isEmpty()
            ? []
            : $this->attendanceRowsForMembers($members->pluck('id')->all(), $start, $end);

        $rows = [];
        $grandTotal = 0;
        $totals = array_fill_keys(array_map(
            fn (CateringAttendanceStatus $status): string => $status->value,
            CateringAttendanceStatus::cases(),
        ), 0);
        $savedDays = 0;
        $invoiceNumber = $this->classInvoiceNumber($schoolClass, $start);

        foreach ($members as $member) {
            $memberRows = $rowsByMember[$member->id] ?? [];
            $counts = $this->statusCounts($this->invoiceVisibleRows($memberRows));
            $pricePerDay = (int) ($member->cateringCategory?->price_per_day ?? 0);
            $total = $counts[self::BILLABLE_STATUS->value] * $pricePerDay;

            $savedDays += count($memberRows);
            $grandTotal += $total;

            foreach ($totals as $statusValue => $count) {
                $totals[$statusValue] += $counts[$statusValue];
            }

            $rows[] = [
                'memberId' => $member->id,
                'memberName' => $member->name,
                'ikut' => $counts[self::BILLABLE_STATUS->value],
                'sakit' => $counts[CateringAttendanceStatus::Sakit->value],
                'izin' => $counts[CateringAttendanceStatus::Izin->value],
                'alfa' => $counts[CateringAttendanceStatus::Alfa->value],
                'tidakIkut' => $counts[CateringAttendanceStatus::TidakIkut->value],
                'ujian' => $counts[CateringAttendanceStatus::Ujian->value],
                'eventUnit' => $counts[CateringAttendanceStatus::EventUnit->value],
                'puasa' => $counts[CateringAttendanceStatus::Puasa->value],
                'libur' => $counts[CateringAttendanceStatus::Libur->value],
                'savedDays' => count($memberRows),
                'pricePerDay' => $pricePerDay,
                'pricePerDayFormatted' => $this->formatRupiah($pricePerDay),
                'total' => $total,
                'totalFormatted' => $this->formatRupiah($total),
            ];
        }

        return [
            'classId' => $schoolClass->id,
            'className' => $schoolClass->name,
            'level' => (string) $schoolClass->level,
            'invoiceNumber' => $invoiceNumber,
            'invoiceDate' => now()->format('d/m/Y'),
            'month' => $start->month,
            'year' => $start->year,
            'periodLabel' => $this->periodLabel($start, $end),
            'periodDescription' => sprintf('Catering %s %d', self::MONTH_NAMES[$start->month], $start->year),
            'rows' => $rows,
            'participants' => count($rows),
            'savedDays' => $savedDays,
            'grandTotal' => $grandTotal,
            'grandTotalFormatted' => $this->formatRupiah($grandTotal),
            'totalIkut' => $totals[self::BILLABLE_STATUS->value],
            'totalSakit' => $totals[CateringAttendanceStatus::Sakit->value],
            'totalIzin' => $totals[CateringAttendanceStatus::Izin->value],
            'totalAlfa' => $totals[CateringAttendanceStatus::Alfa->value],
            'totalTidakIkut' => $totals[CateringAttendanceStatus::TidakIkut->value],
            'totalUjian' => $totals[CateringAttendanceStatus::Ujian->value],
            'totalEventUnit' => $totals[CateringAttendanceStatus::EventUnit->value],
            'totalPuasa' => $totals[CateringAttendanceStatus::Puasa->value],
            'totalLibur' => $totals[CateringAttendanceStatus::Libur->value],
            'payment' => $this->paymentDetails(),
            'confirmationReference' => sprintf(
                '%s - %s %d - SUDAH TRANSFER',
                mb_strtoupper($schoolClass->name),
                mb_strtoupper(self::MONTH_NAMES[$start->month]),
                $start->year,
            ),
            'logoDataUri' => $this->logoDataUri(),
        ];
    }

    /**
     * Build invoice payloads for every member of a scope using a fixed number of queries.
     *
     * @return array<int, array<string, mixed>>
     */
    public function buildBulkInvoices(
        CateringParticipantGroup $group,
        ?SchoolClass $schoolClass,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): array {
        $members = CateringMember::query()
            ->select(['id', 'name', 'school_class_id', 'catering_category_id', 'guardian_name', 'guardian_phone'])
            ->with([
                'cateringCategory:id,price_per_day,participant_group',
                'schoolClass:id,name',
            ])
            ->participantGroup($group)
            ->when(
                $schoolClass !== null,
                fn (Builder $query): Builder => $query->where('school_class_id', $schoolClass->id),
            )
            ->active()
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        if ($members->isEmpty()) {
            return [];
        }

        $rowsByMember = $this->attendanceRowsForMembers(
            $members->pluck('id')->all(),
            $start,
            $end,
        );

        return $members
            ->map(fn (CateringMember $member): array => $this->composeInvoice(
                $member,
                $rowsByMember[$member->id] ?? [],
                $start,
                $end,
            ))
            ->values()
            ->all();
    }

    /**
     * Render a single member invoice to raw PDF bytes.
     *
     * @param  array<string, mixed>  $invoice
     */
    public function renderPdf(array $invoice): string
    {
        return Pdf::loadView('pdf.catering.invoice-member', $invoice)
            ->setPaper('a4', 'portrait')
            ->output();
    }

    /**
     * Render the class summary invoice to raw PDF bytes.
     *
     * @param  array<string, mixed>  $invoice
     */
    public function renderClassPdf(array $invoice): string
    {
        return Pdf::loadView('pdf.catering.invoice-class', $invoice)
            ->setPaper('a4', 'portrait')
            ->output();
    }

    /**
     * Build an inline individual invoice response so the browser PDF viewer can preview it.
     *
     * @param  array<string, mixed>  $invoice
     */
    public function individualPdfPreviewResponse(array $invoice): StreamedResponse
    {
        return $this->inlinePdfResponse(
            $this->renderPdf($invoice),
            $this->individualPdfFilename($invoice),
        );
    }

    /**
     * Build an inline class summary invoice response.
     *
     * @param  array<string, mixed>  $invoice
     */
    public function classPdfPreviewResponse(array $invoice): StreamedResponse
    {
        return $this->inlinePdfResponse(
            $this->renderClassPdf($invoice),
            $this->classPdfFilename($invoice),
        );
    }

    /**
     * Build a ZIP download holding one individual invoice PDF per eligible member.
     *
     * @param  array<int, array<string, mixed>>  $invoices
     */
    public function bulkZipResponse(array $invoices, string $scopeLabel, CarbonImmutable $start): StreamedResponse
    {
        $zipPath = $this->temporaryZipPath();

        try {
            $bytes = $this->buildZipBytes($zipPath, $invoices);
        } finally {
            if (is_file($zipPath)) {
                unlink($zipPath);
            }
        }

        return $this->downloadResponse(
            $bytes,
            $this->bulkZipFilename($scopeLabel, $start),
            'application/zip',
        );
    }

    /**
     * Build a ZIP filename for a class or group scope.
     */
    public function bulkZipFilename(string $scopeLabel, CarbonImmutable $start): string
    {
        return sprintf(
            'Invoice-Catering-%s-%s-%d.zip',
            $this->sanitizeFilenamePart($scopeLabel),
            self::MONTH_NAMES[$start->month],
            $start->year,
        );
    }

    /**
     * Build an individual PDF filename, appending a stable suffix only on collision.
     *
     * @param  array<string, mixed>  $invoice
     * @param  array<int, string>  $usedFilenames
     */
    public function individualPdfFilename(array $invoice, array $usedFilenames = []): string
    {
        $taken = array_map(
            fn (string $filename): string => mb_strtolower($filename),
            $usedFilenames,
        );

        $base = sprintf(
            'Invoice-%s-%s-%s-%d',
            $this->sanitizeFilenamePart((string) $invoice['memberName']),
            $this->sanitizeFilenamePart((string) $invoice['scopeLabel']),
            self::MONTH_NAMES[(int) $invoice['month']],
            (int) $invoice['year'],
        );

        $filename = $base.'.pdf';

        if (in_array(mb_strtolower($filename), $taken, true)) {
            $filename = $base.'-'.$invoice['memberId'].'.pdf';
            $counter = 1;

            while (in_array(mb_strtolower($filename), $taken, true)) {
                $counter++;
                $filename = $base.'-'.$invoice['memberId'].'-'.$counter.'.pdf';
            }
        }

        return $filename;
    }

    /**
     * Build the class summary PDF filename.
     *
     * @param  array<string, mixed>  $invoice
     */
    public function classPdfFilename(array $invoice): string
    {
        return sprintf(
            'Invoice-Catering-Kelas-%s-%s-%d.pdf',
            $this->sanitizeFilenamePart((string) $invoice['className']),
            self::MONTH_NAMES[(int) $invoice['month']],
            (int) $invoice['year'],
        );
    }

    /**
     * Indonesian Rupiah formatting used across invoice totals.
     */
    public function formatRupiah(int $amount): string
    {
        return 'Rp '.Number::format($amount, 0, null, 'id');
    }

    /**
     * Indonesian month name for a given month number.
     */
    public function monthName(int $month): string
    {
        return self::MONTH_NAMES[$month] ?? (string) $month;
    }

    /**
     * Load saved attendance rows for many members in one bounded query.
     *
     * @param  array<int, int>  $memberIds
     * @return array<int, array<int, array{date: string, dateIso: string, status: CateringAttendanceStatus}>>
     */
    private function attendanceRowsForMembers(
        array $memberIds,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): array {
        /** @var array<int, array<int, array{date: string, dateIso: string, status: CateringAttendanceStatus}>> $grouped */
        $grouped = [];

        CateringAttendance::query()
            ->select(['catering_member_id', 'attendance_date', 'status'])
            ->whereIn('catering_member_id', $memberIds)
            ->where('attendance_date', '>=', $start->toDateString())
            ->where('attendance_date', '<', $end->addDay()->toDateString())
            ->orderBy('attendance_date')
            ->orderBy('id')
            ->get()
            ->each(function (CateringAttendance $attendance) use (&$grouped): void {
                $date = CarbonImmutable::parse($attendance->attendance_date);

                $grouped[$attendance->catering_member_id][] = [
                    'date' => $date->format('d/m/Y'),
                    'dateIso' => $date->toDateString(),
                    'status' => $attendance->status,
                ];
            });

        return $grouped;
    }

    /**
     * Count saved attendance rows per status for a single member.
     *
     * @param  array<int, array{date: string, dateIso: string, status: CateringAttendanceStatus}>  $rows
     * @return array<string, int>
     */
    private function statusCounts(array $rows): array
    {
        $counts = array_fill_keys(array_map(
            fn (CateringAttendanceStatus $status): string => $status->value,
            CateringAttendanceStatus::cases(),
        ), 0);

        foreach ($rows as $row) {
            $counts[$row['status']->value]++;
        }

        return $counts;
    }

    /**
     * Assemble the invoice payload shared by individual and bulk rendering.
     *
     * `savedDays` keeps counting every persisted row of the period, while the
     * printed detail and the status recap only cover the days the invoice
     * actually reports.
     *
     * @param  array<int, array{date: string, dateIso: string, status: CateringAttendanceStatus}>  $rows
     * @return array<string, mixed>
     */
    private function composeInvoice(
        CateringMember $member,
        array $rows,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): array {
        $category = $member->relationLoaded('cateringCategory')
            ? $member->cateringCategory
            : $member->cateringCategory()->first();

        $pricePerDay = (int) ($category?->price_per_day ?? 0);
        $visibleRows = $this->invoiceVisibleRows($rows);
        $counts = $this->statusCounts($visibleRows);
        $quantity = $counts[self::BILLABLE_STATUS->value];
        $group = $category?->participant_group ?? CateringParticipantGroup::Student;
        $usesClass = $group->requiresClassSelection();
        $className = $usesClass ? ($member->schoolClass?->name ?? '-') : $group->label();
        $isEmployee = $group === CateringParticipantGroup::Employee;
        $total = $quantity * $pricePerDay;
        $invoiceNumber = sprintf('INV/CAF/%02d/%d/%d', $start->month, $start->year, $member->id);

        return [
            'memberId' => $member->id,
            'memberName' => $member->name,
            'guardianName' => $member->guardian_name,
            'guardianPhone' => $member->guardian_phone,
            'invoiceNumber' => $invoiceNumber,
            'invoiceDate' => now()->format('d/m/Y'),
            'month' => $start->month,
            'year' => $start->year,
            'periodLabel' => $this->periodLabel($start, $end),
            'periodDescription' => sprintf(
                'Catering %s %d',
                self::MONTH_NAMES[$start->month],
                $start->year,
            ),
            'identityLabel' => $usesClass ? 'Kelas' : 'Kelompok',
            'identityValue' => $className,
            'scopeLabel' => $className,
            'contextLabel' => $isEmployee ? 'CATERING PEGAWAI' : 'CATERING SISWA',
            'titleLabel' => $isEmployee ? 'INVOICE CATERING PEGAWAI' : 'INVOICE CATERING SISWA',
            'participantGroupLabel' => $group->label(),
            'quantity' => $quantity,
            'savedDays' => count($rows),
            'pricePerDay' => $pricePerDay,
            'pricePerDayFormatted' => $this->formatRupiah($pricePerDay),
            'total' => $total,
            'totalFormatted' => $this->formatRupiah($total),
            'attendanceRows' => $this->detailRows($visibleRows, $pricePerDay),
            'countIkut' => $counts[self::BILLABLE_STATUS->value],
            'countSakit' => $counts[CateringAttendanceStatus::Sakit->value],
            'countIzin' => $counts[CateringAttendanceStatus::Izin->value],
            'countAlfa' => $counts[CateringAttendanceStatus::Alfa->value],
            'countTidakIkut' => $counts[CateringAttendanceStatus::TidakIkut->value],
            'countUjian' => $counts[CateringAttendanceStatus::Ujian->value],
            'countEventUnit' => $counts[CateringAttendanceStatus::EventUnit->value],
            'countPuasa' => $counts[CateringAttendanceStatus::Puasa->value],
            'countLibur' => $counts[CateringAttendanceStatus::Libur->value],
            'payment' => $this->paymentDetails(),
            'confirmationReference' => sprintf(
                '%s - %s - SUDAH TRANSFER',
                mb_strtoupper($member->name),
                mb_strtoupper($className),
            ),
            'logoDataUri' => $this->logoDataUri(),
        ];
    }

    /**
     * Official bank and payment-confirmation details shared by every invoice.
     *
     * @return array{bank: string, account_number: string, account_name: string, admin_whatsapp: string, admin_whatsapp_display: string}
     */
    private function paymentDetails(): array
    {
        return config('catering.payment');
    }

    /**
     * Keep only the attendance days an invoice is allowed to present.
     *
     * Libur remains stored as attendance history but is not relevant to invoice
     * presentation. Every other status remains visible regardless of weekday.
     *
     * @param  array<int, array{date: string, dateIso: string, status: CateringAttendanceStatus}>  $rows
     * @return array<int, array{date: string, dateIso: string, status: CateringAttendanceStatus}>
     */
    private function invoiceVisibleRows(array $rows): array
    {
        return array_values(array_filter(
            $rows,
            fn (array $row): bool => $row['status'] !== CateringAttendanceStatus::Libur,
        ));
    }

    /**
     * Turn saved attendance rows into printable detail rows where only "ikut" is billed.
     *
     * @param  array<int, array{date: string, dateIso: string, status: CateringAttendanceStatus}>  $rows
     * @return array<int, array<string, string|int|bool>>
     */
    private function detailRows(array $rows, int $pricePerDay): array
    {
        $detail = [];
        $number = 0;

        foreach ($rows as $row) {
            $isBillable = $row['status'] === self::BILLABLE_STATUS;
            $subtotal = $isBillable ? $pricePerDay : 0;
            $number++;

            $detail[] = [
                'number' => $number,
                'date' => $row['date'],
                'status' => $row['status']->label(),
                'isBillable' => $isBillable,
                'pricePerDayFormatted' => $isBillable ? $this->formatRupiah($pricePerDay) : '-',
                'subtotal' => $subtotal,
                'subtotalFormatted' => $this->formatRupiah($subtotal),
            ];
        }

        return $detail;
    }

    /**
     * Human readable period label for a selected month.
     */
    private function periodLabel(CarbonImmutable $start, CarbonImmutable $end): string
    {
        return sprintf(
            '%02d - %02d %s %d',
            $start->day,
            $end->day,
            self::MONTH_NAMES[$start->month],
            $start->year,
        );
    }

    /**
     * Deterministic class invoice number derived from the period and class id.
     */
    private function classInvoiceNumber(SchoolClass $schoolClass, CarbonImmutable $start): string
    {
        return sprintf('INV/CAF/%02d/%d/CLASS-%d', $start->month, $start->year, $schoolClass->id);
    }

    /**
     * Embed the school logo so DomPDF never needs filesystem or remote access.
     */
    private function logoDataUri(): ?string
    {
        if ($this->logoDataUri !== null) {
            return $this->logoDataUri ?: null;
        }

        $path = public_path('images/annur_logo2.png');

        if (! is_file($path) || ! is_readable($path)) {
            $this->logoDataUri = '';

            return null;
        }

        $this->logoDataUri = 'data:image/png;base64,'.base64_encode(
            $this->downscaledLogo($path, self::LOGO_MAX_WIDTH),
        );

        return $this->logoDataUri;
    }

    /**
     * Shrink the logo to its printed size so each invoice PDF stays small enough
     * to be bundled in a bulk ZIP.
     */
    private function downscaledLogo(string $path, int $maxWidth): string
    {
        $original = file_get_contents($path);

        if ($original === false) {
            return '';
        }

        $size = @getimagesize($path);

        if ($size === false || $size[0] <= $maxWidth) {
            return $original;
        }

        $source = @imagecreatefromstring($original);

        if ($source === false) {
            return $original;
        }

        try {
            $height = (int) round($size[1] * ($maxWidth / $size[0]));
            $resized = imagecreatetruecolor($maxWidth, max(1, $height));

            if ($resized === false) {
                return $original;
            }

            try {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                imagecopyresampled($resized, $source, 0, 0, 0, 0, $maxWidth, max(1, $height), $size[0], $size[1]);

                ob_start();
                imagepng($resized, null, 6);
                $encoded = ob_get_clean();

                return is_string($encoded) && $encoded !== '' ? $encoded : $original;
            } finally {
                imagedestroy($resized);
            }
        } finally {
            imagedestroy($source);
        }
    }

    /**
     * Replace filesystem-unsafe characters while keeping readable letters and digits.
     */
    private function sanitizeFilenamePart(string $value): string
    {
        $clean = preg_replace('/[^\p{L}\p{N}]+/u', '-', $value) ?? '';
        $clean = trim($clean, '-');

        return $clean !== '' ? substr($clean, 0, 60) : 'TanpaNama';
    }

    /**
     * Write every invoice PDF into one archive and return the raw ZIP bytes.
     *
     * @param  array<int, array<string, mixed>>  $invoices
     */
    private function buildZipBytes(string $zipPath, array $invoices): string
    {
        if ($invoices === []) {
            throw new RuntimeException('Arsip ZIP invoice tidak dapat dibuat karena tidak ada invoice yang dihasilkan.');
        }

        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Arsip ZIP invoice tidak dapat dibuat.');
        }

        $usedFilenames = [];

        try {
            foreach ($invoices as $invoice) {
                $filename = $this->individualPdfFilename($invoice, $usedFilenames);
                $usedFilenames[] = $filename;

                if ($zip->addFromString($filename, $this->renderPdf($invoice)) !== true) {
                    throw new RuntimeException('PDF '.$filename.' gagal ditambahkan ke arsip ZIP.');
                }
            }

            if ($zip->close() !== true || ! is_file($zipPath)) {
                throw new RuntimeException('Arsip ZIP invoice gagal ditulis ke disk.');
            }
        } catch (Throwable $exception) {
            $zip->close();

            throw $exception;
        }

        $bytes = file_get_contents($zipPath);

        if ($bytes === false) {
            throw new RuntimeException('Arsip ZIP invoice tidak dapat dibaca.');
        }

        return $bytes;
    }

    /**
     * A unique temp path outside public storage for the generated archive.
     */
    private function temporaryZipPath(): string
    {
        return sprintf(
            '%s/catering-invoice-%s.zip',
            sys_get_temp_dir(),
            bin2hex(random_bytes(8)),
        );
    }

    /**
     * Build a streamed attachment response with a safe filename.
     */
    private function downloadResponse(string $bytes, string $filename, string $contentType): StreamedResponse
    {
        return response()->streamDownload(
            function () use ($bytes): void {
                echo $bytes;
            },
            $filename,
            [
                'Content-Type' => $contentType,
                'Content-Length' => (string) strlen($bytes),
            ],
        );
    }

    /**
     * Build an inline PDF response so the browser renders its native PDF viewer.
     */
    private function inlinePdfResponse(string $bytes, string $filename): StreamedResponse
    {
        return response()->stream(
            fn () => print $bytes,
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
                'Content-Length' => (string) strlen($bytes),
                'Cache-Control' => 'private, max-age=0, must-revalidate',
            ],
        );
    }
}
