<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use Modules\Authentication\Models\User;
use Modules\Stores\Models\Store;
use Modules\Stores\Services\StoreAccessService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class CatalogAccessService
{
    public function __construct(private StoreAccessService $stores) {}

    public function ensureCanView(User $user, Store $store): void
    {
        $this->stores->ensureCanView($user, $store);
    }

    public function ensureCanManageProducts(User $user, Store $store): void
    {
        $this->stores->ensureCanView($user, $store);

        $previousStoreId = getPermissionsTeamId();
        setPermissionsTeamId($store->getKey());
        try {
            if (! $user->can('manage products')) {
                throw new AccessDeniedHttpException('The manage products permission is required.');
            }
        } finally {
            setPermissionsTeamId($previousStoreId);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }
}
