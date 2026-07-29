<?php

declare(strict_types=1);

namespace Modules\Stores\Actions;

use Modules\Stores\Models\Currency;

final class EnsureCurrencyCatalog
{
    /** @var list<array{name: string, code: string, symbol: string, symbol_position: string, decimal_places: int}> */
    private const CURRENCIES = [
        ['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'symbol_position' => 'before', 'decimal_places' => 2],
        ['name' => 'Euro', 'code' => 'EUR', 'symbol' => '€', 'symbol_position' => 'before', 'decimal_places' => 2],
        ['name' => 'Pound Sterling', 'code' => 'GBP', 'symbol' => '£', 'symbol_position' => 'before', 'decimal_places' => 2],
        ['name' => 'UAE Dirham', 'code' => 'AED', 'symbol' => 'د.إ', 'symbol_position' => 'after', 'decimal_places' => 2],
        ['name' => 'Saudi Riyal', 'code' => 'SAR', 'symbol' => 'ر.س', 'symbol_position' => 'after', 'decimal_places' => 2],
        ['name' => 'Pakistani Rupee', 'code' => 'PKR', 'symbol' => '₨', 'symbol_position' => 'before', 'decimal_places' => 2],
        ['name' => 'Indian Rupee', 'code' => 'INR', 'symbol' => '₹', 'symbol_position' => 'before', 'decimal_places' => 2],
        ['name' => 'Chinese Yuan', 'code' => 'CNY', 'symbol' => '¥', 'symbol_position' => 'before', 'decimal_places' => 2],
        ['name' => 'Japanese Yen', 'code' => 'JPY', 'symbol' => '¥', 'symbol_position' => 'before', 'decimal_places' => 0],
        ['name' => 'South Korean Won', 'code' => 'KRW', 'symbol' => '₩', 'symbol_position' => 'before', 'decimal_places' => 0],
        ['name' => 'Thai Baht', 'code' => 'THB', 'symbol' => '฿', 'symbol_position' => 'before', 'decimal_places' => 2],
        ['name' => 'Turkish Lira', 'code' => 'TRY', 'symbol' => '₺', 'symbol_position' => 'before', 'decimal_places' => 2],
        ['name' => 'Canadian Dollar', 'code' => 'CAD', 'symbol' => 'C$', 'symbol_position' => 'before', 'decimal_places' => 2],
        ['name' => 'Australian Dollar', 'code' => 'AUD', 'symbol' => 'A$', 'symbol_position' => 'before', 'decimal_places' => 2],
        ['name' => 'New Zealand Dollar', 'code' => 'NZD', 'symbol' => 'NZ$', 'symbol_position' => 'before', 'decimal_places' => 2],
        ['name' => 'Swiss Franc', 'code' => 'CHF', 'symbol' => 'CHF', 'symbol_position' => 'before', 'decimal_places' => 2],
        ['name' => 'Swedish Krona', 'code' => 'SEK', 'symbol' => 'kr', 'symbol_position' => 'after', 'decimal_places' => 2],
        ['name' => 'Norwegian Krone', 'code' => 'NOK', 'symbol' => 'kr', 'symbol_position' => 'after', 'decimal_places' => 2],
        ['name' => 'Danish Krone', 'code' => 'DKK', 'symbol' => 'kr', 'symbol_position' => 'after', 'decimal_places' => 2],
        ['name' => 'Polish Zloty', 'code' => 'PLN', 'symbol' => 'zł', 'symbol_position' => 'after', 'decimal_places' => 2],
        ['name' => 'Czech Koruna', 'code' => 'CZK', 'symbol' => 'Kč', 'symbol_position' => 'after', 'decimal_places' => 2],
        ['name' => 'Brazilian Real', 'code' => 'BRL', 'symbol' => 'R$', 'symbol_position' => 'before', 'decimal_places' => 2],
        ['name' => 'Singapore Dollar', 'code' => 'SGD', 'symbol' => 'S$', 'symbol_position' => 'before', 'decimal_places' => 2],
        ['name' => 'Hong Kong Dollar', 'code' => 'HKD', 'symbol' => 'HK$', 'symbol_position' => 'before', 'decimal_places' => 2],
        ['name' => 'South African Rand', 'code' => 'ZAR', 'symbol' => 'R', 'symbol_position' => 'before', 'decimal_places' => 2],
    ];

    public function ensure(): void
    {
        foreach (self::CURRENCIES as $attributes) {
            $currency = Currency::query()->firstOrCreate(
                ['code' => $attributes['code']],
                [
                    ...$attributes,
                    'usd_exchange_rate' => $attributes['code'] === 'USD' ? 1 : null,
                    'is_base' => $attributes['code'] === 'USD',
                    'is_active' => true,
                    'exchange_rate_updated_at' => $attributes['code'] === 'USD' ? now() : null,
                ],
            );

            if ($currency->code === 'USD') {
                $currency->forceFill([
                    'usd_exchange_rate' => 1,
                    'is_base' => true,
                    'is_active' => true,
                    'exchange_rate_updated_at' => $currency->exchange_rate_updated_at ?? now(),
                ])->save();
            }
        }
    }
}
