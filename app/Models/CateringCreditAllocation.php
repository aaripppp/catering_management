<?php

namespace App\Models;

use Database\Factories\CateringCreditAllocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['catering_credit_id', 'catering_bill_id', 'amount', 'applied_at'])]
class CateringCreditAllocation extends Model
{
    /** @use HasFactory<CateringCreditAllocationFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'applied_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CateringCredit, $this> */
    public function cateringCredit(): BelongsTo
    {
        return $this->belongsTo(CateringCredit::class);
    }

    /** @return BelongsTo<CateringBill, $this> */
    public function cateringBill(): BelongsTo
    {
        return $this->belongsTo(CateringBill::class);
    }
}
