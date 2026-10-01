<?php

namespace App\Enums;

enum CateringAttendanceStatus: string
{
    case Ikut = 'ikut';
    case Sakit = 'sakit';
    case Izin = 'izin';
    case Alfa = 'alfa';
    case TidakIkut = 'tidak_ikut';
    case Libur = 'libur';

    public function label(): string
    {
        return match ($this) {
            self::Ikut => 'Ikut',
            self::Sakit => 'Sakit',
            self::Izin => 'Izin',
            self::Alfa => 'Alfa',
            self::TidakIkut => 'Tidak Ikut',
            self::Libur => 'Libur',
        };
    }

    public function shorthand(): string
    {
        return match ($this) {
            self::Ikut => '✓',
            self::Sakit => 'S',
            self::Izin => 'I',
            self::Alfa => 'A',
            self::TidakIkut => 'T',
            self::Libur => 'L',
        };
    }
}
