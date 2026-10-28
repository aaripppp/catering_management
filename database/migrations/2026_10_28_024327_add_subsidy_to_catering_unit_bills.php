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
        Schema::table('catering_unit_bills', function (Blueprint $table) {
            $table->boolean('employee_subsidy_enabled')->default(false)->after('gross_amount');
            $table->unsignedBigInteger('employee_subsidy_qty')->default(0)->after('employee_subsidy_enabled');
            $table->unsignedBigInteger('employee_subsidy_rate')->default(0)->after('employee_subsidy_qty');
            $table->unsignedBigInteger('employee_subsidy_amount')->default(0)->after('employee_subsidy_rate');

            $table->boolean('student_subsidy_enabled')->default(false)->after('employee_subsidy_amount');
            $table->unsignedBigInteger('student_subsidy_qty')->default(0)->after('student_subsidy_enabled');
            $table->unsignedBigInteger('student_subsidy_rate')->default(0)->after('student_subsidy_qty');
            $table->unsignedBigInteger('student_subsidy_amount')->default(0)->after('student_subsidy_rate');

            $table->unsignedBigInteger('total_subsidy_amount')->default(0)->after('student_subsidy_amount');
            $table->unsignedBigInteger('net_amount')->default(0)->after('total_subsidy_amount');
        });

        DB::table('catering_unit_bills')->update([
            'employee_subsidy_enabled' => false,
            'employee_subsidy_qty' => 0,
            'employee_subsidy_rate' => 0,
            'employee_subsidy_amount' => 0,
            'student_subsidy_enabled' => false,
            'student_subsidy_qty' => 0,
            'student_subsidy_rate' => 0,
            'student_subsidy_amount' => 0,
            'total_subsidy_amount' => 0,
            'net_amount' => DB::raw('gross_amount'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catering_unit_bills', function (Blueprint $table) {
            $table->dropColumn([
                'employee_subsidy_enabled',
                'employee_subsidy_qty',
                'employee_subsidy_rate',
                'employee_subsidy_amount',
                'student_subsidy_enabled',
                'student_subsidy_qty',
                'student_subsidy_rate',
                'student_subsidy_amount',
                'total_subsidy_amount',
                'net_amount',
            ]);
        });
    }
};
