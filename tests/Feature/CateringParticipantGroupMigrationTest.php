<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Runs the participant_group migration against a throwaway SQLite database so the
 * production and test databases are never touched.
 *
 * @return array{groups: array<string, string>, notnull: int, default: ?string}
 */
function runParticipantGroupMigrationAgainstLegacyCategories(array $legacyNames): array
{
    $originalConnection = config('database.default');
    $probePath = tempnam(sys_get_temp_dir(), 'catering-group-probe-').'.sqlite';
    touch($probePath);
    $result = ['groups' => [], 'notnull' => 0, 'default' => null];

    try {
        Config::set('database.connections.catering_group_probe', [
            'driver' => 'sqlite',
            'database' => $probePath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        Config::set('database.default', 'catering_group_probe');
        DB::purge('catering_group_probe');

        Schema::create('catering_categories', function ($table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('price_per_day');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        foreach ($legacyNames as $index => $name) {
            DB::table('catering_categories')->insert([
                'name' => $name,
                'price_per_day' => 15000 + $index,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $migrationFile = File::glob(database_path('migrations/*_add_participant_group_to_catering_categories_table.php'))[0];
        (require $migrationFile)->up();

        $column = collect(DB::select('PRAGMA table_info(catering_categories)'))
            ->firstWhere('name', 'participant_group');

        $result = [
            'groups' => DB::table('catering_categories')->pluck('participant_group', 'name')->all(),
            'notnull' => $column->notnull,
            'default' => $column->dflt_value,
        ];
    } finally {
        DB::purge('catering_group_probe');
        Config::set('database.default', $originalConnection);
        DB::forgetRecordModificationState();

        if (is_file($probePath)) {
            File::delete($probePath);
        }
    }

    return $result;
}

it('backfills every existing category with the approved participant group', function () {
    $result = runParticipantGroupMigrationAgainstLegacyCategories([
        'Siswa Umum',
        'Anak Guru',
        'TK',
        'Daycare',
        'Guru',
        'TU',
        'Yayasan',
        'SDM',
    ]);

    expect($result['groups'])->toBe([
        'Siswa Umum' => 'student',
        'Anak Guru' => 'student',
        'TK' => 'student',
        'Daycare' => 'student',
        'Guru' => 'employee',
        'TU' => 'employee',
        'Yayasan' => 'employee',
        'SDM' => 'employee',
    ]);

    expect($result['notnull'])->toBe(1)
        ->and($result['default'])->toBeNull();
});

it('refuses to guess a group and aborts when a category is unknown', function () {
    expect(fn () => runParticipantGroupMigrationAgainstLegacyCategories([
        'Siswa Umum',
        'Guru',
        'Kategori Misterius',
    ]))->toThrow(RuntimeException::class, 'Kategori Misterius');
});
