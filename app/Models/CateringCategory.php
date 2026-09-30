<?php

namespace App\Models;

use App\Enums\CateringParticipantGroup;
use Database\Factories\CateringCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Number;

#[Fillable(['name', 'price_per_day', 'participant_group', 'description', 'is_active'])]
class CateringCategory extends Model
{
    /** @use HasFactory<CateringCategoryFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_per_day' => 'integer',
            'participant_group' => CateringParticipantGroup::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Format the daily price as Indonesian Rupiah, for example "Rp 17.000".
     */
    protected function formattedPricePerDay(): Attribute
    {
        return Attribute::get(
            fn (): string => 'Rp '.Number::format($this->price_per_day, 0, null, 'id'),
        );
    }

    /**
     * @return HasMany<CateringMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(CateringMember::class);
    }

    /**
     * @param  Builder<CateringCategory>  $query
     * @return Builder<CateringCategory>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
