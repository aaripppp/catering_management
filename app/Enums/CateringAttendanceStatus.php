<?php

namespace App\Enums;

enum CateringAttendanceStatus: string
{
    case Ikut = 'ikut';
    case Sakit = 'sakit';
    case Izin = 'izin';
    case Alfa = 'alfa';
    case TidakIkut = 'tidak_ikut';
    case Ujian = 'ujian';
    case EventUnit = 'event_unit';
    case Puasa = 'puasa';
    case Libur = 'libur';

    public function label(): string
    {
        return match ($this) {
            self::Ikut => 'Aktif',
            self::Sakit => 'Sakit',
            self::Izin => 'Izin',
            self::Alfa => 'Budaya Makan',
            self::TidakIkut => 'Off',
            self::Ujian => 'Ujian',
            self::EventUnit => 'Event Unit',
            self::Puasa => 'Puasa',
            self::Libur => 'Libur',
        };
    }

    public function shorthand(): string
    {
        return match ($this) {
            self::Ikut => '✓',
            self::Sakit => 'S',
            self::Izin => 'I',
            self::Alfa => 'B',
            self::TidakIkut => 'O',
            self::Ujian => 'U',
            self::EventUnit => 'E',
            self::Puasa => 'P',
            self::Libur => 'L',
        };
    }
}
