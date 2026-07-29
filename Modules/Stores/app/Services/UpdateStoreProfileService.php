<?php

declare(strict_types=1);

namespace Modules\Stores\Services;

use Illuminate\Support\Facades\DB;
use Modules\Authentication\Models\User;
use Modules\Stores\Models\Store;

final readonly class UpdateStoreProfileService
{
    public function __construct(private StoreAccessService $access) {}

    /** @param array<string, mixed> $profile */
    public function update(User $user, Store $store, array $profile): Store
    {
        $this->access->ensureCanManage($user, $store);

        return DB::transaction(function () use ($store, $profile): Store {
            $store->fill($profile)->save();

            return $store->refresh();
        });
    }
}
