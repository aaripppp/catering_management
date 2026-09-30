<?php

namespace Database\Factories;

use App\Enums\SchoolLevel;
use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SchoolClass>
 */
class SchoolClassFactory extends Factory
{
    protected $model = SchoolClass::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $counter = 0;
        $counter++;
        $level = $this->faker->randomElement(SchoolLevel::cases());

        return [
            'name' => 'Kelas '.Str::upper($this->faker->unique()->randomLetter()).$counter,
            'level' => $level->value,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
