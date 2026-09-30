<?php

namespace Database\Seeders;

use App\Enums\Gender;
use App\Models\CateringCategory;
use App\Models\CateringMember;
use App\Models\SchoolClass;
use Illuminate\Database\Seeder;

class CateringMemberSeeder extends Seeder
{
    public function run(): void
    {
        $classes = SchoolClass::query()->pluck('id', 'name');
        $categories = CateringCategory::query()->pluck('id', 'name');

        if ($classes->isEmpty() || $categories->isEmpty()) {
            return;
        }

        $members = [
            ['name' => 'Ahmad Fauzi', 'class' => 'VII A', 'category' => 'Siswa Umum', 'gender' => Gender::Male],
            ['name' => 'Budi Santoso', 'class' => 'VII A', 'category' => 'Siswa Umum', 'gender' => Gender::Male],
            ['name' => 'Citra Dewi', 'class' => 'VII B', 'category' => 'Siswa Umum', 'gender' => Gender::Female],
            ['name' => 'Dewi Lestari', 'class' => 'VII B', 'category' => 'Anak Guru', 'gender' => Gender::Female],
            ['name' => 'Eko Prasetyo', 'class' => 'VIII A', 'category' => 'Siswa Umum', 'gender' => Gender::Male],
            ['name' => 'Fitri Handayani', 'class' => 'VIII A', 'category' => 'Siswa Umum', 'gender' => Gender::Female],
            ['name' => 'Gilang Ramadhan', 'class' => 'VIII B', 'category' => 'Siswa Umum', 'gender' => Gender::Male],
            ['name' => 'Hana Kusuma', 'class' => 'VIII B', 'category' => 'Anak Guru', 'gender' => Gender::Female],
            ['name' => 'Irfan Maulana', 'class' => 'IX A', 'category' => 'Siswa Umum', 'gender' => Gender::Male],
            ['name' => 'Julia Anggraini', 'class' => 'IX A', 'category' => 'Siswa Umum', 'gender' => Gender::Female],
            ['name' => 'Krisna Wibowo', 'class' => 'IX B', 'category' => 'Siswa Umum', 'gender' => Gender::Male],
            ['name' => 'Lestari Wulandari', 'class' => null, 'category' => 'TK', 'gender' => Gender::Female],
            ['name' => 'Bambang Wibowo', 'class' => null, 'category' => 'Guru', 'gender' => Gender::Male],
            ['name' => 'Siti Rahayu', 'class' => null, 'category' => 'TU', 'gender' => Gender::Female],
            ['name' => 'Dewi Sartika', 'class' => 'TK A', 'category' => 'TK', 'gender' => Gender::Female],
            ['name' => 'Rudi Hartono', 'class' => 'Daycare A', 'category' => 'Daycare', 'gender' => Gender::Male],
        ];

        foreach ($members as $member) {
            CateringMember::query()->firstOrCreate(
                ['name' => $member['name']],
                [
                    'school_class_id' => $member['class'] ? $classes->get($member['class']) : null,
                    'catering_category_id' => $categories->get($member['category']),
                    'gender' => $member['gender'],
                    'phone' => '08'.fake()->numerify('##########'),
                    'guardian_name' => fake()->name(),
                    'guardian_phone' => '08'.fake()->numerify('##########'),
                    'notes' => null,
                    'is_active' => true,
                ],
            );
        }
    }
}
