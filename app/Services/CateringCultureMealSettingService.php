<?php

namespace App\Services;

use App\Models\CateringSetting;
use InvalidArgumentException;

/**
 * Stores the simple, global catering reference settings.
 *
 * The Budaya Makan reference price is display-only. The billable price is used
 * exclusively for flagged Budaya Makan Tagihan (B+) student billing and never
 * creates manual income or touches payments and credits directly.
 */
class CateringCultureMealSettingService
{
    public const CULTURE_MEAL_PRICE_KEY = 'culture_meal_price';

    public const CULTURE_MEAL_BILLABLE_PRICE_KEY = 'culture_meal_billable_price';

    public const DEFAULT_CULTURE_MEAL_PRICE = 16000;

    public const DEFAULT_CULTURE_MEAL_BILLABLE_PRICE = 16000;

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

    /**
     * Resolve the global B+ price, falling back to the documented default when
     * the key is missing or an invalid non-positive value was stored directly.
     */
    public function getCultureMealBillablePrice(): int
    {
        $value = CateringSetting::query()
            ->where('key', self::CULTURE_MEAL_BILLABLE_PRICE_KEY)
            ->value('value');

        if ($value === null) {
            return self::DEFAULT_CULTURE_MEAL_BILLABLE_PRICE;
        }

        $amount = (int) $value;

        return $amount > 0 ? $amount : self::DEFAULT_CULTURE_MEAL_BILLABLE_PRICE;
    }

    public function updateCultureMealBillablePrice(int $amount): void
    {
        if ($amount < 1) {
            throw new InvalidArgumentException('Harga Budaya Makan Tagihan harus lebih dari nol.');
        }

        CateringSetting::query()->updateOrCreate(
            ['key' => self::CULTURE_MEAL_BILLABLE_PRICE_KEY],
            ['value' => (string) $amount],
        );
    }
}
