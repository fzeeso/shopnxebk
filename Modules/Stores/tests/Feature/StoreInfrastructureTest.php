<?php

declare(strict_types=1);

namespace Modules\Stores\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Authentication\Actions\EnsureAuthorizationCatalog;
use Modules\Authentication\Models\User;
use Modules\Authentication\Services\ScopedRoleAssignmentService;
use Modules\Stores\Contracts\StoreProvisioner;
use Modules\Stores\Enums\StoreStatus;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StoreDomain;
use Modules\Stores\Models\StoreSetting;
use Modules\Stores\Models\StoreTheme;
use Tests\TestCase;

final class StoreInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_lifecycle_accepts_only_the_six_supported_statuses(): void
    {
        foreach (StoreStatus::cases() as $status) {
            $store = Store::factory()->create(['status' => $status]);

            self::assertSame($status, $store->status);
        }

        try {
            DB::transaction(fn () => DB::table('stores')
                ->where('id', $store->getKey())
                ->update(['status' => 'pending']));
            self::fail('The legacy pending status should have been rejected.');
        } catch (QueryException) {
            self::assertTrue(true);
        }
    }

    public function test_store_domain_settings_and_theme_records_use_internal_keys_and_public_ulids(): void
    {
        $store = Store::factory()->create(['status' => StoreStatus::Trial]);

        $domain = $store->domains()->create([
            'domain' => 'trial-store.example.test',
            'domain_type' => 'custom',
            'is_primary' => true,
            'status' => 'verified',
            'ssl_status' => 'issued',
            'verified_at' => now(),
        ]);
        $settings = $store->storeSettings()->create([
            'contact_email' => 'contact@example.test',
            'store_country_code' => 'US',
            'store_state' => 'California',
            'store_city' => 'San Francisco',
            'store_zip' => '94105',
            'store_address_1' => '1 Market Street',
            'store_address_2' => 'Suite 500',
            'weight_unit' => 'kg',
            'storefront_enabled' => true,
            'password_enabled' => true,
            'password_hash' => 'not-a-real-password-hash',
            'social_links' => ['instagram' => 'shopnxe'],
            'extra_settings' => ['checkout_note' => true],
        ]);
        $theme = $store->themes()->create([
            'name' => 'Default storefront',
            'template_key' => 'default',
            'is_active' => true,
            'settings' => ['accent' => '#075c3c'],
        ]);

        self::assertInstanceOf(StoreDomain::class, $domain);
        self::assertTrue(Str::isUlid($domain->public_id));
        self::assertSame($store->getKey(), $domain->store_id);
        self::assertTrue($domain->is_primary);
        self::assertNotNull($domain->verified_at);

        self::assertInstanceOf(StoreSetting::class, $settings);
        self::assertSame($store->getKey(), $settings->getKey());
        self::assertSame(['instagram' => 'shopnxe'], $settings->social_links);
        self::assertSame('US', $settings->store_country_code);
        self::assertSame('San Francisco', $settings->store_city);
        self::assertSame('1 Market Street', $settings->store_address_1);
        self::assertTrue(Hash::check('not-a-real-password-hash', $settings->password_hash));
        self::assertArrayNotHasKey('password_hash', $settings->toArray());

        self::assertInstanceOf(StoreTheme::class, $theme);
        self::assertTrue(Str::isUlid($theme->public_id));
        self::assertSame($theme->getKey(), $store->fresh()->activeTheme?->getKey());

        self::assertTrue(Schema::hasColumns('store_settings', [
            'logo_media_id',
            'favicon_media_id',
            'store_country_code',
            'store_state',
            'store_city',
            'store_zip',
            'store_address_1',
            'store_address_2',
        ]));
        self::assertTrue(Schema::hasTable('store_users'));
        self::assertFalse(Schema::hasTable('store_memberships'));
    }

    public function test_each_store_has_at_most_one_primary_domain_and_one_active_theme(): void
    {
        $store = Store::factory()->create();
        $store->domains()->create([
            'domain' => 'primary.example.test',
            'domain_type' => 'custom',
            'is_primary' => true,
            'status' => 'verified',
            'ssl_status' => 'issued',
        ]);

        try {
            DB::transaction(fn () => $store->domains()->create([
                'domain' => 'second.example.test',
                'domain_type' => 'custom',
                'is_primary' => true,
                'status' => 'pending',
                'ssl_status' => 'pending',
            ]));
            self::fail('A second primary domain should have been rejected.');
        } catch (QueryException) {
            self::assertTrue(true);
        }

        $store->themes()->create([
            'name' => 'First theme',
            'template_key' => 'first',
            'is_active' => true,
            'settings' => [],
        ]);

        try {
            DB::transaction(fn () => $store->themes()->create([
                'name' => 'Second theme',
                'template_key' => 'second',
                'is_active' => true,
                'settings' => [],
            ]));
            self::fail('A second active theme should have been rejected.');
        } catch (QueryException) {
            self::assertTrue(true);
        }
    }

    public function test_store_provisioning_rolls_back_every_step_when_domain_creation_fails(): void
    {
        config()->set('stores.platform_domain', 'stores.example.test');
        $existingStore = Store::factory()->create();
        $existingStore->domains()->create([
            'domain' => 'atomic-shop.stores.example.test',
            'domain_type' => 'platform',
            'is_primary' => true,
            'status' => 'active',
            'ssl_status' => 'pending',
        ]);
        $owner = User::factory()->create();
        $storeCount = Store::query()->count();
        $settingsCount = StoreSetting::query()->count();
        $themeCount = StoreTheme::query()->count();

        try {
            app(StoreProvisioner::class)->provision($owner, 'Atomic Shop', 'atomic-shop');
            self::fail('Provisioning should fail when the platform domain is already assigned.');
        } catch (QueryException) {
            self::assertTrue(true);
        }

        self::assertSame($storeCount, Store::query()->count());
        self::assertSame($settingsCount, StoreSetting::query()->count());
        self::assertSame($themeCount, StoreTheme::query()->count());
        self::assertDatabaseMissing('stores', ['slug' => 'atomic-shop']);
    }

    public function test_merchant_setup_endpoint_returns_a_complete_store_and_selected_dashboard_url(): void
    {
        app(EnsureAuthorizationCatalog::class)->ensure();
        config()->set('stores.platform_domain', 'stores.example.test');
        config()->set('stores.admin_dashboard_url', 'https://admin.example.test/dashboard');
        $owner = User::factory()->create();

        $response = $this->actingAs($owner, 'web')->postJson('/api/v1/stores', [
            'name' => 'Complete Store',
            'slug' => 'complete-store',
            'email' => 'contact@complete.example.test',
            'phone' => '+1-555-0100',
            'store_country_code' => 'pk',
            'store_state' => 'Sindh',
            'store_city' => 'Karachi',
            'store_zip' => '75500',
            'store_address_1' => 'Main Boulevard',
            'store_address_2' => 'Office 12',
            'primary_domain' => 'www.complete.example.test',
            'theme_template_key' => 'editorial_modern',
            'preferences' => [
                'weight_unit' => 'lb',
                'order_prefix' => 'NXE',
                'inventory_tracking' => true,
            ],
        ])->assertCreated()
            ->assertJsonPath('data.status', StoreStatus::Draft->value)
            ->assertJsonPath('data.primary_domain', 'www.complete.example.test');

        $store = Store::query()->where('slug', 'complete-store')->firstOrFail();
        $response->assertJsonPath('dashboard_url', "https://admin.example.test/dashboard?store={$store->public_id}");

        self::assertDatabaseHas('store_settings', [
            'store_id' => $store->getKey(),
            'contact_email' => 'contact@complete.example.test',
            'contact_phone' => '+1-555-0100',
            'store_country_code' => 'PK',
            'store_state' => 'Sindh',
            'store_city' => 'Karachi',
            'store_zip' => '75500',
            'store_address_1' => 'Main Boulevard',
            'store_address_2' => 'Office 12',
            'weight_unit' => 'lb',
            'storefront_enabled' => false,
            'order_number_prefix' => 'NXE',
        ]);
        self::assertDatabaseHas('store_domains', [
            'store_id' => $store->getKey(),
            'domain' => 'complete-store.stores.example.test',
            'domain_type' => 'platform',
            'is_primary' => false,
            'status' => 'active',
        ]);
        self::assertDatabaseHas('store_domains', [
            'store_id' => $store->getKey(),
            'domain' => 'www.complete.example.test',
            'domain_type' => 'custom',
            'is_primary' => true,
            'status' => 'pending',
        ]);
        self::assertDatabaseHas('store_themes', [
            'store_id' => $store->getKey(),
            'template_key' => 'editorial_modern',
            'is_active' => true,
        ]);
        self::assertSame(['inventory_tracking' => true], $store->storeSettings?->extra_settings);

        $this->actingAs($owner, 'web')
            ->withHeader('X-Store-ID', (string) $store->public_id)
            ->patchJson('/api/v1/store/settings', [
                'contact_email' => 'settings@complete.example.test',
                'store_country_code' => 'us',
                'store_state' => 'Texas',
                'store_city' => 'Austin',
                'store_zip' => '78701',
                'store_address_1' => 'Congress Avenue',
                'store_address_2' => null,
                'preferences' => [
                    'weight_unit' => 'kg',
                    'order_prefix' => 'AUS',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.contact_email', 'settings@complete.example.test')
            ->assertJsonPath('data.store_country_code', 'US')
            ->assertJsonPath('data.store_state', 'Texas')
            ->assertJsonPath('data.store_city', 'Austin')
            ->assertJsonPath('data.store_zip', '78701')
            ->assertJsonPath('data.store_address_1', 'Congress Avenue')
            ->assertJsonPath('data.store_address_2', null);

        self::assertDatabaseHas('store_settings', [
            'store_id' => $store->getKey(),
            'contact_email' => 'settings@complete.example.test',
            'store_country_code' => 'US',
            'store_state' => 'Texas',
            'store_city' => 'Austin',
            'store_zip' => '78701',
            'store_address_1' => 'Congress Avenue',
            'store_address_2' => null,
            'weight_unit' => 'kg',
            'order_number_prefix' => 'AUS',
        ]);
    }

    public function test_platform_merchant_api_creates_reads_and_updates_normalized_store_address(): void
    {
        Notification::fake();
        app(EnsureAuthorizationCatalog::class)->ensure();
        $admin = User::factory()->platform()->create();
        app(ScopedRoleAssignmentService::class)->assignPlatformRole($admin, 'Super Admin');

        $created = $this->actingAs($admin, 'web')->postJson('/api/v1/platform/merchants', [
            'owner' => [
                'name' => 'Address Merchant',
                'email' => 'address.merchant@example.test',
                'password' => 'Strong!Password123',
                'password_confirmation' => 'Strong!Password123',
            ],
            'store' => [
                'name' => 'Address Store',
                'slug' => 'address-store',
                'store_country_code' => 'pk',
                'store_state' => 'Punjab',
                'store_city' => 'Lahore',
                'store_zip' => '54000',
                'store_address_1' => 'Mall Road',
                'store_address_2' => null,
            ],
        ])->assertCreated()
            ->assertJsonPath('data.store_settings.store_country_code', 'PK')
            ->assertJsonPath('data.store_settings.store_city', 'Lahore')
            ->assertJsonPath('data.store_settings.store_address_1', 'Mall Road');

        $storeId = (string) $created->json('data.store.id');

        $this->actingAs($admin, 'web')->patchJson("/api/v1/platform/merchants/{$storeId}", [
            'owner' => [
                'name' => 'Address Merchant',
                'email' => 'address.merchant@example.test',
            ],
            'store' => [
                'name' => 'Address Store',
                'slug' => 'address-store',
                'status' => StoreStatus::Draft->value,
                'store_country_code' => 'us',
                'store_state' => 'New York',
                'store_city' => 'New York',
                'store_zip' => '10001',
                'store_address_1' => 'Fifth Avenue',
                'store_address_2' => 'Floor 2',
            ],
        ])->assertOk()
            ->assertJsonPath('data.store_settings.store_country_code', 'US')
            ->assertJsonPath('data.store_settings.store_state', 'New York')
            ->assertJsonPath('data.store_settings.store_zip', '10001')
            ->assertJsonPath('data.store_settings.store_address_2', 'Floor 2');
    }
}
