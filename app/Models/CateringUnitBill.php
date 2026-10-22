<?php

namespace App\Models;

use Database\Factories\CateringUnitBillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'catering_employee_unit_id',
    'generated_by',
    'period_month',
    'period_year',
    'unit_name',
    'total_quantity',
    'price_per_person_snapshot',
    'gross_amount',
    'generated_at',
])]
class CateringUnitBill extends Model
{
    /** @use HasFactory<CateringUnitBillFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'period_month' => 'integer',
            'period_year' => 'integer',
            'total_quantity' => 'integer',
            'price_per_person_snapshot' => 'integer',
            'gross_amount' => 'integer',
            'generated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CateringEmployeeUnit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(CateringEmployeeUnit::class, 'catering_employee_unit_id');
    }

    /** @return BelongsTo<User, $this> */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /** @return HasMany<CateringUnitBillDay, $this> */
    public function days(): HasMany
    {
        return $this->hasMany(CateringUnitBillDay::class);
    }

    /** @return HasMany<CateringUnitPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(CateringUnitPayment::class);
    }
}
