<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Authentication\Models\User;
use Modules\Stores\Enums\MembershipStatus;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StoreMembership;

final class StoreMembershipFactory extends Factory
{
    protected $model = StoreMembership::class;

    public function definition(): array
    {
        return ['store_id' => Store::factory(), 'user_id' => User::factory(), 'status' => MembershipStatus::Active, 'joined_at' => now()];
    }
}
