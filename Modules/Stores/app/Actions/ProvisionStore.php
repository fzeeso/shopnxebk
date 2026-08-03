<?php

declare(strict_types=1);

namespace Modules\Stores\Actions;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Authentication\Models\User;
use Modules\Authentication\Services\ScopedRoleAssignmentService;
use Modules\Stores\Contracts\StoreProvisioner;
use Modules\Stores\Enums\MembershipStatus;
use Modules\Stores\Enums\StoreStatus;
use Modules\Stores\Events\StoreCreated;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StoreMembership;
use Modules\Themes\Contracts\ThemeInstaller;

final readonly class ProvisionStore implements StoreProvisioner
{
    public function __construct(
        private ScopedRoleAssignmentService $roleAssignments,
        private ThemeInstaller $themeInstaller,
    ) {}

    /**
     * @param  array{
     *     theme_template_key?: string,
     *     primary_domain?: string|null,
     *     contact_email?: string|null,
     *     contact_phone?: string|null,
     *     store_country_code?: string|null,
     *     store_state?: string|null,
     *     store_city?: string|null,
     *     store_zip?: string|null,
     *     store_address_1?: string|null,
     *     store_address_2?: string|null,
     *     preferences?: array<string, mixed>
     * }  $options
     */
    public function provision(User $owner, string $name, string $slug, array $options = []): Store
    {
        if (! $owner->isStoreUser()) {
            throw new \DomainException('Only Store-scoped users may own or provision Stores.');
        }

        $preferences = is_array($options['preferences'] ?? null) ? $options['preferences'] : [];
        $themeTemplateKey = $this->themeTemplateKey($options['theme_template_key'] ?? null);
        $platformDomain = $this->platformDomain($slug);
        $customDomain = $this->customDomain($options['primary_domain'] ?? null, $platformDomain);

        return DB::transaction(function () use ($customDomain, $name, $options, $owner, $platformDomain, $preferences, $slug, $themeTemplateKey): Store {
            $store = Store::query()->create([
                'name' => $name,
                'legal_name' => $name,
                'slug' => $slug,
                'status' => StoreStatus::Draft,
                'primary_domain' => $customDomain ?? $platformDomain,
                'settings' => $preferences,
                'metadata' => [],
            ]);

            $store->storeSettings()->create([
                'contact_email' => $options['contact_email'] ?? $preferences['support_email'] ?? $owner->email,
                'contact_phone' => $options['contact_phone'] ?? null,
                'store_country_code' => $options['store_country_code'] ?? null,
                'store_state' => $options['store_state'] ?? null,
                'store_city' => $options['store_city'] ?? null,
                'store_zip' => $options['store_zip'] ?? null,
                'store_address_1' => $options['store_address_1'] ?? null,
                'store_address_2' => $options['store_address_2'] ?? null,
                'weight_unit' => $preferences['weight_unit'] ?? 'kg',
                'storefront_enabled' => false,
                'password_enabled' => false,
                'order_number_prefix' => $preferences['order_prefix'] ?? null,
                'social_links' => [],
                'extra_settings' => Arr::except($preferences, ['support_email', 'weight_unit', 'order_prefix']),
            ]);

            $store->localeSettings()->create([
                'date_format' => $preferences['date_format'] ?? 'Y-m-d',
                'time_format' => $preferences['time_format'] ?? '24h',
                'weight_unit' => $preferences['weight_unit'] ?? 'kg',
                'dimension_unit' => $preferences['dimension_unit'] ?? 'cm',
            ]);

            $store->domains()->create([
                'domain' => $platformDomain,
                'domain_type' => 'platform',
                'is_primary' => $customDomain === null,
                'status' => 'active',
                'ssl_status' => 'pending',
                'verified_at' => now(),
            ]);

            if ($customDomain !== null) {
                $store->domains()->create([
                    'domain' => $customDomain,
                    'domain_type' => 'custom',
                    'is_primary' => true,
                    'status' => 'pending',
                    'ssl_status' => 'pending',
                ]);
            }

            $this->themeInstaller->installSelected($store, $owner, $themeTemplateKey);

            StoreMembership::query()->create([
                'store_id' => $store->getKey(),
                'user_id' => $owner->getKey(),
                'status' => MembershipStatus::Active,
                'joined_at' => now(),
            ]);
            $this->roleAssignments->assignStoreRole($owner, $store, 'Owner');

            DB::afterCommit(fn () => StoreCreated::dispatch($store->getKey(), $owner->getKey()));

            return $store->refresh()->load(['storeSettings', 'domains', 'activeTheme']);
        });
    }

    private function themeTemplateKey(mixed $value): string
    {
        $themeTemplateKey = Str::lower(trim((string) ($value ?? config('stores.default_theme_key', 'default'))));
        if (preg_match('/^[a-z0-9][a-z0-9_-]{0,119}$/D', $themeTemplateKey) !== 1) {
            throw new \InvalidArgumentException('The selected Store theme key is invalid.');
        }

        return $themeTemplateKey;
    }

    private function platformDomain(string $slug): string
    {
        $rootDomain = Str::lower(trim((string) config('stores.platform_domain', 'shopnxe.com'), " .\t\n\r\0\x0B"));
        if (preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $rootDomain) !== 1) {
            throw new \LogicException('The Store platform domain configuration is invalid.');
        }

        return str_replace('_', '-', Str::lower($slug)).'.'.$rootDomain;
    }

    private function customDomain(mixed $value, string $platformDomain): ?string
    {
        $customDomain = Str::lower(trim((string) ($value ?? ''), " .\t\n\r\0\x0B"));

        if ($customDomain !== '' && preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $customDomain) !== 1) {
            throw new \InvalidArgumentException('The selected custom Store domain is invalid.');
        }

        return $customDomain === '' || $customDomain === $platformDomain ? null : $customDomain;
    }
}
