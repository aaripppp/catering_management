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
        Schema::create('catering_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catering_member_id')
                ->constrained('catering_members')
                ->restrictOnDelete();
            $table->date('attendance_date');
            $table->string('status');
            $table->timestamps();

            $table->unique(['catering_member_id', 'attendance_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catering_attendances');
    }
};
