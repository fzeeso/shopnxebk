<?php

declare(strict_types=1);

namespace Modules\Stores\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StoreDomain;

final class StoreDomainManager
{
    public function initialize(Store $store, ?string $customDomain): void
    {
        $platformDomain = $this->platformDomain($store->slug);
        $customDomain = $this->normalizeOptionalDomain($customDomain);
        $customDomain = $customDomain === $platformDomain ? null : $customDomain;

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

        $store->forceFill([
            'primary_domain' => $customDomain ?? $platformDomain,
        ])->save();
    }

    /** @param array<string, mixed> $data */
    public function create(Store $store, array $data): StoreDomain
    {
        return DB::transaction(function () use ($data, $store): StoreDomain {
            $isPrimary = ! $store->domains()->exists()
                || (bool) ($data['is_primary'] ?? false);

            if ($isPrimary) {
                $store->domains()->where('is_primary', true)->update(['is_primary' => false]);
            }

            $domain = $store->domains()->create([
                'domain' => $data['domain'],
                'domain_type' => $data['domain_type'] ?? 'custom',
                'is_primary' => $isPrimary,
                'status' => $data['status'] ?? 'pending',
                'ssl_status' => $data['ssl_status'] ?? 'pending',
                'verified_at' => (bool) ($data['is_verified'] ?? false) ? now() : null,
            ]);

            if ($isPrimary) {
                $this->updateStorePrimaryDomain($store, $domain->domain);
            }

            return $domain->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Store $store, StoreDomain $domain, array $data): StoreDomain
    {
        return DB::transaction(function () use ($data, $domain, $store): StoreDomain {
            $willBePrimary = array_key_exists('is_primary', $data)
                ? (bool) $data['is_primary']
                : $domain->is_primary;

            if ($domain->is_primary && ! $willBePrimary && ! $store->domains()->whereKeyNot($domain->getKey())->where('is_primary', true)->exists()) {
                throw ValidationException::withMessages([
                    'is_primary' => ['Select another primary domain before removing this one.'],
                ]);
            }

            if ($willBePrimary) {
                $store->domains()
                    ->whereKeyNot($domain->getKey())
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }

            $domain->fill(Arr::only($data, [
                'domain',
                'domain_type',
                'status',
                'ssl_status',
            ]));
            $domain->is_primary = $willBePrimary;

            if (array_key_exists('is_verified', $data)) {
                $domain->verified_at = (bool) $data['is_verified'] ? now() : null;
            }

            $domain->save();

            if ($willBePrimary) {
                $this->updateStorePrimaryDomain($store, $domain->domain);
            }

            return $domain->refresh();
        });
    }

    public function syncPrimaryDomain(Store $store, ?string $primaryDomain): void
    {
        DB::transaction(function () use ($primaryDomain, $store): void {
            $primaryDomain = $this->normalizeOptionalDomain($primaryDomain);
            $store->domains()->where('is_primary', true)->update(['is_primary' => false]);

            if ($primaryDomain === null) {
                $this->updateStorePrimaryDomain($store, null);

                return;
            }

            $domain = $store->domains()->where('domain', $primaryDomain)->first();
            if (! $domain instanceof StoreDomain) {
                $domain = $store->domains()->create([
                    'domain' => $primaryDomain,
                    'domain_type' => $this->isPlatformDomain($primaryDomain) ? 'platform' : 'custom',
                    'is_primary' => false,
                    'status' => 'pending',
                    'ssl_status' => 'pending',
                ]);
            }

            $domain->forceFill(['is_primary' => true])->save();
            $this->updateStorePrimaryDomain($store, $domain->domain);
        });
    }

    private function platformDomain(string $slug): string
    {
        $rootDomain = $this->platformRootDomain();

        return str_replace('_', '-', Str::lower($slug)).'.'.$rootDomain;
    }

    private function platformRootDomain(): string
    {
        $rootDomain = Str::lower(trim((string) config('stores.platform_domain', 'shopnxe.com'), " .\t\n\r\0\x0B"));
        if (preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $rootDomain) !== 1) {
            throw new \LogicException('The Store platform domain configuration is invalid.');
        }

        return $rootDomain;
    }

    private function isPlatformDomain(string $domain): bool
    {
        return Str::endsWith($domain, '.'.$this->platformRootDomain());
    }

    private function normalizeOptionalDomain(?string $domain): ?string
    {
        $domain = Str::lower(trim((string) $domain, " .\t\n\r\0\x0B"));

        return $domain === '' ? null : $domain;
    }

    private function updateStorePrimaryDomain(Store $store, ?string $domain): void
    {
        $store->forceFill(['primary_domain' => $domain])->save();
    }
}
