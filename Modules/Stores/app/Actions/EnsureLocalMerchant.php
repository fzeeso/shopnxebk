<?php

declare(strict_types=1);

namespace Modules\Stores\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Authentication\Enums\AccessScope;
use Modules\Authentication\Models\User;
use Modules\Authentication\Services\ScopedRoleAssignmentService;
use Modules\Stores\Enums\MembershipStatus;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StoreMembership;

final readonly class EnsureLocalMerchant
{
    public function __construct(
        private ProvisionStore $storeProvisioner,
        private ScopedRoleAssignmentService $roleAssignments,
    ) {}

    public function ensure(string $name, string $email, string $password, string $storeName, string $storeSlug): User
    {
        if (! app()->environment('local')) {
            throw new DomainException('Local development accounts may only be created in the local environment.');
        }

        return DB::transaction(function () use ($name, $email, $password, $storeName, $storeSlug): User {
            $user = User::query()->firstOrNew(['email' => $email]);
            if ($user->exists && ! $user->isStoreUser()) {
                throw new DomainException('The local merchant email already belongs to a Platform account.');
            }

            $user->fill([
                'name' => $name,
                'password' => $password,
                'scope' => AccessScope::Store,
            ]);
            $user->forceFill(['email_verified_at' => now()]);
            $user->save();

            $store = Store::query()->where('slug', $storeSlug)->first();
            if ($store === null) {
                $store = $this->storeProvisioner->provision($user, $storeName, $storeSlug);
            } else {
                $membership = StoreMembership::query()->firstOrNew([
                    'store_id' => $store->getKey(),
                    'user_id' => $user->getKey(),
                ]);
                $membership->fill([
                    'status' => MembershipStatus::Active,
                    'joined_at' => $membership->joined_at ?? now(),
                ])->save();
            }

            $this->roleAssignments->syncStoreRoles($user, $store, $this->roleAssignments->storeRoleNames($store));

            return $user->refresh();
        });
    }
}
