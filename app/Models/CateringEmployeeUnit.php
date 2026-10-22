<?php

namespace App\Models;

use Database\Factories\CateringEmployeeUnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Number;

#[Fillable(['name', 'price_per_person', 'is_active', 'sort_order'])]
class CateringEmployeeUnit extends Model
{
    /** @use HasFactory<CateringEmployeeUnitFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'price_per_person' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected function formattedPricePerPerson(): Attribute
    {
        return Attribute::get(
            fn (): string => 'Rp '.Number::format($this->price_per_person, 0, null, 'id'),
        );
    }

    /** @param Builder<CateringEmployeeUnit> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @param Builder<CateringEmployeeUnit> $query */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<CateringUnitAttendance, $this> */
    public function unitAttendances(): HasMany
    {
        return $this->hasMany(CateringUnitAttendance::class);
    }

    /** @return HasMany<CateringUnitBill, $this> */
    public function unitBills(): HasMany
    {
        return $this->hasMany(CateringUnitBill::class);
    }
}
