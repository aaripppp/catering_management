<?php

namespace Database\Factories;

use App\Models\CateringUnitBill;
use App\Models\CateringUnitPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CateringUnitPayment>
 */
class CateringUnitPaymentFactory extends Factory
{
    protected $model = CateringUnitPayment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'catering_unit_bill_id' => CateringUnitBill::factory(),
            'amount' => 100000,
            'paid_at' => now(),
            'payment_method' => null,
            'note' => null,
            'created_by' => null,
        ];
    }
}
