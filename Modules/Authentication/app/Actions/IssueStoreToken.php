<?php

declare(strict_types=1);

namespace Modules\Authentication\Actions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Authentication\Data\IssuedToken;
use Modules\Authentication\Models\PersonalAccessToken;
use Modules\Authentication\Models\User;
use Modules\Stores\Enums\MembershipStatus;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StoreMembership;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class IssueStoreToken
{
    /** @param array{email: string, password: string, device_name: string, store_id: string} $data */
    public function forCredentials(array $data, string $ip, ?string $userAgent): IssuedToken
    {
        $user = $this->userForCredentials($data);

        return $this->issue($user, $data['device_name'], $data['store_id'], ['store:access'], null, ['ip' => $ip, 'user_agent' => $userAgent]);
    }

    /** @param array{email: string, password: string, device_name: string, store_id: string} $data */
    public function userForCredentials(array $data): User
    {
        $user = User::query()->where('email', Str::lower($data['email']))->first();
        if ($user === null || ! Hash::check($data['password'], $user->password)) {
            throw new AuthenticationException('Invalid credentials.');
        }

        $this->resolveActiveStore($user, $data['store_id']);

        return $user;
    }

    /** @param list<string> $abilities @param array<string, mixed> $metadata */
    public function issue(User $user, string $name, ?string $storePublicId, array $abilities, ?string $expiresAt, array $metadata = []): IssuedToken
    {
        $store = $storePublicId === null ? null : $this->resolveActiveStore($user, $storePublicId);

        $expires = $expiresAt === null ? null : now()->parse($expiresAt);
        $newToken = $user->createToken($name, $abilities, $expires);
        /** @var PersonalAccessToken $token */
        $token = $newToken->accessToken;
        $token->forceFill(['store_id' => $store?->getKey(), 'metadata' => $metadata])->save();

        return new IssuedToken($newToken->plainTextToken, $token);
    }

    private function resolveActiveStore(User $user, string $storePublicId): Store
    {
        $store = Store::query()->where('public_id', $storePublicId)->first();
        if ($store === null || ! StoreMembership::query()
            ->where('store_id', $store->getKey())
            ->where('user_id', $user->getKey())
            ->where('status', MembershipStatus::Active->value)
            ->exists()) {
            throw new AccessDeniedHttpException('Active store membership is required.');
        }

        return $store;
    }
}
