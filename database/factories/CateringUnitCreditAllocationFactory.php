<?php

namespace Database\Factories;

use App\Models\CateringUnitBill;
use App\Models\CateringUnitCredit;
use App\Models\CateringUnitCreditAllocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CateringUnitCreditAllocation>
 */
class CateringUnitCreditAllocationFactory extends Factory
{
    protected $model = CateringUnitCreditAllocation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'catering_unit_credit_id' => CateringUnitCredit::factory(),
            'catering_unit_bill_id' => CateringUnitBill::factory(),
            'amount' => 100000,
            'applied_at' => now(),
        ];
    }
}
