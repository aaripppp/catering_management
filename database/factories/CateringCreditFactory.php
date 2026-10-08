<?php

namespace Database\Factories;

use App\Models\CateringBill;
use App\Models\CateringCredit;
use App\Models\CateringMember;
use App\Models\CateringPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CateringCredit>
 */
class CateringCreditFactory extends Factory
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
            'source_payment_id' => CateringPayment::factory(),
            'source_bill_id' => CateringBill::factory(),
            'original_amount' => 100000,
            'remaining_amount' => 100000,
        ];
    }
}
