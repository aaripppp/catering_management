<?php

namespace App\Models;

use App\Enums\SchoolLevel;
use Database\Factories\SchoolClassFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'level', 'is_active'])]
class SchoolClass extends Model
{
    /** @use HasFactory<SchoolClassFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function jenjang(): Attribute
    {
        return Attribute::get(
            fn (): ?string => SchoolLevel::tryFrom((string) $this->level)?->educationLevel(),
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
     * @param  Builder<SchoolClass>  $query
     * @return Builder<SchoolClass>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<SchoolClass>  $query
     * @return Builder<SchoolClass>
     */
    public function scopeForJenjang(Builder $query, string $jenjang): Builder
    {
        $levels = SchoolLevel::valuesForEducationLevel($jenjang);

        return $levels === []
            ? $query->whereRaw('1 = 0')
            : $query->whereIn($this->qualifyColumn('level'), $levels);
    }

    /**
     * @param  Builder<SchoolClass>  $query
     * @return Builder<SchoolClass>
     */
    public function scopeOrderedForSelection(Builder $query): Builder
    {
        $levelColumn = $this->qualifyColumn('level');
        $nameColumn = $this->qualifyColumn('name');
        $idColumn = $this->qualifyColumn('id');
        $levelOrder = [];
        $bindings = [];

        foreach (SchoolLevel::cases() as $position => $level) {
            $levelOrder[] = 'WHEN ? THEN ?';
            $bindings[] = $level->value;
            $bindings[] = $position;
        }

        return $query
            ->orderByRaw(
                'CASE '.$levelColumn.' '.implode(' ', $levelOrder).' ELSE ? END',
                [...$bindings, count(SchoolLevel::cases())],
            )
            ->orderByRaw(
                'CASE WHEN '.$levelColumn.' = ? AND UPPER('.$nameColumn.') LIKE ? THEN 0 '
                .'WHEN '.$levelColumn.' = ? AND UPPER('.$nameColumn.') LIKE ? THEN 1 ELSE 0 END',
                ['12', '%IPA%', '12', '%IPS%'],
            )
            ->orderByRaw('LENGTH('.$nameColumn.')')
            ->orderBy($nameColumn)
            ->orderBy($idColumn);
    }
}
