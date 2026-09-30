<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Models\CateringCategory;
use App\Models\CateringMember;
use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CateringMember>
 */
class CateringMemberFactory extends Factory
{
    protected $model = CateringMember::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'school_class_id' => SchoolClass::factory(),
            'catering_category_id' => CateringCategory::factory(),
            'gender' => $this->faker->randomElement(Gender::cases()),
            'phone' => $this->faker->numerify('08##########'),
            'guardian_name' => $this->faker->name(),
            'guardian_phone' => $this->faker->numerify('08##########'),
            'notes' => null,
            'is_active' => true,
        ];
    }

    public function withoutClass(): static
    {
        return $this->state(fn (array $attributes): array => [
            'school_class_id' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
