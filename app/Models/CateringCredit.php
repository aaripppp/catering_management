<?php

namespace App\Models;

use Database\Factories\CateringCreditFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['catering_member_id', 'source_payment_id', 'source_bill_id', 'original_amount', 'remaining_amount'])]
class CateringCredit extends Model
{
    /** @use HasFactory<CateringCreditFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'original_amount' => 'integer',
            'remaining_amount' => 'integer',
        ];
    }

    /** @return BelongsTo<CateringMember, $this> */
    public function cateringMember(): BelongsTo
    {
        return $this->belongsTo(CateringMember::class);
    }

    /** @return BelongsTo<CateringPayment, $this> */
    public function sourcePayment(): BelongsTo
    {
        return $this->belongsTo(CateringPayment::class, 'source_payment_id');
    }

    /** @return BelongsTo<CateringBill, $this> */
    public function sourceBill(): BelongsTo
    {
        return $this->belongsTo(CateringBill::class, 'source_bill_id');
    }

    /** @return HasMany<CateringCreditAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(CateringCreditAllocation::class);
    }
}
