<?php

namespace App\Models;

use Database\Factories\CateringPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['catering_bill_id', 'catering_member_id', 'amount', 'paid_at', 'note', 'created_by'])]
class CateringPayment extends Model
{
    /** @use HasFactory<CateringPaymentFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CateringBill, $this> */
    public function cateringBill(): BelongsTo
    {
        return $this->belongsTo(CateringBill::class);
    }

    /** @return BelongsTo<CateringMember, $this> */
    public function cateringMember(): BelongsTo
    {
        return $this->belongsTo(CateringMember::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasOne<CateringCredit, $this> */
    public function generatedCredit(): HasOne
    {
        return $this->hasOne(CateringCredit::class, 'source_payment_id');
    }
}
