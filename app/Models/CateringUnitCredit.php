<?php

namespace App\Models;

use Database\Factories\CateringUnitCreditFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'catering_employee_unit_id',
    'source_unit_payment_id',
    'source_unit_bill_id',
    'original_amount',
    'remaining_amount',
])]
class CateringUnitCredit extends Model
{
    /** @use HasFactory<CateringUnitCreditFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'original_amount' => 'integer',
            'remaining_amount' => 'integer',
        ];
    }

    /** @return BelongsTo<CateringEmployeeUnit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(CateringEmployeeUnit::class, 'catering_employee_unit_id');
    }

    /** @return BelongsTo<CateringUnitPayment, $this> */
    public function sourcePayment(): BelongsTo
    {
        return $this->belongsTo(CateringUnitPayment::class, 'source_unit_payment_id');
    }

    /** @return BelongsTo<CateringUnitBill, $this> */
    public function sourceBill(): BelongsTo
    {
        return $this->belongsTo(CateringUnitBill::class, 'source_unit_bill_id');
    }

    /** @return HasMany<CateringUnitCreditAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(CateringUnitCreditAllocation::class);
    }
}
