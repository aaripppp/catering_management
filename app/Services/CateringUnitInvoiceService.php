<?php

namespace App\Services;

use App\Enums\CateringUnitBillSyncStatus;
use App\Exceptions\CateringUnitCombinedInvoiceNotReadyException;
use App\Exceptions\CateringUnitInvoiceNotReadyException;
use App\Models\CateringUnitBill;
use App\Models\CateringUnitBillDay;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CateringUnitInvoiceService
{
    /** @var array<int, string> */
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

    private ?string $logoDataUri = null;

    public function __construct(private readonly CateringUnitBillingService $billingService) {}

    /** @return array<string, mixed> */
    public function buildInvoice(CateringUnitBill $bill): array
    {
        if ($this->billingService->syncStatusForBill($bill) !== CateringUnitBillSyncStatus::Synced) {
            throw new CateringUnitInvoiceNotReadyException;
        }

        $bill->load(['days' => fn ($query) => $query->orderBy('attendance_date')]);
        $period = CarbonImmutable::create($bill->period_year, $bill->period_month, 1)->startOfMonth();
        $invoiceDate = $period->addMonth()->startOfMonth();
        $dueDate = $invoiceDate->setDay(28);
        $hasMixedRates = $bill->days
            ->pluck('price_per_person_snapshot')
            ->unique()
            ->count() > 1;

        return [
            'billId' => $bill->id,
            'unitName' => $bill->unit_name,
            'invoiceNumber' => sprintf(
                'INV/CAF/%02d/%d/UNIT-%d',
                $bill->period_month,
                $bill->period_year,
                $bill->id,
            ),
            'invoiceDate' => $invoiceDate->format('d/m/Y'),
            'invoiceDateFormatted' => $this->formatIndonesianDate($invoiceDate),
            'dueDate' => $dueDate->format('d/m/Y'),
            'dueDateFormatted' => $this->formatIndonesianDate($dueDate),
            'month' => $bill->period_month,
            'year' => $bill->period_year,
            'periodLabel' => self::MONTH_NAMES[$bill->period_month].' '.$bill->period_year,
            'pricePerPerson' => $bill->price_per_person_snapshot,
            'priceLabel' => $hasMixedRates
                ? 'Tarif Campuran'
                : ($bill->price_per_person_snapshot === null
                    ? '-'
                    : $this->formatRupiah($bill->price_per_person_snapshot)),
            'totalQuantity' => $bill->total_quantity,
            'grossAmount' => $bill->gross_amount,
            'grossAmountFormatted' => $this->formatRupiah($bill->gross_amount),
            'days' => $bill->days
                ->map(fn (CateringUnitBillDay $day): array => [
                    'date' => $this->formatIndonesianDate(CarbonImmutable::parse($day->attendance_date)),
                    'dateIso' => $day->attendance_date->toDateString(),
                    'quantity' => $day->quantity,
                    'pricePerPerson' => $day->price_per_person_snapshot,
                    'pricePerPersonFormatted' => $this->formatRupiah($day->price_per_person_snapshot),
                    'subtotal' => $day->subtotal,
                    'subtotalFormatted' => $this->formatRupiah($day->subtotal),
                ])
                ->all(),
            'payment' => config('catering.payment'),
            'confirmationReference' => sprintf(
                '%s - %s %d - SUDAH TRANSFER',
                mb_strtoupper($bill->unit_name),
                mb_strtoupper(self::MONTH_NAMES[$bill->period_month]),
                $bill->period_year,
            ),
            'logoDataUri' => $this->logoDataUri(),
        ];
    }

    /** @return array<string, mixed> */
    public function buildCombinedInvoice(int $month, int $year): array
    {
        $statuses = $this->billingService->periodStatus($month, $year);

        if ($statuses->contains(fn (array $row): bool => in_array($row['status'], [
            CateringUnitBillSyncStatus::Stale,
            CateringUnitBillSyncStatus::Unsynced,
        ], true))) {
            throw new CateringUnitCombinedInvoiceNotReadyException;
        }

        $bills = $statuses
            ->filter(fn (array $row): bool => $row['status'] === CateringUnitBillSyncStatus::Synced)
            ->map(fn (array $row): CateringUnitBill => $row['bill'])
            ->values();
        $period = CarbonImmutable::create($year, $month, 1)->startOfMonth();
        $invoiceDate = $period->addMonth()->startOfMonth();
        $dueDate = $invoiceDate->setDay(28);

        return [
            'invoiceNumber' => sprintf('INV/CAF/%02d/%d/UNIT-ALL', $month, $year),
            'invoiceDate' => $invoiceDate->format('d/m/Y'),
            'invoiceDateFormatted' => $this->formatIndonesianDate($invoiceDate),
            'dueDate' => $dueDate->format('d/m/Y'),
            'dueDateFormatted' => $this->formatIndonesianDate($dueDate),
            'month' => $month,
            'year' => $year,
            'periodLabel' => self::MONTH_NAMES[$month].' '.$year,
            'rowCount' => $bills->count(),
            'rows' => $bills
                ->map(fn (CateringUnitBill $bill, int $index): array => [
                    'no' => $index + 1,
                    'unitName' => $bill->unit_name,
                    'totalQuantity' => $bill->total_quantity,
                    'totalQuantityFormatted' => number_format($bill->total_quantity, 0, ',', '.'),
                    'pricePerPerson' => $bill->price_per_person_snapshot,
                    'priceLabel' => $bill->price_per_person_snapshot === null
                        ? 'Tarif Campuran'
                        : $this->formatRupiah($bill->price_per_person_snapshot),
                    'grossAmount' => $bill->gross_amount,
                    'grossAmountFormatted' => $this->formatRupiah($bill->gross_amount),
                ])
                ->all(),
            'grandTotalQuantity' => (int) $bills->sum('total_quantity'),
            'grandTotalQuantityFormatted' => number_format((int) $bills->sum('total_quantity'), 0, ',', '.'),
            'grandTotalAmount' => (int) $bills->sum('gross_amount'),
            'grandTotalAmountFormatted' => $this->formatRupiah((int) $bills->sum('gross_amount')),
            'payment' => config('catering.payment'),
            'logoDataUri' => $this->logoDataUri(),
        ];
    }

    /** @param array<string, mixed> $invoice */
    public function renderCombinedPdf(array $invoice): string
    {
        return Pdf::loadView('pdf.catering.unit-invoice-all', $invoice)
            ->setPaper('a4', 'landscape')
            ->output();
    }

    public function combinedPreviewResponse(int $month, int $year): StreamedResponse
    {
        $invoice = $this->buildCombinedInvoice($month, $year);
        $bytes = $this->renderCombinedPdf($invoice);

        return response()->stream(
            fn () => print $bytes,
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$this->combinedFilename($invoice).'"',
                'Content-Length' => (string) strlen($bytes),
                'Cache-Control' => 'private, max-age=0, must-revalidate',
            ],
        );
    }

    /** @param array<string, mixed> $invoice */
    public function combinedFilename(array $invoice): string
    {
        return sprintf(
            'Invoice-All-Unit-%s-%d.pdf',
            self::MONTH_NAMES[(int) $invoice['month']],
            (int) $invoice['year'],
        );
    }

    /** @param array<string, mixed> $invoice */
    public function renderPdf(array $invoice): string
    {
        return Pdf::loadView('pdf.catering.unit-invoice', $invoice)
            ->setPaper('a4', 'portrait')
            ->output();
    }

    public function previewResponse(CateringUnitBill $bill): StreamedResponse
    {
        $invoice = $this->buildInvoice($bill);
        $bytes = $this->renderPdf($invoice);

        return response()->stream(
            fn () => print $bytes,
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$this->filename($invoice).'"',
                'Content-Length' => (string) strlen($bytes),
                'Cache-Control' => 'private, max-age=0, must-revalidate',
            ],
        );
    }

    /** @param array<string, mixed> $invoice */
    public function filename(array $invoice): string
    {
        return sprintf(
            'Invoice-Unit-%s-%s-%d.pdf',
            $this->sanitizeFilenamePart((string) $invoice['unitName']),
            self::MONTH_NAMES[(int) $invoice['month']],
            (int) $invoice['year'],
        );
    }

    private function formatIndonesianDate(CarbonImmutable $date): string
    {
        return sprintf('%d %s %d', $date->day, self::MONTH_NAMES[$date->month], $date->year);
    }

    private function formatRupiah(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }

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

        $contents = file_get_contents($path);

        if ($contents === false) {
            $this->logoDataUri = '';

            return null;
        }

        return $this->logoDataUri = 'data:image/png;base64,'.base64_encode($contents);
    }

    private function sanitizeFilenamePart(string $value): string
    {
        $clean = preg_replace('/[^\p{L}\p{N}]+/u', '-', $value) ?? '';
        $clean = trim($clean, '-');

        return $clean !== '' ? substr($clean, 0, 60) : 'TanpaNama';
    }
}
