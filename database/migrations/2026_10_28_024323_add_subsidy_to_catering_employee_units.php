<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('catering_employee_units', function (Blueprint $table) {
            $table->unsignedBigInteger('default_employee_subsidy_rate')->default(0)->after('price_per_person');
            $table->unsignedBigInteger('default_student_subsidy_rate')->default(0)->after('default_employee_subsidy_rate');
        });

        DB::table('catering_employee_units')->update([
            'default_employee_subsidy_rate' => 0,
            'default_student_subsidy_rate' => 0,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catering_employee_units', function (Blueprint $table) {
            $table->dropColumn(['default_employee_subsidy_rate', 'default_student_subsidy_rate']);
        });
    }
};
