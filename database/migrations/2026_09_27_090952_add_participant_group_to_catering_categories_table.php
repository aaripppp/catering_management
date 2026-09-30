<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Categories are migrated by their exact current name. This mapping is a one time
     * backfill for data that predates the participant_group column; application logic
     * must never classify a category by name again.
     *
     * @var array<string, string>
     */
    private const GROUP_BY_CATEGORY_NAME = [
        'Siswa Umum' => 'student',
        'Anak Guru' => 'student',
        'TK' => 'student',
        'Daycare' => 'student',
        'Guru' => 'employee',
        'TU' => 'employee',
        'Yayasan' => 'employee',
        'SDM' => 'employee',
    ];

    public function up(): void
    {
        Schema::table('catering_categories', function (Blueprint $table) {
            $table->string('participant_group')->nullable()->after('price_per_day');
        });

        foreach (self::GROUP_BY_CATEGORY_NAME as $name => $group) {
            DB::table('catering_categories')
                ->where('name', $name)
                ->update(['participant_group' => $group]);
        }

        $unclassified = DB::table('catering_categories')
            ->whereNull('participant_group')
            ->pluck('name')
            ->all();

        if ($unclassified !== []) {
            throw new RuntimeException(
                'catering_categories contains categories without a known participant group: '
                .implode(', ', $unclassified)
                .'. Set their participant group manually before retrying.',
            );
        }

        Schema::table('catering_categories', function (Blueprint $table) {
            $table->string('participant_group')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('catering_categories', function (Blueprint $table) {
            $table->dropColumn('participant_group');
        });
    }
};
