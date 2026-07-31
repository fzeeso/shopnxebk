<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Requests;

use Modules\Stores\Models\Store;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class UpdatePlatformStoreRequest extends PlatformStoreWriteRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $store = Store::query()->where('public_id', (string) $this->route('store'))->first();
        if (! $store instanceof Store) {
            throw new NotFoundHttpException('Store not found.');
        }

        return $this->storeRules(
            storeKey: $store->getKey(),
            partial: true,
        );
    }
}
