<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CateringMonthlyReportExportService
{
    public function __construct(private CateringMonthlyReportService $reportService) {}

    /**
     * @param  array{month: int, year: int, participantGroup: string, jenjang: string, schoolClassId: string}  $filters
     */
    public function createExcel(array $filters): string
    {
        $path = tempnam(sys_get_temp_dir(), 'catering-monthly-report-');

        if ($path === false) {
            throw new RuntimeException('Gagal menyiapkan file laporan bulanan.');
        }

        $summary = $this->reportService->summary($filters);
        $groups = $this->reportService->groupRows($filters);
        $details = $this->reportService->detailRows($filters);
        $writer = new Writer;
        $writer->openToFile($path);

        try {
            $writer->getCurrentSheet()->setName('Ringkasan');
            $writer->addRow(Row::fromValues(['Laporan Bulanan Catering']));
            $writer->addRow(Row::fromValues(['Periode', $this->reportService->periodLabel($filters['month'], $filters['year'])]));
            $writer->addRow(Row::fromValues(['Filter', $this->reportService->filterLabel($filters)]));
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues(['Keterangan', 'Jumlah']));

            foreach ($this->summaryRows($summary) as $row) {
                $writer->addRow(Row::fromValues($row));
            }

            $groupSheet = $writer->addNewSheetAndMakeItCurrent();
            $groupSheet->setName('Rekap Per Kelas-Kelompok');
            $writer->addRow(Row::fromValues($this->groupHeaders()));

            foreach ($groups as $index => $group) {
                $writer->addRow(Row::fromValues([
                    $index + 1,
                    $group['label'],
                    $group['participant_count'],
                    $group['gross_amount'],
                    $group['money_in'],
                    $group['credit_used'],
                    $group['outstanding_amount'],
                    $group['overpayment_amount'],
                    $group['paid_count'],
                    $group['partial_count'],
                    $group['unpaid_count'],
                ]));
            }

            $detailSheet = $writer->addNewSheetAndMakeItCurrent();
            $detailSheet->setName('Detail Pembayaran');
            $writer->addRow(Row::fromValues($this->detailHeaders()));

            foreach ($details as $index => $detail) {
                $writer->addRow(Row::fromValues([
                    $index + 1,
                    $detail['member_name'],
                    $detail['group_label'],
                    $detail['category_name'],
                    $detail['gross_amount'],
                    $detail['paid_amount'],
                    $detail['credit_applied_amount'],
                    $detail['generated_credit_amount'],
                    $detail['outstanding_amount'],
                    $detail['payment_status_label'],
                ]));
            }
        } finally {
            $writer->close();
        }

        return $path;
    }

    /**
     * @param  array{month: int, year: int, participantGroup: string, jenjang: string, schoolClassId: string}  $filters
     */
    public function renderPdf(array $filters): string
    {
        return Pdf::loadView('pdf.catering.monthly-report', [
            'periodLabel' => $this->reportService->periodLabel($filters['month'], $filters['year']),
            'filterLabel' => $this->reportService->filterLabel($filters),
            'summary' => $this->reportService->summary($filters),
            'groups' => $this->reportService->groupRows($filters),
            'details' => $this->reportService->detailRows($filters),
        ])->setPaper('a4', 'landscape')->output();
    }

    /**
     * @param  array{month: int, year: int, participantGroup: string, jenjang: string, schoolClassId: string}  $filters
     */
    public function pdfPreviewResponse(array $filters): StreamedResponse
    {
        $bytes = $this->renderPdf($filters);

        return response()->stream(
            fn () => print $bytes,
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$this->filename($filters, 'pdf').'"',
                'Content-Length' => (string) strlen($bytes),
                'Cache-Control' => 'private, max-age=0, must-revalidate',
            ],
        );
    }

    /**
     * @param  array{month: int, year: int, participantGroup: string, jenjang: string, schoolClassId: string}  $filters
     */
    public function filename(array $filters, string $extension): string
    {
        $period = str_replace(' ', '-', mb_strtolower($this->reportService->periodLabel($filters['month'], $filters['year'])));

        return 'laporan-bulanan-catering-'.$period.'.'.$extension;
    }

    /**
     * @param  array<string, int>  $summary
     * @return array<int, array{string, int}>
     */
    private function summaryRows(array $summary): array
    {
        return [
            ['Total Tagihan', $summary['gross_amount']],
            ['Jumlah Uang Masuk', $summary['money_in']],
            ['Kredit Digunakan', $summary['credit_used']],
            ['Total Tunggakan', $summary['outstanding_amount']],
            ['Total Lebih Bayar', $summary['overpayment_amount']],
            ['Jumlah Lunas', $summary['paid_count']],
            ['Jumlah Sebagian', $summary['partial_count']],
            ['Jumlah Belum Bayar', $summary['unpaid_count']],
        ];
    }

    /** @return array<int, string> */
    private function groupHeaders(): array
    {
        return ['No', 'Kelas / Kelompok', 'Peserta', 'Total Tagihan', 'Uang Masuk', 'Kredit Digunakan', 'Tunggakan', 'Lebih Bayar', 'Lunas', 'Sebagian', 'Belum Bayar'];
    }

    /** @return array<int, string> */
    private function detailHeaders(): array
    {
        return ['No', 'Peserta', 'Kelas / Kelompok', 'Kategori', 'Tagihan', 'Terbayar', 'Kredit Dipakai', 'Lebih Bayar', 'Sisa', 'Status'];
    }
}
