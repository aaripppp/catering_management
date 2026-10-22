<?php

namespace App\Models;

use Database\Factories\CateringUnitPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'catering_unit_bill_id',
    'amount',
    'paid_at',
    'payment_method',
    'note',
    'created_by',
])]
class CateringUnitPayment extends Model
{
    /** @use HasFactory<CateringUnitPaymentFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CateringUnitBill, $this> */
    public function cateringUnitBill(): BelongsTo
    {
        return $this->belongsTo(CateringUnitBill::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
