<?php

namespace App\Enums;

enum SchoolLevel: string
{
    case Daycare = 'Daycare';
    case Kb = 'KB';
    case TkA = 'TK A';
    case TkB = 'TK B';
    case Grade1 = '1';
    case Grade2 = '2';
    case Grade3 = '3';
    case Grade4 = '4';
    case Grade5 = '5';
    case Grade6 = '6';
    case Grade7 = '7';
    case Grade8 = '8';
    case Grade9 = '9';
    case Grade10 = '10';
    case Grade11 = '11';
    case Grade12 = '12';

    public function label(): string
    {
        return is_numeric($this->value) ? 'Kelas '.$this->value : $this->value;
    }

    public function educationLevel(): string
    {
        return match ($this) {
            self::Daycare => 'Daycare',
            self::Kb, self::TkA, self::TkB => 'TK',
            self::Grade1, self::Grade2, self::Grade3, self::Grade4, self::Grade5, self::Grade6 => 'SD',
            self::Grade7, self::Grade8, self::Grade9 => 'SMP',
            self::Grade10, self::Grade11, self::Grade12 => 'SMA',
        };
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function groupedOptions(): array
    {
        $options = [];

        foreach (self::cases() as $level) {
            $options[$level->educationLevel()][$level->value] = $level->label();
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    public static function educationLevels(): array
    {
        return array_keys(self::groupedOptions());
    }

    /**
     * @return array<int, string>
     */
    public static function valuesForEducationLevel(string $educationLevel): array
    {
        return array_keys(self::groupedOptions()[$educationLevel] ?? []);
    }
}
