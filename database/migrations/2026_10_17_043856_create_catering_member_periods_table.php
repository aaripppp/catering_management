<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catering_member_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catering_member_id')
                ->constrained('catering_members')
                ->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->timestamps();

            $table->index(['catering_member_id', 'start_date', 'end_date'], 'cmp_member_start_end');
            $table->index(['start_date', 'end_date'], 'cmp_start_end');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catering_member_periods');
    }
};
