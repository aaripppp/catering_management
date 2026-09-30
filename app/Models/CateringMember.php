<?php

namespace App\Models;

use App\Enums\CateringParticipantGroup;
use App\Enums\Gender;
use Database\Factories\CateringMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'name',
    'school_class_id',
    'catering_category_id',
    'gender',
    'phone',
    'guardian_name',
    'guardian_phone',
    'notes',
    'is_active',
])]
class CateringMember extends Model
{
    /** @use HasFactory<CateringMemberFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /**
     * @return BelongsTo<CateringCategory, $this>
     */
    public function cateringCategory(): BelongsTo
    {
        return $this->belongsTo(CateringCategory::class);
    }

    /**
     * @return HasMany<CateringAttendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(CateringAttendance::class);
    }

    /**
     * The catering operational placement of an employee participant.
     *
     * Only members whose catering category declares the employee participant
     * group use this relation. Students keep using school_class_id.
     *
     * @return HasOne<CateringEmployeeAssignment, $this>
     */
    public function employeeAssignment(): HasOne
    {
        return $this->hasOne(CateringEmployeeAssignment::class);
    }

    /**
     * @param  Builder<CateringMember>  $query
     * @return Builder<CateringMember>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Filter members by the participant group declared on their catering category.
     *
     * The category's participant_group column is the single source of truth; category
     * names and ids are never inspected so renaming a category is safe.
     *
     * @param  Builder<CateringMember>  $query
     * @return Builder<CateringMember>
     */
    public function scopeParticipantGroup(Builder $query, CateringParticipantGroup|string $group): Builder
    {
        $value = $group instanceof CateringParticipantGroup ? $group->value : $group;

        return $query->whereHas(
            'cateringCategory',
            fn (Builder $categoryQuery) => $categoryQuery->where('participant_group', $value),
        );
    }
}
