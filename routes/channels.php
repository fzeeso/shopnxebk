<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Broadcast;
use Modules\Authentication\Models\PersonalAccessToken;
use Modules\Authentication\Models\User;
use Modules\Tenancy\Contracts\TenantContext;
use Modules\Tenancy\Enums\MembershipStatus;
use Modules\Tenancy\Models\TenantMembership;

Broadcast::channel('tenant.{tenantId}', function (User $user, string $tenantId): bool {
    $tenant = app(TenantContext::class)->current();
    if ($tenant === null || $tenant->getKey() !== $tenantId) {
        return false;
    }

    $token = $user->currentAccessToken();
    if ($token instanceof PersonalAccessToken && $token->tenant_id !== null && $token->tenant_id !== $tenantId) {
        return false;
    }

    return TenantMembership::query()
        ->where('tenant_id', $tenantId)
        ->where('user_id', $user->getAuthIdentifier())
        ->where('status', MembershipStatus::Active->value)
        ->exists() && $user->can('tenant.access');
});

Broadcast::channel('user.{userId}', fn (User $user, string $userId): bool => (string) $user->getAuthIdentifier() === $userId);
