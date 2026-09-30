<?php

namespace App\Services;

use App\Models\CateringCategory;
use App\Models\CateringMember;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CateringMemberImportService
{
    private const DEFAULT_CATEGORY = 'Siswa Umum';

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{rows: array<int, array<string, mixed>>, summary: array{total: int, ready: int, errors: int}, has_errors: bool}
     */
    public function preview(array $rows): array
    {
        $classes = SchoolClass::query()
            ->select(['id', 'name'])
            ->get()
            ->keyBy(fn (SchoolClass $schoolClass): string => $this->normalizeLookup($schoolClass->name));
        $categories = CateringCategory::query()
            ->select(['id', 'name'])
            ->get()
            ->keyBy(fn (CateringCategory $category): string => $this->normalizeLookup($category->name));
        $fileDuplicateCounts = collect($rows)
            ->map(fn (array $row): string => $this->duplicateKey(
                $this->clean($row['name'] ?? ''),
                $this->clean($row['class_name'] ?? ''),
            ))
            ->filter()
            ->countBy();
        $existingMemberKeys = CateringMember::query()
            ->whereNotNull('school_class_id')
            ->get(['name', 'school_class_id'])
            ->mapWithKeys(fn (CateringMember $member): array => [
                $this->normalizeLookup($member->name).'|'.$member->school_class_id => true,
            ]);

        $previewRows = collect($rows)->map(function (array $row) use ($classes, $categories, $fileDuplicateCounts, $existingMemberKeys): array {
            $preparedRow = $this->prepareRow($row);
            $errors = $this->rowErrors($preparedRow);
            $schoolClass = $classes->get($this->normalizeLookup($preparedRow['class_name']));
            $requestedCategory = $preparedRow['category_name'] !== ''
                ? $preparedRow['category_name']
                : self::DEFAULT_CATEGORY;
            $category = $categories->get($this->normalizeLookup($requestedCategory));

            if ($preparedRow['class_name'] !== '' && ! $schoolClass) {
                $errors[] = sprintf('Kelas "%s" tidak ditemukan.', $preparedRow['class_name']);
            }

            if (! $category) {
                $errors[] = $preparedRow['category_name'] === ''
                    ? 'Kategori default "Siswa Umum" tidak ditemukan.'
                    : sprintf('Kategori "%s" tidak ditemukan.', $preparedRow['category_name']);
            }

            $fileDuplicateKey = $this->duplicateKey($preparedRow['name'], $preparedRow['class_name']);

            if ($fileDuplicateKey !== '' && ($fileDuplicateCounts[$fileDuplicateKey] ?? 0) > 1) {
                $errors[] = 'Nama dan kelas yang sama muncul lebih dari sekali dalam file.';
            }

            if ($schoolClass) {
                $databaseDuplicateKey = $this->normalizeLookup($preparedRow['name']).'|'.$schoolClass->id;

                if ($preparedRow['name'] !== '' && $existingMemberKeys->has($databaseDuplicateKey)) {
                    $errors[] = 'Peserta dengan nama dan kelas yang sama sudah terdaftar.';
                }
            }

            $errors = array_values(array_unique($errors));

            return [
                ...$preparedRow,
                'school_class_id' => $schoolClass?->id,
                'resolved_class_name' => $schoolClass?->name ?? $preparedRow['class_name'],
                'catering_category_id' => $category?->id,
                'resolved_category_name' => $category?->name ?? $requestedCategory,
                'status' => $errors === [] ? 'ready' : 'error',
                'status_label' => $errors === [] ? 'Siap Import' : 'Bermasalah: '.implode(' ', $errors),
                'errors' => $errors,
            ];
        })->values();

        $summary = [
            'total' => $previewRows->count(),
            'ready' => $previewRows->where('status', 'ready')->count(),
            'errors' => $previewRows->where('status', 'error')->count(),
        ];

        return [
            'rows' => $previewRows->all(),
            'summary' => $summary,
            'has_errors' => $summary['errors'] > 0,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{total: int, breakdown: array<string, int>}
     */
    public function import(array $rows): array
    {
        return DB::transaction(function () use ($rows): array {
            $preview = $this->preview($rows);

            if ($preview['has_errors']) {
                throw ValidationException::withMessages([
                    'import' => 'Import dibatalkan karena masih ada baris bermasalah.',
                ]);
            }

            $breakdown = [];

            foreach ($preview['rows'] as $row) {
                CateringMember::query()->create([
                    'name' => $row['name'],
                    'school_class_id' => $row['school_class_id'],
                    'catering_category_id' => $row['catering_category_id'],
                    'gender' => $this->nullableString($row['gender']),
                    'guardian_name' => $this->nullableString($row['guardian_name']),
                    'guardian_phone' => $this->nullableString($row['guardian_phone']),
                    'phone' => $this->nullableString($row['phone']),
                    'notes' => $this->nullableString($row['notes']),
                ]);

                $categoryName = $row['resolved_category_name'];
                $breakdown[$categoryName] = ($breakdown[$categoryName] ?? 0) + 1;
            }

            ksort($breakdown);

            return [
                'total' => count($preview['rows']),
                'breakdown' => $breakdown,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, int|string>
     */
    private function prepareRow(array $row): array
    {
        return [
            'row_number' => (int) ($row['row_number'] ?? 0),
            'name' => $this->clean($row['name'] ?? ''),
            'class_name' => $this->clean($row['class_name'] ?? ''),
            'category_name' => $this->clean($row['category_name'] ?? ''),
            'gender' => strtoupper($this->clean($row['gender'] ?? '')),
            'guardian_name' => $this->clean($row['guardian_name'] ?? ''),
            'guardian_phone' => $this->clean($row['guardian_phone'] ?? ''),
            'phone' => $this->clean($row['phone'] ?? ''),
            'notes' => $this->clean($row['notes'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, string>
     */
    private function rowErrors(array $row): array
    {
        $validator = Validator::make($row, [
            'name' => ['required', 'string', 'max:255'],
            'class_name' => ['required', 'string', 'max:255'],
            'category_name' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'in:L,P'],
            'guardian_name' => ['nullable', 'string', 'max:255'],
            'guardian_phone' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'required' => ':attribute wajib diisi.',
            'gender.in' => 'Jenis Kelamin harus L atau P.',
        ], [
            'name' => 'Nama',
            'class_name' => 'Kelas',
            'category_name' => 'Kategori',
            'gender' => 'Jenis Kelamin',
            'guardian_name' => 'Nama Wali',
            'guardian_phone' => 'No HP Wali',
            'phone' => 'No HP',
            'notes' => 'Catatan',
        ]);

        return $validator->errors()->all();
    }

    private function duplicateKey(string $name, string $className): string
    {
        if ($name === '' || $className === '') {
            return '';
        }

        return $this->normalizeLookup($name).'|'.$this->normalizeLookup($className);
    }

    private function normalizeLookup(string $value): string
    {
        return mb_strtoupper($this->clean($value));
    }

    private function clean(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        $value = trim((string) $value);

        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = $this->clean($value);

        return $value === '' ? null : $value;
    }
}
