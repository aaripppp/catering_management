<?php

namespace Database\Factories;

use App\Enums\CateringAttendanceStatus;
use App\Models\CateringAttendance;
use App\Models\CateringMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CateringAttendance>
 */
class CateringAttendanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'catering_member_id' => CateringMember::factory(),
            'attendance_date' => $this->faker->date(),
            'status' => $this->faker->randomElement(CateringAttendanceStatus::cases()),
        ];
    }
}
