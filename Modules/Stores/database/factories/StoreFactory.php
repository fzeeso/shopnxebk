<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Stores\Enums\BusinessType;
use Modules\Stores\Enums\StoreStatus;
use Modules\Stores\Models\Store;

final class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'legal_name' => $name,
            'description' => fake()->sentence(),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
            'status' => StoreStatus::Active,
            'industry' => 'retail',
            'business_type' => BusinessType::Ecommerce,
            'currency_code' => 'USD',
            'language_code' => 'en',
            'timezone' => 'UTC',
            'country_code' => 'US',
            'is_verified' => false,
            'is_ai_enabled' => false,
            'is_pos_enabled' => false,
            'is_b2b_enabled' => false,
            'is_marketplace_enabled' => false,
            'settings' => [],
            'metadata' => [],
        ];
    }
}
