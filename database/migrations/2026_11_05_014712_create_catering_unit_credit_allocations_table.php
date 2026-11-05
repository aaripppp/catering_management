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
        Schema::create('catering_unit_credit_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('catering_unit_credit_id');
            $table->foreign('catering_unit_credit_id', 'cu_alloc_credit_fk')
                ->references('id')
                ->on('catering_unit_credits')
                ->restrictOnDelete();
            $table->foreignId('catering_unit_bill_id');
            $table->foreign('catering_unit_bill_id', 'cu_alloc_bill_fk')
                ->references('id')
                ->on('catering_unit_bills')
                ->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->timestamp('applied_at');
            $table->timestamps();

            $table->unique(
                ['catering_unit_credit_id', 'catering_unit_bill_id'],
                'cu_alloc_credit_bill_uq',
            );
            $table->index('catering_unit_bill_id', 'cu_alloc_bill_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catering_unit_credit_allocations');
    }
};
