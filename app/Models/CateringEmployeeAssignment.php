<?php

namespace App\Models;

use App\Enums\CateringEmployeeAssignmentType;
use App\Enums\SchoolLevel;
use Database\Factories\CateringEmployeeAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Records where an employee participant belongs for catering recap purposes.
 *
 * This is operational grouping only: it is not a wali kelas authorization and
 * it does not replace `catering_members.school_class_id`, which stays reserved
 * for student participants.
 */
#[Fillable([
    'catering_member_id',
    'assignment_type',
    'school_class_id',
    'level',
    'education_level',
])]
class CateringEmployeeAssignment extends Model
{
    /** @use HasFactory<CateringEmployeeAssignmentFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assignment_type' => CateringEmployeeAssignmentType::class,
        ];
    }

    /**
     * @return BelongsTo<CateringMember, $this>
     */
    public function cateringMember(): BelongsTo
    {
        return $this->belongsTo(CateringMember::class);
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /**
     * A short human readable summary of where this employee is placed.
     */
    protected function targetLabel(): Attribute
    {
        return Attribute::get(function (): string {
            $type = $this->assignment_type;

            return match ($type) {
                CateringEmployeeAssignmentType::SchoolClass => $this->schoolClass?->name
                    ?? 'Kelas tidak tersedia',
                CateringEmployeeAssignmentType::Level => SchoolLevel::tryFrom((string) $this->level)?->label()
                    ?? (string) $this->level
                    ?: 'Tingkat tidak tersedia',
                CateringEmployeeAssignmentType::EducationLevel => (string) $this->education_level
                    ?: 'Jenjang tidak tersedia',
                CateringEmployeeAssignmentType::General => 'Umum',
                null => 'Belum ditentukan',
                default => $type->label(),
            };
        });
    }

    /**
     * Whether the assignment points at a target that is no longer resolvable.
     *
     * A school class is deleted with `nullOnDelete`, so a class typed assignment
     * can outlive its class. Recap must treat that as unplaced rather than
     * silently grouping the employee under a missing class.
     */
    protected function hasMissingTarget(): Attribute
    {
        return Attribute::get(fn (): bool => match ($this->assignment_type) {
            CateringEmployeeAssignmentType::SchoolClass => $this->school_class_id === null,
            CateringEmployeeAssignmentType::Level => $this->level === null,
            CateringEmployeeAssignmentType::EducationLevel => $this->education_level === null,
            default => false,
        });
    }

    /**
     * Restrict to a single assignment type, for grouping in PHP.
     *
     * @param  Builder<CateringEmployeeAssignment>  $query
     * @return Builder<CateringEmployeeAssignment>
     */
    public function scopeOfType(Builder $query, CateringEmployeeAssignmentType|string $type): Builder
    {
        $value = $type instanceof CateringEmployeeAssignmentType ? $type->value : $type;

        return $query->where('assignment_type', $value);
    }
}
