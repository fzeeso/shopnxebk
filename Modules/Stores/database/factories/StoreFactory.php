<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Stores\Enums\StoreStatus;
use Modules\Stores\Models\Store;

final class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return ['name' => $name, 'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999), 'status' => StoreStatus::Active, 'settings' => [], 'metadata' => []];
    }
}
