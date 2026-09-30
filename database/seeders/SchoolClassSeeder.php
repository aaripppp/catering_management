<?php

namespace Database\Seeders;

use App\Models\SchoolClass;
use Illuminate\Database\Seeder;

class SchoolClassSeeder extends Seeder
{
    public function run(): void
    {
        $classes = [];

        // KB
        for ($i = 1; $i <= 5; $i++) {
            $classes[] = [
                'name' => "KB-{$i}",
                'level' => 'KB',
                'is_active' => true,
            ];
        }

        // TK A
        for ($i = 1; $i <= 5; $i++) {
            $classes[] = [
                'name' => "A-{$i}",
                'level' => 'TK A',
                'is_active' => true,
            ];
        }

        // TK B
        for ($i = 1; $i <= 5; $i++) {
            $classes[] = [
                'name' => "B-{$i}",
                'level' => 'TK B',
                'is_active' => true,
            ];
        }

        // Kelas 1 dan 2
        // Contoh:
        // 1A-1, 1A-2, 1B-1, 1B-2 ... 1E-2
        // 2A-1, 2A-2 ... 2E-2
        foreach ([1, 2] as $grade) {
            foreach (range('A', 'E') as $letter) {
                for ($group = 1; $group <= 2; $group++) {
                    $classes[] = [
                        'name' => "{$grade}{$letter}-{$group}",
                        'level' => (string) $grade,
                        'is_active' => true,
                    ];
                }
            }
        }

        // Kelas 3 sampai 6
        // 3A ... 3E
        // ...
        // 6A ... 6E
        for ($grade = 3; $grade <= 6; $grade++) {
            foreach (range('A', 'E') as $letter) {
                $classes[] = [
                    'name' => "{$grade}{$letter}",
                    'level' => (string) $grade,
                    'is_active' => true,
                ];
            }
        }

        // SMP
        // 7A ... 7E
        // 8A ... 8E
        // 9A ... 9E
        for ($grade = 7; $grade <= 9; $grade++) {
            foreach (range('A', 'E') as $letter) {
                $classes[] = [
                    'name' => "{$grade}{$letter}",
                    'level' => (string) $grade,
                    'is_active' => true,
                ];
            }
        }

        // SMA kelas 10
        foreach (['A', 'B'] as $letter) {
            $classes[] = [
                'name' => "10{$letter}",
                'level' => '10',
                'is_active' => true,
            ];
        }

        // SMA kelas 11
        foreach (['A', 'B'] as $letter) {
            $classes[] = [
                'name' => "11{$letter}",
                'level' => '11',
                'is_active' => true,
            ];
        }

        // SMA kelas 12
        foreach (['12A-IPA', '12A-IPS'] as $name) {
            $classes[] = [
                'name' => $name,
                'level' => '12',
                'is_active' => true,
            ];
        }

        foreach ($classes as $class) {
            SchoolClass::updateOrCreate(
                ['name' => $class['name']],
                [
                    'level' => $class['level'],
                    'is_active' => $class['is_active'],
                ]
            );
        }
    }
}