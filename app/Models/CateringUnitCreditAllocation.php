<?php

namespace App\Models;

use Database\Factories\CateringUnitCreditAllocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'catering_unit_credit_id',
    'catering_unit_bill_id',
    'amount',
    'applied_at',
])]
class CateringUnitCreditAllocation extends Model
{
    /** @use HasFactory<CateringUnitCreditAllocationFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'applied_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CateringUnitCredit, $this> */
    public function credit(): BelongsTo
    {
        return $this->belongsTo(CateringUnitCredit::class, 'catering_unit_credit_id');
    }

    /** @return BelongsTo<CateringUnitBill, $this> */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(CateringUnitBill::class, 'catering_unit_bill_id');
    }
}
