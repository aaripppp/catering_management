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
        Schema::create('catering_unit_bill_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catering_unit_bill_id')
                ->constrained('catering_unit_bills')
                ->cascadeOnDelete();
            $table->date('attendance_date');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('price_per_person_snapshot');
            $table->unsignedBigInteger('subtotal');
            $table->timestamps();

            $table->unique(
                ['catering_unit_bill_id', 'attendance_date'],
                'unit_bill_day_bill_date_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catering_unit_bill_days');
    }
};
