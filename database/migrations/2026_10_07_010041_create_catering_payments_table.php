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
        Schema::create('catering_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catering_bill_id')->constrained()->restrictOnDelete();
            $table->foreignId('catering_member_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->timestamp('paid_at');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['catering_member_id', 'paid_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catering_payments');
    }
};
