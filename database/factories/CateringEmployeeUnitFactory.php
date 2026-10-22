<?php

namespace Database\Factories;

use App\Models\CateringEmployeeUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CateringEmployeeUnit>
 */
class CateringEmployeeUnitFactory extends Factory
{
    protected $model = CateringEmployeeUnit::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Unit '.$this->faker->unique()->company(),
            'price_per_person' => $this->faker->randomElement([13000, 14000]),
            'is_active' => true,
            'sort_order' => $this->faker->unique()->numberBetween(1, 10000),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
