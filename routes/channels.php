<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Broadcast;
use Modules\Authentication\Models\PersonalAccessToken;
use Modules\Authentication\Models\User;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Enums\MembershipStatus;
use Modules\Stores\Models\StoreMembership;

Broadcast::channel('store.{storeId}', function (User $user, string $storeId): bool {
    $store = app(StoreContext::class)->current();
    if ($store === null || $store->public_id !== $storeId) {
        return false;
    }

    $token = $user->currentAccessToken();
    if ($token instanceof PersonalAccessToken && $token->store_id !== null && $token->store_id !== $store->getKey()) {
        return false;
    }

    return StoreMembership::query()
        ->where('store_id', $store->getKey())
        ->where('user_id', $user->getAuthIdentifier())
        ->where('status', MembershipStatus::Active->value)
        ->exists() && $user->can('access store');
});

Broadcast::channel('user.{userId}', fn (User $user, string $userId): bool => $user->public_id === $userId);
