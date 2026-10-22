<?php

namespace Database\Seeders;

use App\Models\CateringEmployeeUnit;
use Illuminate\Database\Seeder;

class CateringEmployeeUnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $units = [
            ['name' => 'Daycare', 'price_per_person' => 13000],
            ['name' => 'TK', 'price_per_person' => 13000],
            ['name' => 'SD', 'price_per_person' => 14000],
            ['name' => 'SMP', 'price_per_person' => 13000],
            ['name' => 'SMA', 'price_per_person' => 13000],
            ['name' => 'TU', 'price_per_person' => 13000],
            ['name' => 'Annur Mart', 'price_per_person' => 13000],
            ['name' => 'Yayasan', 'price_per_person' => 13000],
            ['name' => 'SDM', 'price_per_person' => 13000],
        ];

        foreach ($units as $index => $unit) {
            CateringEmployeeUnit::query()->firstOrCreate(
                ['sort_order' => $index + 1],
                $unit + ['is_active' => true],
            );
        }
    }
}
