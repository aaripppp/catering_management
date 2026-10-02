<?php

namespace App\Services;

use App\Models\CateringCategory;
use App\Models\SchoolClass;
use DateTimeInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

class CateringMemberImportSpreadsheet
{
    /** @var array<int, string> */
    public const HEADERS = [
        'Nama',
        'Kelas',
        'Kategori',
        'Jenis Kelamin',
        'Nama Wali',
        'No HP Wali',
        'No HP',
        'Catatan',
    ];

    /**
     * @return array<int, array<string, int|string>>
     */
    public function read(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($sheet->getName() !== 'DATA PESERTA') {
                    continue;
                }

                $rows = [];
                $rowNumber = 0;

                foreach ($sheet->getRowIterator() as $row) {
                    $rowNumber++;
                    $values = array_map($this->stringValue(...), $row->toArray());

                    if ($rowNumber === 1) {
                        if ($values !== self::HEADERS) {
                            throw new RuntimeException('Header file tidak sesuai template Import Peserta Catering.');
                        }

                        continue;
                    }

                    $values = array_pad(array_slice($values, 0, count(self::HEADERS)), count(self::HEADERS), '');

                    if (collect($values)->every(fn (string $value): bool => $value === '')) {
                        continue;
                    }

                    $rows[] = [
                        'row_number' => $rowNumber,
                        'name' => $values[0],
                        'class_name' => $values[1],
                        'category_name' => $values[2],
                        'gender' => strtoupper($values[3]),
                        'guardian_name' => $values[4],
                        'guardian_phone' => $values[5],
                        'phone' => $values[6],
                        'notes' => $values[7],
                    ];
                }

                return $rows;
            }
        } finally {
            $reader->close();
        }

        throw new RuntimeException('Workbook tidak memiliki sheet DATA PESERTA.');
    }

    public function createTemplate(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'catering-member-import-');

        if ($path === false) {
            throw new RuntimeException('Gagal menyiapkan file template import.');
        }

        $writer = new Writer;
        $writer->openToFile($path);

        try {
            $writer->getCurrentSheet()->setName('DATA PESERTA');
            $writer->addRow(Row::fromValues(self::HEADERS));

            $classSheet = $writer->addNewSheetAndMakeItCurrent();
            $classSheet->setName('REFERENSI KELAS');
            $writer->addRow(Row::fromValues(['Jenjang', 'Tingkat', 'Nama Kelas']));

            SchoolClass::query()
                ->select(['id', 'name', 'level'])
                ->orderedForSelection()
                ->get()
                ->each(function (SchoolClass $schoolClass) use ($writer): void {
                    $writer->addRow(Row::fromValues([
                        $schoolClass->jenjang ?? '-',
                        $schoolClass->level ?? '-',
                        $schoolClass->name,
                    ]));
                });

            $categorySheet = $writer->addNewSheetAndMakeItCurrent();
            $categorySheet->setName('REFERENSI KATEGORI');
            $writer->addRow(Row::fromValues(['Nama Kategori', 'Kelompok', 'Harga / Hari', 'Status']));

            CateringCategory::query()
                ->select(['id', 'name', 'participant_group', 'price_per_day', 'is_active'])
                ->orderBy('name')
                ->get()
                ->each(function (CateringCategory $category) use ($writer): void {
                    $writer->addRow(Row::fromValues([
                        $category->name,
                        $category->participant_group->label(),
                        $category->formatted_price_per_day,
                        $category->is_active ? 'Aktif' : 'Nonaktif',
                    ]));
                });

            $guideSheet = $writer->addNewSheetAndMakeItCurrent();
            $guideSheet->setName('PANDUAN');
            $writer->addRow(Row::fromValues(['PANDUAN IMPORT PESERTA CATERING']));
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues(['WAJIB', 'Nama']));
            $writer->addRow(Row::fromValues(['KONDISIONAL', 'Kelas wajib untuk kategori kelompok Siswa dan harus dikosongkan untuk kelompok Pegawai.']));
            $writer->addRow(Row::fromValues(['OPSIONAL', 'Kategori', 'Jenis Kelamin', 'Nama Wali', 'No HP Wali', 'No HP', 'Catatan']));
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues(['Jika Kategori kosong, otomatis menggunakan "Siswa Umum".']));
            $writer->addRow(Row::fromValues(['Kelas wajib untuk kategori kelompok Siswa dan boleh kosong untuk kategori kelompok Pegawai.']));
            $writer->addRow(Row::fromValues(['Nama kelas siswa harus cocok dengan master Kelas pada sheet REFERENSI KELAS.']));
            $writer->addRow(Row::fromValues(['Kategori harus cocok dengan master Kategori Harga pada sheet REFERENSI KATEGORI.']));
            $writer->addRow(Row::fromValues(['Jangan membuat kelas atau kategori melalui file import.']));
            $writer->addRow(Row::fromValues(['Gunakan L atau P untuk Jenis Kelamin.']));
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues(['CONTOH SISWA', 'Ahmad Fauzan', '7A', 'Siswa Umum']));
            $writer->addRow(Row::fromValues(['CONTOH PEGAWAI', 'Ustadz Hasan', '[kosong]', 'Guru']));
        } finally {
            $writer->close();
        }

        return $path;
    }

    private function stringValue(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (! is_scalar($value)) {
            return '';
        }

        $value = trim((string) $value);

        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }
}
