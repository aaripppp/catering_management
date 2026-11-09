<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('catering_attendances', function (Blueprint $table): void {
            $table->boolean('is_culture_meal_billable')->default(false)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catering_attendances', function (Blueprint $table): void {
            $table->dropColumn('is_culture_meal_billable');
        });
    }
};
