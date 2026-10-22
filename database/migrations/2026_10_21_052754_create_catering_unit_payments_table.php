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
        Schema::create('catering_unit_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('catering_unit_bill_id')
                ->constrained('catering_unit_bills')
                ->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->timestamp('paid_at');
            $table->string('payment_method')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['catering_unit_bill_id', 'paid_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catering_unit_payments');
    }
};
