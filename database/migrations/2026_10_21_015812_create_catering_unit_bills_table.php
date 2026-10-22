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
        Schema::create('catering_unit_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catering_employee_unit_id')
                ->constrained('catering_employee_units')
                ->restrictOnDelete();
            $table->foreignId('generated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->unsignedTinyInteger('period_month');
            $table->unsignedSmallInteger('period_year');
            $table->string('unit_name');
            $table->unsignedBigInteger('total_quantity');
            $table->unsignedBigInteger('price_per_person_snapshot')->nullable();
            $table->unsignedBigInteger('gross_amount');
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->unique(
                ['catering_employee_unit_id', 'period_month', 'period_year'],
                'unit_bill_unit_period_unique',
            );
            $table->index(['period_year', 'period_month'], 'unit_bill_period_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catering_unit_bills');
    }
};
