<?php

namespace Database\Seeders;

use App\Models\CateringSetting;
use App\Services\CateringCultureMealSettingService;
use Illuminate\Database\Seeder;

class CateringSettingSeeder extends Seeder
{
    /**
     * Seed the default reference settings without overwriting edited values.
     */
    public function run(): void
    {
        CateringSetting::query()->firstOrCreate(
            ['key' => CateringCultureMealSettingService::CULTURE_MEAL_PRICE_KEY],
            ['value' => (string) CateringCultureMealSettingService::DEFAULT_CULTURE_MEAL_PRICE],
        );
    }
}
