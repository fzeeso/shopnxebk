<?php

declare(strict_types=1);

namespace Modules\Authentication\Actions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Authentication\Data\IssuedToken;
use Modules\Authentication\Models\PersonalAccessToken;
use Modules\Authentication\Models\User;
use Modules\Tenancy\Enums\MembershipStatus;
use Modules\Tenancy\Models\TenantMembership;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class IssueTenantToken
{
    /** @param array{email: string, password: string, device_name: string, tenant_id: string} $data */
    public function forCredentials(array $data, string $ip, ?string $userAgent): IssuedToken
    {
        $user = User::query()->where('email', Str::lower($data['email']))->first();
        if ($user === null || ! Hash::check($data['password'], $user->password)) {
            throw new AuthenticationException('Invalid credentials.');
        }

        return $this->issue($user, $data['device_name'], $data['tenant_id'], ['tenant:access'], null, ['ip' => $ip, 'user_agent' => $userAgent]);
    }

    /** @param list<string> $abilities @param array<string, mixed> $metadata */
    public function issue(User $user, string $name, ?string $tenantId, array $abilities, ?string $expiresAt, array $metadata = []): IssuedToken
    {
        if ($tenantId !== null && ! TenantMembership::query()->where('tenant_id', $tenantId)->where('user_id', $user->getKey())->where('status', MembershipStatus::Active->value)->exists()) {
            throw new AccessDeniedHttpException('Active tenant membership is required.');
        }

        $expires = $expiresAt === null ? null : now()->parse($expiresAt);
        $newToken = $user->createToken($name, $abilities, $expires);
        /** @var PersonalAccessToken $token */
        $token = $newToken->accessToken;
        $token->forceFill(['tenant_id' => $tenantId, 'metadata' => $metadata])->save();

        return new IssuedToken($newToken->plainTextToken, $token);
    }
}
