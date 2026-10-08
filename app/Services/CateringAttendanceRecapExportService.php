<?php

namespace App\Services;

use App\Enums\CateringAttendanceStatus;
use Barryvdh\DomPDF\Facade\Pdf;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CateringAttendanceRecapExportService
{
    public function __construct(private CateringAttendanceRecapService $recapService) {}

    /**
     * @param  array{month: int, year: int, participantGroup: string, jenjang: string, schoolClassId: string, search: string}  $filters
     */
    public function createExcel(array $filters): string
    {
        $path = tempnam(sys_get_temp_dir(), 'catering-attendance-recap-');

        if ($path === false) {
            throw new RuntimeException('Gagal menyiapkan file rekap absensi.');
        }

        $summary = $this->recapService->summary($filters);
        $participants = $this->recapService->participantRows($filters);
        $groups = $this->recapService->groupRows($filters);
        $writer = new Writer;
        $writer->openToFile($path);

        try {
            $writer->getCurrentSheet()->setName('Rekap Per Peserta');
            $writer->addRow(Row::fromValues($this->participantHeaders()));

            foreach ($participants as $index => $participant) {
                $writer->addRow(Row::fromValues([
                    $index + 1,
                    $participant['member_name'],
                    $participant['group_label'],
                    ...$this->statusValues($participant),
                    $participant['total_days'],
                ]));
            }

            $groupSheet = $writer->addNewSheetAndMakeItCurrent();
            $groupSheet->setName('Per Kelas-Kelompok');
            $writer->addRow(Row::fromValues($this->groupHeaders()));

            foreach ($groups as $index => $group) {
                $writer->addRow(Row::fromValues([
                    $index + 1,
                    $group['label'],
                    $group['participant_count'],
                    ...$this->statusValues($group),
                ]));
            }

            $summarySheet = $writer->addNewSheetAndMakeItCurrent();
            $summarySheet->setName('Ringkasan');
            $writer->addRow(Row::fromValues(['Rekap Absensi Catering']));
            $writer->addRow(Row::fromValues(['Periode', $this->recapService->periodLabel($filters['month'], $filters['year'])]));
            $writer->addRow(Row::fromValues(['Filter', $this->recapService->filterLabel($filters)]));
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues(['Keterangan', 'Jumlah']));
            $writer->addRow(Row::fromValues(['Total Peserta', $summary['participants']]));
            $writer->addRow(Row::fromValues(['Total Record Absensi', $summary['total_records']]));

            foreach (CateringAttendanceStatus::cases() as $status) {
                $writer->addRow(Row::fromValues([$status->label(), $summary[$status->value]]));
            }
        } finally {
            $writer->close();
        }

        return $path;
    }

    /**
     * @param  array{month: int, year: int, participantGroup: string, jenjang: string, schoolClassId: string, search: string}  $filters
     */
    public function renderPdf(array $filters): string
    {
        return Pdf::loadView('pdf.catering.attendance-recap', [
            'periodLabel' => $this->recapService->periodLabel($filters['month'], $filters['year']),
            'filterLabel' => $this->recapService->filterLabel($filters),
            'summary' => $this->recapService->summary($filters),
            'participants' => $this->recapService->participantRows($filters),
            'groups' => $this->recapService->groupRows($filters),
            'statuses' => CateringAttendanceStatus::cases(),
        ])->setPaper('a4', 'landscape')->output();
    }

    /**
     * @param  array{month: int, year: int, participantGroup: string, jenjang: string, schoolClassId: string, search: string}  $filters
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
     * @param  array{month: int, year: int, participantGroup: string, jenjang: string, schoolClassId: string, search: string}  $filters
     */
    public function filename(array $filters, string $extension): string
    {
        $period = str_replace(' ', '-', mb_strtolower($this->recapService->periodLabel($filters['month'], $filters['year'])));

        return 'rekap-absensi-catering-'.$period.'.'.$extension;
    }

    /** @return array<int, string> */
    private function participantHeaders(): array
    {
        return ['No', 'Nama', 'Kelas / Kelompok', ...$this->statusLabels(), 'Total Hari'];
    }

    /** @return array<int, string> */
    private function groupHeaders(): array
    {
        return ['No', 'Kelas / Kelompok', 'Jumlah Peserta', ...$this->statusLabels()];
    }

    /** @return array<int, string> */
    private function statusLabels(): array
    {
        return array_map(fn (CateringAttendanceStatus $status): string => $status->label(), CateringAttendanceStatus::cases());
    }

    /**
     * @param  array<string, int|string|bool|null>  $row
     * @return array<int, int>
     */
    private function statusValues(array $row): array
    {
        return array_map(fn (CateringAttendanceStatus $status): int => (int) $row[$status->value], CateringAttendanceStatus::cases());
    }
}
