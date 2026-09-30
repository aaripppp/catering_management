<?php

namespace App\Enums;

enum CateringParticipantGroup: string
{
    case Student = 'student';
    case Employee = 'employee';

    /**
     * Get the human readable label for the participant group.
     */
    public function label(): string
    {
        return match ($this) {
            self::Student => 'Siswa',
            self::Employee => 'Pegawai',
        };
    }

    /**
     * Whether participants in this group are organised by jenjang and class.
     */
    public function requiresClassSelection(): bool
    {
        return match ($this) {
            self::Student => true,
            self::Employee => false,
        };
    }

    /**
     * Get every participant group as label => value options.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $group): array => [$group->value => $group->label()])
            ->all();
    }
}
