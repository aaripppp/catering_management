<?php

namespace Database\Factories;

use App\Models\CateringBill;
use App\Models\CateringMember;
use App\Models\CateringPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CateringPayment>
 */
class CateringPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'catering_bill_id' => CateringBill::factory(),
            'catering_member_id' => CateringMember::factory(),
            'amount' => 100000,
            'paid_at' => now(),
            'note' => null,
            'created_by' => null,
        ];
    }
}
