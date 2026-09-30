<?php

namespace App\Enums;

/**
 * How an employee participant is grouped for catering recap purposes.
 *
 * This is operational grouping only. It is not a wali kelas authorization and
 * it never replaces the student oriented `catering_members.school_class_id`.
 */
enum CateringEmployeeAssignmentType: string
{
    /**
     * `class` is a reserved keyword in PHP, so the case is named SchoolClass
     * while the backed value stays the plain "class" the database stores.
     */
    case SchoolClass = 'class';

    case Level = 'level';

    case EducationLevel = 'education_level';

    case General = 'general';

    /**
     * Get the human readable label for the assignment type.
     */
    public function label(): string
    {
        return match ($this) {
            self::SchoolClass => 'Kelas',
            self::Level => 'Tingkat',
            self::EducationLevel => 'Jenjang',
            self::General => 'Umum',
        };
    }

    /**
     * Whether this assignment type stores a target on the school class relation.
     */
    public function targetsSchoolClass(): bool
    {
        return $this === self::SchoolClass;
    }

    /**
     * Whether this assignment type stores a target on the level column.
     */
    public function targetsLevel(): bool
    {
        return $this === self::Level;
    }

    /**
     * Whether this assignment type stores a target on the education level column.
     */
    public function targetsEducationLevel(): bool
    {
        return $this === self::EducationLevel;
    }

    /**
     * Get every assignment type as label => value options.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }
}
