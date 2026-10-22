<?php

namespace Database\Factories;

use App\Models\CateringEmployeeUnit;
use App\Models\CateringUnitAttendance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CateringUnitAttendance>
 */
class CateringUnitAttendanceFactory extends Factory
{
    protected $model = CateringUnitAttendance::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'catering_employee_unit_id' => CateringEmployeeUnit::factory(),
            'attendance_date' => $this->faker->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d'),
            'quantity' => $this->faker->numberBetween(0, 50),
            'price_per_person_snapshot' => 13000,
        ];
    }

    public function zeroQuantity(): static
    {
        return $this->state(fn (): array => [
            'quantity' => 0,
        ]);
    }
}
