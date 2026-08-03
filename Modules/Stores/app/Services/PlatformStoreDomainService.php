<?php

declare(strict_types=1);

namespace Modules\Stores\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Authentication\Models\User;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StoreDomain;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class PlatformStoreDomainService
{
    public function __construct(
        private PlatformStoreAccessService $access,
        private StoreDomainManager $domains,
    ) {}

    /** @return Collection<int, StoreDomain> */
    public function list(User $actor, string $storePublicId): Collection
    {
        $this->access->ensureCanManageStores($actor);

        return $this->findStore($storePublicId)
            ->domains()
            ->orderByDesc('is_primary')
            ->orderBy('domain')
            ->get();
    }

    /** @param array<string, mixed> $data */
    public function create(User $actor, string $storePublicId, array $data): StoreDomain
    {
        $this->access->ensureCanManageStores($actor);

        return $this->domains->create($this->findStore($storePublicId), $data);
    }

    /** @param array<string, mixed> $data */
    public function update(
        User $actor,
        string $storePublicId,
        string $domainPublicId,
        array $data,
    ): StoreDomain {
        $this->access->ensureCanManageStores($actor);
        $store = $this->findStore($storePublicId);
        $domain = $store->domains()->where('public_id', $domainPublicId)->first();
        if (! $domain instanceof StoreDomain) {
            throw new NotFoundHttpException('Store domain not found.');
        }

        return $this->domains->update($store, $domain, $data);
    }

    private function findStore(string $publicId): Store
    {
        $store = Store::query()->where('public_id', $publicId)->first();
        if (! $store instanceof Store) {
            throw new NotFoundHttpException('Store not found.');
        }

        return $store;
    }
}
