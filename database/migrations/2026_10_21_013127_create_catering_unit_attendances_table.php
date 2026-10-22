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
        Schema::create('catering_unit_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catering_employee_unit_id')
                ->constrained('catering_employee_units')
                ->restrictOnDelete();
            $table->date('attendance_date');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('price_per_person_snapshot');
            $table->timestamps();

            $table->unique(
                ['catering_employee_unit_id', 'attendance_date'],
                'unit_attendance_unit_date_unique',
            );
            $table->index(
                ['attendance_date', 'catering_employee_unit_id'],
                'unit_attendance_date_unit_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catering_unit_attendances');
    }
};
