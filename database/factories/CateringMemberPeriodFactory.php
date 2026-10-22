<?php

namespace Database\Factories;

use App\Models\CateringMember;
use App\Models\CateringMemberPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CateringMemberPeriod>
 */
class CateringMemberPeriodFactory extends Factory
{
    protected $model = CateringMemberPeriod::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'catering_member_id' => CateringMember::factory(),
            'start_date' => '2026-09-01',
            'end_date' => null,
        ];
    }

    /**
     * A period that has already been closed on the given last eligible day.
     */
    public function ended(string $endDate): static
    {
        return $this->state(fn (array $attributes): array => [
            'start_date' => '2026-09-01',
            'end_date' => $endDate,
        ]);
    }
}
