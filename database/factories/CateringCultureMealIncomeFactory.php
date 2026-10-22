<?php

namespace Database\Factories;

use App\Models\CateringCultureMealIncome;
use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CateringCultureMealIncome>
 */
class CateringCultureMealIncomeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'income_date' => '2026-10-10',
            'school_class_id' => SchoolClass::factory(),
            'school_class_name' => fake()->randomElement(['VII A', 'VIII B', 'IX C']),
            'amount' => 16000,
            'note' => null,
            'created_by' => null,
        ];
    }
}
