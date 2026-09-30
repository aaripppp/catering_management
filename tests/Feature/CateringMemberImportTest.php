<?php

use App\Enums\Gender;
use App\Livewire\CateringMemberImport;
use App\Models\CateringCategory;
use App\Models\CateringMember;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\CateringMemberImportService;
use App\Services\CateringMemberImportSpreadsheet;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;

function cateringMemberImportRow(array $overrides = []): array
{
    return array_merge([
        'row_number' => 2,
        'name' => 'Arif Test',
        'class_name' => 'VII A',
        'category_name' => '',
        'gender' => 'L',
        'guardian_name' => 'Bapak Arif',
        'guardian_phone' => '081234567891',
        'phone' => '081234567890',
        'notes' => 'Tanpa alergi',
    ], $overrides);
}

function cateringMemberImportWorkbook(array $rows, array $headers = CateringMemberImportSpreadsheet::HEADERS): string
{
    $path = tempnam(sys_get_temp_dir(), 'catering-member-import-test-');
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->getCurrentSheet()->setName('DATA PESERTA');
    $writer->addRow(Row::fromValues($headers));

    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }

    $writer->close();

    return $path;
}

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->schoolClass = SchoolClass::factory()->create([
        'name' => 'VII A',
        'level' => '7',
    ]);
    $this->defaultCategory = CateringCategory::factory()->create([
        'name' => 'Siswa Umum',
        'price_per_day' => 17000,
    ]);
    $this->customCategory = CateringCategory::factory()->create([
        'name' => 'Anak Guru',
        'price_per_day' => 15000,
    ]);
});

it('protects the catering member import route', function () {
    $this->get(route('catering-members.import'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->waliKelas()->create())
        ->get(route('catering-members.import'))
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->get(route('catering-members.import'))
        ->assertOk();
});

it('downloads the xlsx template through Livewire', function () {
    Livewire::actingAs($this->admin)
        ->test(CateringMemberImport::class)
        ->call('downloadTemplate')
        ->assertFileDownloaded('template-import-peserta-catering.xlsx');
});

it('generates all required template sheets and references', function () {
    foreach ([
        ['name' => '10A', 'level' => '10'],
        ['name' => '2A', 'level' => '2'],
        ['name' => '1A', 'level' => '1'],
        ['name' => '12B-IPS', 'level' => '12'],
        ['name' => '12A-IPS', 'level' => '12'],
        ['name' => '12B-IPA', 'level' => '12'],
        ['name' => '12A-IPA', 'level' => '12'],
    ] as $class) {
        SchoolClass::factory()->create($class);
    }

    $path = app(CateringMemberImportSpreadsheet::class)->createTemplate();
    $reader = new Reader;
    $reader->open($path);
    $sheets = [];

    try {
        foreach ($reader->getSheetIterator() as $sheet) {
            $sheets[$sheet->getName()] = collect(iterator_to_array($sheet->getRowIterator()))
                ->map(fn (Row $row): array => $row->toArray())
                ->values()
                ->all();
        }
    } finally {
        $reader->close();
        @unlink($path);
    }

    $classNames = collect($sheets['REFERENSI KELAS'])->skip(1)->pluck(2)->values()->all();

    expect(array_keys($sheets))->toBe([
        'DATA PESERTA',
        'REFERENSI KELAS',
        'REFERENSI KATEGORI',
        'PANDUAN',
    ])->and($sheets['DATA PESERTA'][0])->toBe(CateringMemberImportSpreadsheet::HEADERS)
        ->and($classNames)->toContain('VII A')
        ->and(array_search('1A', $classNames, true))->toBeLessThan(array_search('2A', $classNames, true))
        ->and(array_search('2A', $classNames, true))->toBeLessThan(array_search('10A', $classNames, true))
        ->and(array_values(array_filter($classNames, fn (string $name): bool => str_starts_with($name, '12'))))->toBe([
            '12A-IPA',
            '12B-IPA',
            '12A-IPS',
            '12B-IPS',
        ])
        ->and(collect($sheets['REFERENSI KATEGORI'])->pluck(0))->toContain('Siswa Umum');
});

it('reads a valid workbook and ignores completely empty rows', function () {
    $path = cateringMemberImportWorkbook([
        [' Arif   Test ', ' VII A ', '', 'l', ' Bapak Arif ', '0812', '0813', ' Catatan '],
        ['', '', '', '', '', '', '', ''],
    ]);

    try {
        $rows = app(CateringMemberImportSpreadsheet::class)->read($path);
    } finally {
        @unlink($path);
    }

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['row_number'])->toBe(2)
        ->and($rows[0]['name'])->toBe('Arif Test')
        ->and($rows[0]['class_name'])->toBe('VII A')
        ->and($rows[0]['gender'])->toBe('L');
});

it('rejects a workbook with invalid headers', function () {
    $path = cateringMemberImportWorkbook([], ['Nama Salah']);

    try {
        expect(fn () => app(CateringMemberImportSpreadsheet::class)->read($path))
            ->toThrow(RuntimeException::class, 'Header file tidak sesuai');
    } finally {
        @unlink($path);
    }
});

it('requires Nama and Kelas', function () {
    $preview = app(CateringMemberImportService::class)->preview([
        cateringMemberImportRow(['name' => '', 'class_name' => '']),
    ]);

    expect($preview['has_errors'])->toBeTrue()
        ->and($preview['rows'][0]['errors'])->toContain('Nama wajib diisi.')
        ->and($preview['rows'][0]['errors'])->toContain('Kelas wajib diisi.');
});

it('allows blank category and resolves it to Siswa Umum', function () {
    $preview = app(CateringMemberImportService::class)->preview([
        cateringMemberImportRow(['category_name' => '']),
    ]);

    expect($preview['has_errors'])->toBeFalse()
        ->and($preview['rows'][0]['catering_category_id'])->toBe($this->defaultCategory->id)
        ->and($preview['rows'][0]['resolved_category_name'])->toBe('Siswa Umum');
});

it('resolves custom category by name', function () {
    $preview = app(CateringMemberImportService::class)->preview([
        cateringMemberImportRow(['category_name' => 'Anak Guru']),
    ]);

    expect($preview['has_errors'])->toBeFalse()
        ->and($preview['rows'][0]['catering_category_id'])->toBe($this->customCategory->id);
});

it('blocks missing class without creating it', function () {
    $classCount = SchoolClass::query()->count();
    $preview = app(CateringMemberImportService::class)->preview([
        cateringMemberImportRow(['class_name' => 'VII Z']),
    ]);

    expect($preview['has_errors'])->toBeTrue()
        ->and($preview['rows'][0]['status_label'])->toContain('Kelas "VII Z" tidak ditemukan.')
        ->and(SchoolClass::query()->count())->toBe($classCount);
});

it('blocks missing category without creating it', function () {
    $categoryCount = CateringCategory::query()->count();
    $preview = app(CateringMemberImportService::class)->preview([
        cateringMemberImportRow(['category_name' => 'Kategori ABC']),
    ]);

    expect($preview['has_errors'])->toBeTrue()
        ->and($preview['rows'][0]['status_label'])->toContain('Kategori "Kategori ABC" tidak ditemukan.')
        ->and(CateringCategory::query()->count())->toBe($categoryCount);
});

it('blocks a blank category when Siswa Umum is missing', function () {
    $this->defaultCategory->delete();

    $preview = app(CateringMemberImportService::class)->preview([
        cateringMemberImportRow(['category_name' => '']),
    ]);

    expect($preview['has_errors'])->toBeTrue()
        ->and($preview['rows'][0]['status_label'])->toContain('Kategori default "Siswa Umum" tidak ditemukan.');
});

it('resolves class and category ignoring harmless case and spacing', function () {
    $preview = app(CateringMemberImportService::class)->preview([
        cateringMemberImportRow([
            'class_name' => '  vii   a  ',
            'category_name' => ' anak   guru ',
        ]),
    ]);

    expect($preview['has_errors'])->toBeFalse()
        ->and($preview['rows'][0]['school_class_id'])->toBe($this->schoolClass->id)
        ->and($preview['rows'][0]['catering_category_id'])->toBe($this->customCategory->id)
        ->and($preview['rows'][0]['resolved_class_name'])->toBe('VII A')
        ->and($preview['rows'][0]['resolved_category_name'])->toBe('Anak Guru');
});

it('validates optional gender and field lengths', function () {
    $preview = app(CateringMemberImportService::class)->preview([
        cateringMemberImportRow(['gender' => 'X', 'phone' => str_repeat('1', 31)]),
    ]);

    expect($preview['has_errors'])->toBeTrue()
        ->and($preview['rows'][0]['status_label'])->toContain('Jenis Kelamin harus L atau P.')
        ->and($preview['rows'][0]['errors'])->not->toBeEmpty();
});

it('blocks duplicate name and class inside the uploaded file', function () {
    $preview = app(CateringMemberImportService::class)->preview([
        cateringMemberImportRow(),
        cateringMemberImportRow([
            'row_number' => 3,
            'name' => ' arif  test ',
            'class_name' => ' vii  a ',
        ]),
    ]);

    expect($preview['summary']['errors'])->toBe(2)
        ->and($preview['rows'][0]['status_label'])->toContain('lebih dari sekali dalam file')
        ->and($preview['rows'][1]['status_label'])->toContain('lebih dari sekali dalam file');
});

it('blocks duplicate name and class already in the database', function () {
    CateringMember::factory()->create([
        'name' => 'Arif Test',
        'school_class_id' => $this->schoolClass->id,
    ]);

    $preview = app(CateringMemberImportService::class)->preview([
        cateringMemberImportRow(['name' => ' arif   test ']),
    ]);

    expect($preview['has_errors'])->toBeTrue()
        ->and($preview['rows'][0]['status_label'])->toContain('sudah terdaftar');
});

it('does not write CateringMember records during preview', function () {
    $before = CateringMember::query()->count();

    $preview = app(CateringMemberImportService::class)->preview([
        cateringMemberImportRow(),
    ]);

    expect($preview['summary'])->toBe(['total' => 1, 'ready' => 1, 'errors' => 0])
        ->and(CateringMember::query()->count())->toBe($before);
});

it('blocks the complete import when any row has an error', function () {
    expect(fn () => app(CateringMemberImportService::class)->import([
        cateringMemberImportRow(),
        cateringMemberImportRow(['row_number' => 3, 'name' => 'Error Test', 'class_name' => 'DOES NOT EXIST']),
    ]))->toThrow(ValidationException::class);

    expect(CateringMember::query()->count())->toBe(0);
});

it('imports all valid fields and category breakdown in one transaction', function () {
    $result = app(CateringMemberImportService::class)->import([
        cateringMemberImportRow(),
        cateringMemberImportRow([
            'row_number' => 3,
            'name' => 'Siti Test',
            'category_name' => 'Anak Guru',
            'gender' => 'P',
            'guardian_name' => '',
            'guardian_phone' => '',
            'phone' => '',
            'notes' => '',
        ]),
    ]);

    $arif = CateringMember::query()->where('name', 'Arif Test')->sole();
    $siti = CateringMember::query()->where('name', 'Siti Test')->sole();

    expect($result['total'])->toBe(2)
        ->and($result['breakdown'])->toBe(['Anak Guru' => 1, 'Siswa Umum' => 1])
        ->and($arif->school_class_id)->toBe($this->schoolClass->id)
        ->and($arif->catering_category_id)->toBe($this->defaultCategory->id)
        ->and($arif->gender)->toBe(Gender::Male)
        ->and($arif->guardian_name)->toBe('Bapak Arif')
        ->and($arif->guardian_phone)->toBe('081234567891')
        ->and($arif->phone)->toBe('081234567890')
        ->and($arif->notes)->toBe('Tanpa alergi')
        ->and($arif->is_active)->toBeTrue()
        ->and($siti->catering_category_id)->toBe($this->customCategory->id)
        ->and($siti->gender)->toBe(Gender::Female)
        ->and($siti->guardian_name)->toBeNull();
});

it('rolls back every member when a later insert fails unexpectedly', function () {
    $originalDispatcher = clone CateringMember::getEventDispatcher();

    CateringMember::creating(function (CateringMember $member): void {
        if ($member->name === 'Second Member') {
            throw new RuntimeException('Simulated failure.');
        }
    });

    try {
        expect(fn () => app(CateringMemberImportService::class)->import([
            cateringMemberImportRow(['name' => 'First Member']),
            cateringMemberImportRow(['row_number' => 3, 'name' => 'Second Member']),
        ]))->toThrow(RuntimeException::class, 'Simulated failure.');
    } finally {
        CateringMember::setEventDispatcher($originalDispatcher);
    }

    expect(CateringMember::query()->count())->toBe(0);
});

it('previews and imports a workbook through the Livewire flow', function () {
    $path = cateringMemberImportWorkbook([
        ['Arif Test', 'VII A', '', 'L', 'Bapak Arif', '0812', '0813', 'Catatan'],
    ]);
    $upload = UploadedFile::fake()->createWithContent('peserta.xlsx', file_get_contents($path));

    try {
        $component = Livewire::actingAs($this->admin)
            ->test(CateringMemberImport::class)
            ->set('file', $upload)
            ->call('previewImport')
            ->assertHasNoErrors()
            ->assertSet('step', 2)
            ->assertSee('Siswa Umum')
            ->assertSee('Semua data valid');

        expect(CateringMember::query()->count())->toBe(0);

        $component
            ->call('confirmImport')
            ->assertHasNoErrors()
            ->assertSet('step', 3)
            ->assertSee('Import Berhasil')
            ->assertDispatched(
                'toast',
                type: 'success',
                message: 'Peserta catering berhasil diimport.',
            );

        expect(CateringMember::query()->where('name', 'Arif Test')->sole()->catering_category_id)
            ->toBe($this->defaultCategory->id);
    } finally {
        @unlink($path);
    }
});

it('shows blocking row errors through the Livewire preview', function () {
    $path = cateringMemberImportWorkbook([
        ['Test Error', 'DOES NOT EXIST', '', '', '', '', '', ''],
    ]);
    $upload = UploadedFile::fake()->createWithContent('invalid.xlsx', file_get_contents($path));

    try {
        Livewire::actingAs($this->admin)
            ->test(CateringMemberImport::class)
            ->set('file', $upload)
            ->call('previewImport')
            ->assertSet('step', 2)
            ->assertSee('Perbaiki file sebelum import')
            ->assertSee('Kelas &quot;DOES NOT EXIST&quot; tidak ditemukan.', false)
            ->call('confirmImport')
            ->assertHasErrors(['import']);
    } finally {
        @unlink($path);
    }

    expect(CateringMember::query()->count())->toBe(0);
});
