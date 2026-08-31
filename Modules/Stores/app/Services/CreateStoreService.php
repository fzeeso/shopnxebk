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

    public function authorizeCreation(User $owner): void
    {
        if (! $owner->isStoreUser()) {
            throw new AccessDeniedHttpException('Only Store-scoped accounts may create Stores.');
        }
    }

    /** @param array<string, mixed> $data */
    public function create(User $owner, array $data): Store
    {
        $this->authorizeCreation($owner);

        return DB::transaction(function () use ($owner, $data): Store {
            $preferences = isset($data['preferences']) && is_array($data['preferences'])
                ? $data['preferences']
                : [];
            $store = $this->storeProvisioner->provision($owner, (string) $data['name'], (string) $data['slug'], [
                'theme_template_key' => $data['theme_template_key'] ?? config('stores.default_theme_key', 'default'),
                'primary_domain' => $data['primary_domain'] ?? null,
                'contact_email' => $data['email'] ?? $preferences['support_email'] ?? $owner->email,
                'contact_phone' => $data['phone'] ?? null,
                'store_country_code' => $data['store_country_code'] ?? $data['country_code'] ?? null,
                'store_state' => $data['store_state'] ?? null,
                'store_city' => $data['store_city'] ?? null,
                'store_zip' => $data['store_zip'] ?? null,
                'store_address_1' => $data['store_address_1'] ?? null,
                'store_address_2' => $data['store_address_2'] ?? null,
                'preferences' => $preferences,
            ]);
            $profile = Arr::only($data, self::PROFILE_FIELDS);

            if ($preferences !== []) {
                $profile['settings'] = $preferences;
            }

            if ($profile !== []) {
                $store->fill($profile)->save();
            }

            return $store->refresh();
        });
    }
}
