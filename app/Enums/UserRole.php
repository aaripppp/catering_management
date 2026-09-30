<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case WaliKelas = 'wali_kelas';

    /**
     * Get the human readable label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::WaliKelas => 'Wali Kelas',
        };
    }
}
