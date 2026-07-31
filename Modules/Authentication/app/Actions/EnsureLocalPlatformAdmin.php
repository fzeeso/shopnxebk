<?php

declare(strict_types=1);

namespace Modules\Authentication\Actions;

use DomainException;
use Modules\Authentication\Enums\AccessScope;
use Modules\Authentication\Models\User;
use Modules\Authentication\Services\ScopedRoleAssignmentService;

final readonly class EnsureLocalPlatformAdmin
{
    public function __construct(private ScopedRoleAssignmentService $roleAssignments) {}

    public function ensure(string $name, string $email, string $password): User
    {
        if (! app()->environment('local')) {
            throw new DomainException('Local development accounts may only be created in the local environment.');
        }

        $user = User::query()->firstOrNew(['email' => $email]);
        if ($user->exists && ! $user->isPlatformUser()) {
            throw new DomainException('The local Platform administrator email already belongs to a Store account.');
        }

        $user->fill([
            'name' => $name,
            'password' => $password,
            'scope' => AccessScope::Platform,
        ]);
        $user->forceFill(['email_verified_at' => now()]);
        $user->save();

        $this->roleAssignments->syncPlatformRoles($user, $this->roleAssignments->platformRoleNames());

        return $user->refresh();
    }
}
