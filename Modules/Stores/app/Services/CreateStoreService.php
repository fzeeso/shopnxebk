<?php

declare(strict_types=1);

namespace Modules\Stores\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Modules\Authentication\Models\User;
use Modules\Stores\Contracts\StoreProvisioner;
use Modules\Stores\Models\Store;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class CreateStoreService
{
    private const PROFILE_FIELDS = [
        'legal_name',
        'description',
        'email',
        'phone',
        'primary_domain',
        'logo',
        'favicon',
        'cover_image',
        'industry',
        'business_type',
        'currency_code',
        'language_code',
        'timezone',
        'country_code',
    ];

    public function __construct(private StoreProvisioner $storeProvisioner) {}

    /** @param array<string, mixed> $data */
    public function create(User $owner, array $data): Store
    {
        if (! $owner->isStoreUser()) {
            throw new AccessDeniedHttpException('Only Store-scoped accounts may create Stores.');
        }

        return DB::transaction(function () use ($owner, $data): Store {
            $store = $this->storeProvisioner->provision($owner, (string) $data['name'], (string) $data['slug']);
            $profile = Arr::only($data, self::PROFILE_FIELDS);

            if (isset($data['preferences']) && is_array($data['preferences'])) {
                $profile['settings'] = $data['preferences'];
            }

            if ($profile !== []) {
                $store->fill($profile)->save();
            }

            return $store->refresh();
        });
    }
}
