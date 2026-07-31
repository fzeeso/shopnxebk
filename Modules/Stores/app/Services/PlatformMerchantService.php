<?php

declare(strict_types=1);

namespace Modules\Stores\Services;

use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Modules\Authentication\Enums\AccessScope;
use Modules\Authentication\Models\User;
use Modules\Authentication\Services\ScopedRoleAssignmentService;
use Modules\Stores\Contracts\StoreProvisioner;
use Modules\Stores\Models\Store;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class PlatformMerchantService
{
    private const PROFILE_FIELDS = [
        'legal_name',
        'description',
        'email',
        'phone',
        'primary_domain',
        'industry',
        'business_type',
        'currency_code',
        'language_code',
        'timezone',
        'country_code',
    ];

    public function __construct(
        private PlatformStoreAccessService $access,
        private StoreProvisioner $storeProvisioner,
        private ScopedRoleAssignmentService $roleAssignments,
    ) {}

    /** @return LengthAwarePaginator<int, Store> */
    public function list(User $actor, int $perPage = 25): LengthAwarePaginator
    {
        $this->access->ensureCanManageMerchants($actor);
        $stores = Store::query()->orderBy('name')->paginate($perPage);
        $stores->through(fn (Store $store): Store => $this->loadUsersAndRoles($store));

        return $stores;
    }

    /** @param array<string, mixed> $data */
    public function create(User $actor, array $data): Store
    {
        $this->access->ensureCanManageMerchants($actor);

        $store = DB::transaction(function () use ($data): Store {
            /** @var array{name: string, email: string, password: string} $ownerData */
            $ownerData = $data['owner'];
            /** @var array{name: string, slug: string} $storeData */
            $storeData = $data['store'];
            $owner = User::query()->create([
                'name' => $ownerData['name'],
                'email' => $ownerData['email'],
                'password' => $ownerData['password'],
                'scope' => AccessScope::Store,
            ]);
            $store = $this->storeProvisioner->provision($owner, $storeData['name'], $storeData['slug']);
            $profile = Arr::only($storeData, self::PROFILE_FIELDS);
            if ($profile !== []) {
                $store->fill($profile)->save();
            }

            /** @var list<string> $roles */
            $roles = array_values(array_unique(['Owner', ...($data['roles'] ?? [])]));
            $this->roleAssignments->syncStoreRoles($owner, $store, $roles);

            DB::afterCommit(function () use ($owner): void {
                event(new Registered($owner));
                $owner->sendEmailVerificationNotification();
            });

            return $store;
        });

        return $this->loadUsersAndRoles($store->refresh());
    }

    public function view(User $actor, string $publicId): Store
    {
        $this->access->ensureCanManageMerchants($actor);
        $store = $this->findMerchant($publicId);

        return $this->loadUsersAndRoles($store);
    }

    /** @param array<string, mixed> $data */
    public function update(User $actor, string $publicId, array $data): Store
    {
        $this->access->ensureCanManageMerchants($actor);
        $store = $this->loadUsersAndRoles($this->findMerchant($publicId));
        $owner = $store->users->first(fn (User $user): bool => $user->roles->contains('name', 'Owner'));
        if (! $owner instanceof User) {
            throw new NotFoundHttpException('Merchant owner not found.');
        }

        /** @var array{name: string, email: string, password?: string|null} $ownerData */
        $ownerData = $data['owner'];
        /** @var array{name: string, slug: string} $storeData */
        $storeData = $data['store'];
        $emailChanged = $owner->email !== $ownerData['email'];

        DB::transaction(function () use ($emailChanged, $owner, $ownerData, $store, $storeData): void {
            $owner->name = $ownerData['name'];
            $owner->email = $ownerData['email'];
            if ($emailChanged) {
                $owner->email_verified_at = null;
            }
            if (isset($ownerData['password']) && $ownerData['password'] !== '') {
                $owner->password = $ownerData['password'];
            }
            $owner->save();

            $store->fill([
                'name' => $storeData['name'],
                'slug' => $storeData['slug'],
                ...Arr::only($storeData, [...self::PROFILE_FIELDS, 'status']),
            ])->save();

            if ($emailChanged) {
                DB::afterCommit(function () use ($owner): void {
                    $owner->sendEmailVerificationNotification();
                });
            }
        });

        return $this->loadUsersAndRoles($store->refresh());
    }

    /** @return list<string> */
    public function roles(User $actor): array
    {
        $this->access->ensureCanManageMerchants($actor);

        return $this->roleAssignments->storeRoleNames();
    }

    private function loadUsersAndRoles(Store $store): Store
    {
        $store->load(['users' => fn ($query) => $query->orderBy('users.name')->orderBy('users.email')]);
        $previousStoreId = getPermissionsTeamId();
        setPermissionsTeamId($store->getKey());

        try {
            foreach ($store->users as $user) {
                $user->unsetRelation('roles')->load('roles');
            }
        } finally {
            setPermissionsTeamId($previousStoreId);
        }

        return $store;
    }

    private function findMerchant(string $publicId): Store
    {
        $store = Store::query()->where('public_id', $publicId)->first();
        if ($store === null) {
            throw new NotFoundHttpException('Merchant not found.');
        }

        return $store;
    }
}
