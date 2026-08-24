<?php

declare(strict_types=1);

namespace App\Services\Media;

use Modules\Authentication\Models\User;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Models\Store;
use Modules\Stores\Services\StoreAccessService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class MediaAccessService
{
    public function __construct(
        private StoreContext $context,
        private StoreAccessService $stores,
    ) {}

    public function view(User $user): Store
    {
        $store = $this->context->require();
        $this->stores->ensureCanView($user, $store);

        return $store;
    }

    public function manage(User $user): Store
    {
        $store = $this->view($user);
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

        return $store;
    }
}
