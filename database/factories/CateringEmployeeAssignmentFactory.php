<?php

namespace Database\Factories;

use App\Enums\CateringEmployeeAssignmentType;
use App\Enums\SchoolLevel;
use App\Models\CateringEmployeeAssignment;
use App\Models\CateringMember;
use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CateringEmployeeAssignment>
 */
class CateringEmployeeAssignmentFactory extends Factory
{
    protected $model = CateringEmployeeAssignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'catering_member_id' => CateringMember::factory(),
            'assignment_type' => CateringEmployeeAssignmentType::General,
            'school_class_id' => null,
            'level' => null,
            'education_level' => null,
        ];
    }

    public function schoolClass(SchoolClass|int|null $schoolClass = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'assignment_type' => CateringEmployeeAssignmentType::SchoolClass,
            'school_class_id' => $schoolClass instanceof SchoolClass
                ? $schoolClass->id
                : ($schoolClass ?? SchoolClass::factory()),
            'level' => null,
            'education_level' => null,
        ]);
    }

    public function level(SchoolLevel|string|null $level = null): static
    {
        $value = $level instanceof SchoolLevel ? $level->value : $level;

        return $this->state(fn (array $attributes): array => [
            'assignment_type' => CateringEmployeeAssignmentType::Level,
            'school_class_id' => null,
            'level' => $value ?? $this->faker->randomElement(SchoolLevel::cases())->value,
            'education_level' => null,
        ]);
    }

    public function educationLevel(?string $educationLevel = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'assignment_type' => CateringEmployeeAssignmentType::EducationLevel,
            'school_class_id' => null,
            'level' => null,
            'education_level' => $educationLevel ?? $this->faker->randomElement(SchoolLevel::educationLevels()),
        ]);
    }

    public function general(): static
    {
        return $this->state(fn (array $attributes): array => [
            'assignment_type' => CateringEmployeeAssignmentType::General,
            'school_class_id' => null,
            'level' => null,
            'education_level' => null,
        ]);
    }
}
