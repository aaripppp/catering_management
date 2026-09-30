<?php

namespace Database\Seeders;

use App\Enums\SchoolLevel;
use App\Models\SchoolClass;
use Illuminate\Database\Seeder;

class SchoolClassSeeder extends Seeder
{
    public function run(): void
    {
        $classes = [
            ['name' => 'TK A', 'level' => SchoolLevel::TkA->value],
            ['name' => 'TK B', 'level' => SchoolLevel::TkB->value],
            ['name' => 'Daycare A', 'level' => SchoolLevel::Daycare->value],
            ['name' => 'Daycare B', 'level' => SchoolLevel::Daycare->value],
            ['name' => 'VII A', 'level' => SchoolLevel::Grade7->value],
            ['name' => 'VII B', 'level' => SchoolLevel::Grade7->value],
            ['name' => 'VIII A', 'level' => SchoolLevel::Grade8->value],
            ['name' => 'VIII B', 'level' => SchoolLevel::Grade8->value],
            ['name' => 'IX A', 'level' => SchoolLevel::Grade9->value],
            ['name' => 'IX B', 'level' => SchoolLevel::Grade9->value],
        ];

        foreach ($classes as $class) {
            SchoolClass::query()->firstOrCreate(
                ['name' => $class['name']],
                ['level' => $class['level'], 'is_active' => true],
            );
        }
    }
}
