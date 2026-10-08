<?php

namespace Database\Factories;

use App\Enums\CateringBillStatus;
use App\Enums\CateringParticipantGroup;
use App\Models\CateringBill;
use App\Models\CateringCategory;
use App\Models\CateringMember;
use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CateringBill>
 */
class CateringBillFactory extends Factory
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
            'school_class_id' => SchoolClass::factory(),
            'catering_category_id' => CateringCategory::factory(),
            'generated_by' => null,
            'period_month' => 10,
            'period_year' => 2026,
            'member_name' => fake()->name(),
            'participant_group' => CateringParticipantGroup::Student->value,
            'school_class_name' => '7A',
            'school_level' => '7',
            'guardian_name' => fake()->name(),
            'guardian_phone' => fake()->numerify('08##########'),
            'category_name' => 'Siswa Umum',
            'price_per_day' => 20000,
            'saved_days' => 20,
            'active_days' => 20,
            'sakit_days' => 0,
            'izin_days' => 0,
            'alfa_days' => 0,
            'off_days' => 0,
            'ujian_days' => 0,
            'event_unit_days' => 0,
            'puasa_days' => 0,
            'libur_days' => 0,
            'gross_amount' => 400000,
            'payment_status' => CateringBillStatus::Unpaid,
            'generated_at' => now(),
        ];
    }
}
