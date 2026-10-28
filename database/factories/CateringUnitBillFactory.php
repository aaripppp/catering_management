<?php

namespace Database\Factories;

use App\Models\CateringEmployeeUnit;
use App\Models\CateringUnitBill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CateringUnitBill>
 */
class CateringUnitBillFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'catering_employee_unit_id' => CateringEmployeeUnit::factory(),
            'generated_by' => null,
            'period_month' => 10,
            'period_year' => 2026,
            'unit_name' => fake()->company(),
            'total_quantity' => 20,
            'price_per_person_snapshot' => 13000,
            'gross_amount' => 260000,
            'employee_subsidy_enabled' => false,
            'employee_subsidy_qty' => 0,
            'employee_subsidy_rate' => 0,
            'employee_subsidy_amount' => 0,
            'student_subsidy_enabled' => false,
            'student_subsidy_qty' => 0,
            'student_subsidy_rate' => 0,
            'student_subsidy_amount' => 0,
            'total_subsidy_amount' => 0,
            'net_amount' => 260000,
            'generated_at' => now(),
        ];
    }
}
