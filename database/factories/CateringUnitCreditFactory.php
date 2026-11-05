<?php

namespace Database\Factories;

use App\Models\CateringEmployeeUnit;
use App\Models\CateringUnitBill;
use App\Models\CateringUnitCredit;
use App\Models\CateringUnitPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CateringUnitCredit>
 */
class CateringUnitCreditFactory extends Factory
{
    protected $model = CateringUnitCredit::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'catering_employee_unit_id' => CateringEmployeeUnit::factory(),
            'source_unit_payment_id' => CateringUnitPayment::factory(),
            'source_unit_bill_id' => CateringUnitBill::factory(),
            'original_amount' => 100000,
            'remaining_amount' => 100000,
        ];
    }
}
