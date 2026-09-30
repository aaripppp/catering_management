<?php

namespace Database\Seeders;

use App\Enums\CateringParticipantGroup;
use App\Models\CateringCategory;
use Illuminate\Database\Seeder;

class CateringCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Siswa Umum', 'price_per_day' => 17000, 'participant_group' => CateringParticipantGroup::Student, 'description' => 'Siswa reguler yang tidak memiliki kategori khusus.'],
            ['name' => 'Anak Guru', 'price_per_day' => 12000, 'participant_group' => CateringParticipantGroup::Student, 'description' => 'Anak dari guru/tenaga pendidik sekolah.'],
            ['name' => 'Guru', 'price_per_day' => 20000, 'participant_group' => CateringParticipantGroup::Employee, 'description' => 'Guru dan tenaga pendidik.'],
            ['name' => 'TU', 'price_per_day' => 15000, 'participant_group' => CateringParticipantGroup::Employee, 'description' => 'Tenaga administrasi sekolah (TU).'],
            ['name' => 'Yayasan', 'price_per_day' => 18000, 'participant_group' => CateringParticipantGroup::Employee, 'description' => 'Personel yayasan pengelola sekolah.'],
            ['name' => 'SDM', 'price_per_day' => 16000, 'participant_group' => CateringParticipantGroup::Employee, 'description' => 'Sumber Daya Manusia / Staff non-guru.'],
            ['name' => 'TK', 'price_per_day' => 14000, 'participant_group' => CateringParticipantGroup::Student, 'description' => 'Siswa Taman Kanak-Kanak.'],
            ['name' => 'Daycare', 'price_per_day' => 13000, 'participant_group' => CateringParticipantGroup::Student, 'description' => 'Anak usia daycare/playgroup.'],
        ];

        foreach ($categories as $category) {
            CateringCategory::query()->firstOrCreate(
                ['name' => $category['name']],
                [
                    'price_per_day' => $category['price_per_day'],
                    'participant_group' => $category['participant_group'],
                    'description' => $category['description'],
                    'is_active' => true,
                ],
            );
        }
    }
}
