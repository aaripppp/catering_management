<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Existing bills keep their historical gross amount. The new columns default
     * to zero and are only populated by an explicit billing synchronization.
     */
    public function up(): void
    {
        Schema::table('catering_bills', function (Blueprint $table): void {
            $table->unsignedSmallInteger('billable_culture_meal_days')->default(0)->after('billable_days');
            $table->unsignedBigInteger('culture_meal_billable_price')->default(0)->after('billable_culture_meal_days');
            $table->unsignedBigInteger('culture_meal_billable_amount')->default(0)->after('culture_meal_billable_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catering_bills', function (Blueprint $table): void {
            $table->dropColumn([
                'billable_culture_meal_days',
                'culture_meal_billable_price',
                'culture_meal_billable_amount',
            ]);
        });
    }
};
