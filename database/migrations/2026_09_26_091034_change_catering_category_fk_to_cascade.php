<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Only run on MySQL - SQLite doesn't support dropping foreign keys by name
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('catering_members', function (Blueprint $table) {
            $table->dropForeign('catering_members_catering_category_id_foreign');
            $table->foreign('catering_category_id')
                ->references('id')
                ->on('catering_categories')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('catering_members', function (Blueprint $table) {
            $table->dropForeign('catering_members_catering_category_id_foreign');
            $table->foreign('catering_category_id')
                ->references('id')
                ->on('catering_categories')
                ->onDelete('restrict');
        });
    }
};
