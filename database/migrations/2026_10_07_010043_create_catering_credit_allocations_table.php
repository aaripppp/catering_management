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
        Schema::create('catering_credit_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catering_credit_id')->constrained()->restrictOnDelete();
            $table->foreignId('catering_bill_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->timestamp('applied_at');
            $table->timestamps();

            $table->unique(['catering_credit_id', 'catering_bill_id'], 'credit_allocations_credit_bill_unique');
            $table->index('catering_bill_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catering_credit_allocations');
    }
};
