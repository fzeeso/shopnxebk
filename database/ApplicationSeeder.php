<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Authentication\Actions\EnsureAuthorizationCatalog;
use Modules\Authentication\Actions\EnsureLocalPlatformAdmin;
use Modules\Billing\Actions\EnsurePlanCatalog;
use Modules\Catalog\Actions\EnsureFulfillmentTypeCatalog;
use Modules\Settings\Actions\EnsureCurrencyCatalog;
use Modules\Settings\Actions\EnsureLanguageCatalog;
use Modules\Stores\Actions\EnsureLocalMerchant;
use Modules\Stores\Actions\EnsurePolicyTypeCatalog;
use Modules\Stores\Actions\EnsureStoreLanguageDefaults;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app(EnsureAuthorizationCatalog::class)->ensure();
        app(EnsureCurrencyCatalog::class)->ensure();
        app(EnsureLanguageCatalog::class)->ensure();
        app(EnsureFulfillmentTypeCatalog::class)->ensure();
        app(EnsurePolicyTypeCatalog::class)->ensure();
        app(EnsureStoreLanguageDefaults::class)->ensure();
        app(EnsurePlanCatalog::class)->ensure();

        if (! app()->environment('local')) {
            return;
        }

        $adminEmail = env('PLATFORM_ADMIN_EMAIL');
        $adminPassword = env('PLATFORM_ADMIN_PASSWORD');
        if (is_string($adminEmail) && $adminEmail !== '' && is_string($adminPassword) && $adminPassword !== '') {
            app(EnsureLocalPlatformAdmin::class)->ensure(
                (string) env('PLATFORM_ADMIN_NAME', 'Platform Test Administrator'),
                $adminEmail,
                $adminPassword,
            );
        }

        $merchantEmail = env('LOCAL_MERCHANT_EMAIL');
        $merchantPassword = env('LOCAL_MERCHANT_PASSWORD');
        $storeName = env('LOCAL_MERCHANT_STORE_NAME');
        $storeSlug = env('LOCAL_MERCHANT_STORE_SLUG');
        if (is_string($merchantEmail) && $merchantEmail !== ''
            && is_string($merchantPassword) && $merchantPassword !== ''
            && is_string($storeName) && $storeName !== ''
            && is_string($storeSlug) && $storeSlug !== '') {
            app(EnsureLocalMerchant::class)->ensure(
                (string) env('LOCAL_MERCHANT_NAME', 'Merchant Test User'),
                $merchantEmail,
                $merchantPassword,
                $storeName,
                $storeSlug,
            );
        }
    }
}
