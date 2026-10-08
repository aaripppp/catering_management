<?php

namespace Database\Factories;

use App\Models\CateringBill;
use App\Models\CateringCredit;
use App\Models\CateringCreditAllocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CateringCreditAllocation>
 */
class CateringCreditAllocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'catering_credit_id' => CateringCredit::factory(),
            'catering_bill_id' => CateringBill::factory(),
            'amount' => 50000,
            'applied_at' => now(),
        ];
    }
}
