<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('catering_bills', function (Blueprint $table): void {
            $table->unsignedSmallInteger('billable_days')->default(0)->after('active_days');
        });

        DB::table('catering_bills')->update([
            'billable_days' => DB::raw('active_days'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catering_bills', function (Blueprint $table): void {
            $table->dropColumn('billable_days');
        });
    }
};
