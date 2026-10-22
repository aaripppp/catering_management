<?php

namespace App\Models;

use Database\Factories\CateringUnitBillDayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'catering_unit_bill_id',
    'attendance_date',
    'quantity',
    'price_per_person_snapshot',
    'subtotal',
])]
class CateringUnitBillDay extends Model
{
    /** @use HasFactory<CateringUnitBillDayFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'quantity' => 'integer',
            'price_per_person_snapshot' => 'integer',
            'subtotal' => 'integer',
        ];
    }

    /** @return BelongsTo<CateringUnitBill, $this> */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(CateringUnitBill::class, 'catering_unit_bill_id');
    }
}
