<?php

namespace Database\Factories;

use App\Models\CateringUnitBill;
use App\Models\CateringUnitBillDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CateringUnitBillDay>
 */
class CateringUnitBillDayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(0, 50);
        $price = fake()->randomElement([13000, 14000]);

        return [
            'catering_unit_bill_id' => CateringUnitBill::factory(),
            'attendance_date' => fake()->dateTimeBetween('2026-10-01', '2026-10-31')->format('Y-m-d'),
            'quantity' => $quantity,
            'price_per_person_snapshot' => $price,
            'subtotal' => $quantity * $price,
        ];
    }
}
