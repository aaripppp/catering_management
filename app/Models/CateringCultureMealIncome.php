<?php

namespace App\Models;

use Database\Factories\CateringCultureMealIncomeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;

#[Fillable([
    'income_date',
    'school_class_id',
    'school_class_name',
    'amount',
    'note',
    'created_by',
])]
class CateringCultureMealIncome extends Model
{
    /** @use HasFactory<CateringCultureMealIncomeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'income_date' => 'date',
            'amount' => 'integer',
        ];
    }

    /**
     * Format the amount as Indonesian Rupiah, for example "Rp 16.000".
     */
    protected function formattedAmount(): Attribute
    {
        return Attribute::get(
            fn (): string => 'Rp '.Number::format($this->amount, 0, null, 'id'),
        );
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
