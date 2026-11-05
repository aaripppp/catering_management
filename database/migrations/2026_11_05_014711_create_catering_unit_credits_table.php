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
        Schema::create('catering_unit_credits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('catering_employee_unit_id');
            $table->foreign('catering_employee_unit_id', 'cu_credit_unit_fk')
                ->references('id')
                ->on('catering_employee_units')
                ->restrictOnDelete();
            $table->foreignId('source_unit_payment_id');
            $table->foreign('source_unit_payment_id', 'cu_credit_payment_fk')
                ->references('id')
                ->on('catering_unit_payments')
                ->restrictOnDelete();
            $table->foreignId('source_unit_bill_id');
            $table->foreign('source_unit_bill_id', 'cu_credit_bill_fk')
                ->references('id')
                ->on('catering_unit_bills')
                ->restrictOnDelete();
            $table->unsignedBigInteger('original_amount');
            $table->unsignedBigInteger('remaining_amount');
            $table->timestamps();

            $table->unique('source_unit_payment_id', 'cu_credit_payment_uq');
            $table->index('source_unit_bill_id', 'cu_credit_bill_idx');
            $table->index(
                ['catering_employee_unit_id', 'remaining_amount'],
                'cu_credit_unit_remaining_idx',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catering_unit_credits');
    }
};
