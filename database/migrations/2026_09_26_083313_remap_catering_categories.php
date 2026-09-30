<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Only run on MySQL - SQLite doesn't support SET FOREIGN_KEY_CHECKS
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $mapping = [
            'Reguler' => 'Siswa Umum',
            'Ekonomis' => 'Anak Guru',
            'Premium' => 'Guru',
            'Vegetarian' => 'TU',
            'Diet' => 'Yayasan',
            'Keto' => 'SDM',
            'Snack Saku' => 'Daycare',
            'Paket Bekas' => 'TK',
        ];

        // Disable foreign key checks temporarily
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach ($mapping as $oldName => $newName) {
            $oldId = DB::table('catering_categories')->where('name', $oldName)->value('id');
            $newId = DB::table('catering_categories')->where('name', $newName)->value('id');

            if ($oldId && $newId && $oldId !== $newId) {
                DB::table('catering_members')
                    ->where('catering_category_id', $oldId)
                    ->update(['catering_category_id' => $newId]);

                DB::table('catering_categories')->where('id', $oldId)->delete();
            }
        }

        $remainingOldNames = array_keys($mapping);
        DB::table('catering_categories')
            ->whereIn('name', $remainingOldNames)
            ->delete();

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        // Not reversible without backup data
    }
};
