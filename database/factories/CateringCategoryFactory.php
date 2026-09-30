<?php

namespace Database\Factories;

use App\Enums\CateringParticipantGroup;
use App\Models\CateringCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CateringCategory>
 */
class CateringCategoryFactory extends Factory
{
    protected $model = CateringCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst($this->faker->unique()->words(2, true)),
            'price_per_day' => $this->faker->randomElement([10000, 12000, 15000, 17000, 20000]),
            'participant_group' => CateringParticipantGroup::Student,
            'description' => $this->faker->sentence(),
            'is_active' => true,
        ];
    }

    public function student(): static
    {
        return $this->state(fn (array $attributes): array => [
            'participant_group' => CateringParticipantGroup::Student,
        ]);
    }

    public function employee(): static
    {
        return $this->state(fn (array $attributes): array => [
            'participant_group' => CateringParticipantGroup::Employee,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
