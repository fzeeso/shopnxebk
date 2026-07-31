<?php

declare(strict_types=1);

namespace Modules\Stores\Services;

use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\Authentication\Enums\AccessScope;
use Modules\Authentication\Models\User;
use Modules\Authentication\Services\ScopedRoleAssignmentService;
use Modules\Stores\Enums\MembershipStatus;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StoreMembership;

final readonly class StoreUserAdminService
{
    public function __construct(
        private StoreAccessService $access,
        private ScopedRoleAssignmentService $roleAssignments,
    ) {}

    /** @return LengthAwarePaginator<int, User> */
    public function list(User $actor, Store $store, int $perPage = 25): LengthAwarePaginator
    {
        $this->access->ensureCanManageMembers($actor, $store);

        return $this->loadUsersAndRoles($store, $perPage);
    }

    /** @param array{name: string, email: string, password: string, roles: list<string>} $data */
    public function create(User $actor, Store $store, array $data): User
    {
        $this->access->ensureCanManageMembers($actor, $store);
        $this->access->ensureCanManageRoles($actor, $store);

        $user = DB::transaction(function () use ($store, $data): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'scope' => AccessScope::Store,
            ]);
            StoreMembership::query()->create([
                'store_id' => $store->getKey(),
                'user_id' => $user->getKey(),
                'status' => MembershipStatus::Active,
                'joined_at' => now(),
            ]);
            $this->roleAssignments->syncStoreRoles($user, $store, $data['roles']);

            DB::afterCommit(function () use ($user): void {
                event(new Registered($user));
                $user->sendEmailVerificationNotification();
            });

            return $user;
        });

        return $this->loadUserAndRole($store, $user);
    }

    /** @return list<string> */
    public function roles(User $actor, Store $store): array
    {
        $this->access->ensureCanManageRoles($actor, $store);

        return $this->roleAssignments->storeRoleNames($store);
    }

    /** @return LengthAwarePaginator<int, User> */
    private function loadUsersAndRoles(Store $store, int $perPage): LengthAwarePaginator
    {
        $users = $store->users()->orderBy('users.name')->orderBy('users.email')->paginate($perPage);
        $previousStoreId = getPermissionsTeamId();
        setPermissionsTeamId($store->getKey());

        try {
            $users->getCollection()->each(fn (User $user): User => $user->load('roles'));
        } finally {
            setPermissionsTeamId($previousStoreId);
        }

        return $users;
    }

    private function loadUserAndRole(Store $store, User $user): User
    {
        $member = $store->users()->where('users.id', $user->getKey())->firstOrFail();
        $previousStoreId = getPermissionsTeamId();
        setPermissionsTeamId($store->getKey());

        try {
            $member->load('roles');
        } finally {
            setPermissionsTeamId($previousStoreId);
        }

        return $member;
    }
}
