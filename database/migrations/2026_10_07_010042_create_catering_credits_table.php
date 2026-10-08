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
        Schema::create('catering_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catering_member_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_payment_id')->unique()->constrained('catering_payments')->restrictOnDelete();
            $table->foreignId('source_bill_id')->constrained('catering_bills')->restrictOnDelete();
            $table->unsignedBigInteger('original_amount');
            $table->unsignedBigInteger('remaining_amount');
            $table->timestamps();

            $table->index(['catering_member_id', 'remaining_amount']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catering_credits');
    }
};
