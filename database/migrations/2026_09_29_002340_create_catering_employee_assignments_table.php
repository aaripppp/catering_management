<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catering_employee_assignments', function (Blueprint $table) {
            $table->id();

            // V1 keeps exactly one placement per employee, so the member id is
            // unique rather than merely indexed.
            $table->foreignId('catering_member_id')
                ->unique()
                ->constrained('catering_members')
                ->restrictOnDelete();

            $table->string('assignment_type', 30);

            $table->foreignId('school_class_id')
                ->nullable()
                ->constrained('school_classes')
                ->nullOnDelete();

            $table->string('level', 20)->nullable();
            $table->string('education_level', 20)->nullable();

            $table->timestamps();

            // The dashboard will recap by target, so index each axis the recap
            // groups on instead of scanning the table.
            $table->index(['assignment_type', 'school_class_id'], 'catering_employee_assignments_class_index');
            $table->index(['assignment_type', 'level'], 'catering_employee_assignments_level_index');
            $table->index(['assignment_type', 'education_level'], 'catering_employee_assignments_education_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catering_employee_assignments');
    }
};
