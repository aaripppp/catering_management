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
        Schema::create('catering_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catering_member_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_class_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('catering_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('period_month');
            $table->unsignedSmallInteger('period_year');
            $table->string('member_name');
            $table->string('participant_group');
            $table->string('school_class_name')->nullable();
            $table->string('school_level')->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('guardian_phone', 30)->nullable();
            $table->string('category_name');
            $table->unsignedBigInteger('price_per_day');
            $table->unsignedSmallInteger('saved_days')->default(0);
            $table->unsignedSmallInteger('active_days')->default(0);
            $table->unsignedSmallInteger('sakit_days')->default(0);
            $table->unsignedSmallInteger('izin_days')->default(0);
            $table->unsignedSmallInteger('alfa_days')->default(0);
            $table->unsignedSmallInteger('off_days')->default(0);
            $table->unsignedSmallInteger('ujian_days')->default(0);
            $table->unsignedSmallInteger('event_unit_days')->default(0);
            $table->unsignedSmallInteger('puasa_days')->default(0);
            $table->unsignedSmallInteger('libur_days')->default(0);
            $table->unsignedBigInteger('gross_amount');
            $table->string('payment_status');
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->unique(['catering_member_id', 'period_month', 'period_year'], 'bills_member_period_unique');
            $table->index(['period_year', 'period_month']);
            $table->index(['participant_group', 'payment_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catering_bills');
    }
};
