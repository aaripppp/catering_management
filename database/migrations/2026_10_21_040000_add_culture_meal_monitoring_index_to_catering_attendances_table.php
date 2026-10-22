<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a composite index for the Budaya Makan monitoring query.
     *
     * The monitoring filters on status + attendance_date range and joins the
     * membership coverage by catering_member_id. The existing unique index on
     * (catering_member_id, attendance_date) does not serve that access path.
     */
    public function up(): void
    {
        Schema::table('catering_attendances', function (Blueprint $table): void {
            $table->index(['status', 'attendance_date', 'catering_member_id'], 'cat_att_status_date_member_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catering_attendances', function (Blueprint $table): void {
            $table->dropIndex('cat_att_status_date_member_index');
        });
    }
};
