<?php

namespace App\Services;

use App\Models\CateringSetting;

/**
 * Stores the simple, global catering reference settings.
 *
 * The Budaya Makan price is reference-only: nothing here reads attendance,
 * creates manual income, or touches billing, invoices, payments or credits.
 */
class CateringCultureMealSettingService
{
    public const CULTURE_MEAL_PRICE_KEY = 'culture_meal_price';

    public const DEFAULT_CULTURE_MEAL_PRICE = 16000;

    public function getCultureMealPrice(): int
    {
        $value = CateringSetting::query()
            ->where('key', self::CULTURE_MEAL_PRICE_KEY)
            ->value('value');

        return $value === null ? self::DEFAULT_CULTURE_MEAL_PRICE : (int) $value;
    }

    public function updateCultureMealPrice(int $amount): void
    {
        CateringSetting::query()->updateOrCreate(
            ['key' => self::CULTURE_MEAL_PRICE_KEY],
            ['value' => (string) $amount],
        );
    }
}
