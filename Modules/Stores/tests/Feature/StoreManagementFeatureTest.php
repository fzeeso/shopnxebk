<?php

declare(strict_types=1);

namespace Modules\Stores\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Authentication\Actions\EnsureAuthorizationCatalog;
use Modules\Authentication\Models\User;
use Modules\Authentication\Services\ScopedRoleAssignmentService;
use Modules\Stores\Actions\EnsureLanguageCatalog;
use Modules\Stores\Enums\MembershipStatus;
use Modules\Stores\Models\Language;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StoreLanguage;
use Modules\Stores\Models\StoreMembership;
use Tests\TestCase;

final class StoreManagementFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_user_can_create_an_additional_store_and_becomes_its_owner(): void
    {
        $owner = User::factory()->create();

        $response = $this->actingAs($owner, 'web')->postJson('/api/v1/stores', [
            'name' => 'Northwind Wholesale',
            'slug' => 'northwind-wholesale',
            'legal_name' => 'Northwind Wholesale Limited',
            'email' => 'STORE@EXAMPLE.TEST',
            'business_type' => 'b2b',
            'currency_code' => 'gbp',
            'language_code' => 'en-GB',
            'timezone' => 'Europe/London',
            'country_code' => 'gb',
            'preferences' => [
                'order_prefix' => 'NW',
                'inventory_tracking' => true,
                'low_stock_threshold' => 10,
            ],
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Northwind Wholesale')
            ->assertJsonPath('data.legal_name', 'Northwind Wholesale Limited')
            ->assertJsonPath('data.email', 'store@example.test')
            ->assertJsonPath('data.currency_code', 'GBP')
            ->assertJsonPath('data.country_code', 'GB');

        $store = Store::query()->where('public_id', $response->json('data.id'))->firstOrFail();

        $this->assertDatabaseHas('store_memberships', [
            'store_id' => $store->getKey(),
            'user_id' => $owner->getKey(),
            'status' => MembershipStatus::Active->value,
        ]);

        setPermissionsTeamId($store->getKey());
        self::assertTrue($owner->fresh()->hasRole('Owner'));
        self::assertSame('NW', $store->settings['order_prefix']);
    }

    public function test_active_member_can_view_store_profile_and_settings(): void
    {
        [$user, $store] = $this->memberWithRole('Sales');
        $store->forceFill([
            'currency_code' => 'EUR',
            'settings' => ['guest_checkout' => true],
            'is_pos_enabled' => true,
        ])->save();

        $this->actingAs($user, 'web')
            ->withHeader('X-Store-ID', $store->public_id)
            ->getJson('/api/v1/store')
            ->assertOk()
            ->assertJsonPath('data.id', $store->public_id);

        $this->actingAs($user, 'web')
            ->withHeader('X-Store-ID', $store->public_id)
            ->getJson('/api/v1/store/settings')
            ->assertOk()
            ->assertJsonPath('data.store_id', $store->public_id)
            ->assertJsonPath('data.currency_code', 'EUR')
            ->assertJsonPath('data.preferences.guest_checkout', true)
            ->assertJsonPath('data.capabilities.pos', true);
    }

    public function test_manager_can_update_profile_and_merge_settings(): void
    {
        [$manager, $store] = $this->memberWithRole('Manager');
        $store->forceFill(['settings' => ['inventory_tracking' => true, 'order_prefix' => 'OLD']])->save();

        $this->actingAs($manager, 'web')
            ->withHeader('X-Store-ID', $store->public_id)
            ->patchJson('/api/v1/store/profile', [
                'name' => 'Updated Store',
                'email' => 'OWNER@EXAMPLE.TEST',
                'business_type' => 'services',
            ])->assertOk()
            ->assertJsonPath('data.name', 'Updated Store')
            ->assertJsonPath('data.email', 'owner@example.test')
            ->assertJsonPath('data.business_type', 'services');

        $this->actingAs($manager, 'web')
            ->withHeader('X-Store-ID', $store->public_id)
            ->patchJson('/api/v1/store/settings', [
                'currency_code' => 'cad',
                'timezone' => 'America/Toronto',
                'preferences' => [
                    'order_prefix' => 'NEW',
                    'guest_checkout' => false,
                ],
            ])->assertOk()
            ->assertJsonPath('data.currency_code', 'CAD')
            ->assertJsonPath('data.timezone', 'America/Toronto')
            ->assertJsonPath('data.preferences.order_prefix', 'NEW')
            ->assertJsonPath('data.preferences.inventory_tracking', true)
            ->assertJsonPath('data.preferences.guest_checkout', false);
    }

    public function test_store_staff_without_manage_permission_and_cross_store_users_are_rejected(): void
    {
        [$salesUser, $store] = $this->memberWithRole('Sales');
        $otherUser = User::factory()->create();

        $this->actingAs($salesUser, 'web')
            ->withHeader('X-Store-ID', $store->public_id)
            ->patchJson('/api/v1/store/profile', ['name' => 'Forbidden'])
            ->assertForbidden()
            ->assertJsonPath('message', 'The manage store permission is required.');

        $this->actingAs($otherUser, 'web')
            ->withHeader('X-Store-ID', $store->public_id)
            ->getJson('/api/v1/store')
            ->assertForbidden()
            ->assertJsonPath('message', 'Active store membership is required.');
    }

    public function test_platform_fields_and_platform_accounts_are_rejected(): void
    {
        [$owner, $store] = $this->memberWithRole('Owner');

        $this->actingAs($owner, 'web')
            ->withHeader('X-Store-ID', $store->public_id)
            ->patchJson('/api/v1/store/settings', [
                'status' => 'active',
                'plan_id' => 99,
                'is_ai_enabled' => true,
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'plan_id', 'is_ai_enabled']);

        $platformUser = User::factory()->platform()->create();
        $this->actingAs($platformUser, 'web')
            ->postJson('/api/v1/stores', [
                'name' => 'Forbidden Store',
                'slug' => 'forbidden-store',
            ])->assertForbidden()
            ->assertJsonPath('message', 'Store-scoped account required.');
    }

    public function test_language_catalog_seeds_supported_locales_and_backfills_store_default(): void
    {
        $store = Store::factory()->create(['language_code' => 'pt_BR']);

        app(EnsureLanguageCatalog::class)->ensure();

        self::assertSame(21, Language::query()->count());
        $this->assertDatabaseHas('languages', [
            'name' => 'Arabic',
            'native_name' => 'العربية',
            'locale' => 'ar',
            'direction' => 'rtl',
            'is_active' => true,
        ]);

        $portuguese = Language::query()->where('locale', 'pt_BR')->firstOrFail();
        $this->assertDatabaseHas('store_languages', [
            'store_id' => $store->getKey(),
            'language_id' => $portuguese->getKey(),
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    public function test_super_admin_manages_language_catalog_but_support_cannot_add(): void
    {
        app(EnsureAuthorizationCatalog::class)->ensure();
        app(EnsureLanguageCatalog::class)->ensure();

        $superAdmin = User::factory()->platform()->create();
        app(ScopedRoleAssignmentService::class)->assignPlatformRole($superAdmin, 'Super Admin');

        $this->actingAs($superAdmin, 'web')
            ->getJson('/api/v1/platform/languages')
            ->assertOk()
            ->assertJsonCount(21, 'data');

        $this->actingAs($superAdmin, 'web')
            ->postJson('/api/v1/platform/languages', [
                'name' => 'Urdu',
                'native_name' => 'اردو',
                'locale' => 'ur-PK',
                'direction' => 'rtl',
            ])
            ->assertCreated()
            ->assertJsonPath('data.locale', 'ur_PK')
            ->assertJsonPath('data.direction', 'rtl')
            ->assertJsonPath('data.is_active', true);

        $support = User::factory()->platform()->create();
        app(ScopedRoleAssignmentService::class)->assignPlatformRole($support, 'Support');

        $this->actingAs($support, 'web')
            ->postJson('/api/v1/platform/languages', [
                'name' => 'Forbidden',
                'native_name' => 'Forbidden',
                'locale' => 'xx',
                'direction' => 'ltr',
            ])
            ->assertForbidden();
    }

    public function test_store_owner_selects_one_default_language_without_affecting_other_stores(): void
    {
        app(EnsureAuthorizationCatalog::class)->ensure();
        app(EnsureLanguageCatalog::class)->ensure();
        [$owner, $store] = $this->memberWithRole('Owner');
        $otherStore = Store::factory()->create();
        $english = Language::query()->where('locale', 'en')->firstOrFail();
        $french = Language::query()->where('locale', 'fr')->firstOrFail();

        $this->actingAs($owner, 'web')
            ->withHeader('X-Store-ID', $store->public_id)
            ->putJson('/api/v1/store/languages', [
                'language_ids' => [$english->public_id, $french->public_id],
                'default_language_id' => $french->public_id,
            ])
            ->assertOk()
            ->assertJsonPath('data.store_id', $store->public_id)
            ->assertJsonPath('data.default_language_id', $french->public_id)
            ->assertJsonFragment([
                'locale' => 'fr',
                'is_selected' => true,
                'is_default' => true,
            ]);

        self::assertSame(2, StoreLanguage::query()->where('store_id', $store->getKey())->count());
        self::assertSame(
            1,
            StoreLanguage::query()
                ->where('store_id', $store->getKey())
                ->where('is_default', true)
                ->count(),
        );
        self::assertSame('fr', $store->refresh()->language_code);
        self::assertFalse(StoreLanguage::query()->where('store_id', $otherStore->getKey())->exists());
    }

    public function test_language_selection_enforces_manage_store_and_account_scope(): void
    {
        app(EnsureAuthorizationCatalog::class)->ensure();
        app(EnsureLanguageCatalog::class)->ensure();
        [$sales, $store] = $this->memberWithRole('Sales');
        $english = Language::query()->where('locale', 'en')->firstOrFail();

        $this->actingAs($sales, 'web')
            ->withHeader('X-Store-ID', $store->public_id)
            ->putJson('/api/v1/store/languages', [
                'language_ids' => [$english->public_id],
                'default_language_id' => $english->public_id,
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'The manage store permission is required.');

        $platformUser = User::factory()->platform()->create();

        $this->actingAs($platformUser, 'web')
            ->withHeader('X-Store-ID', $store->public_id)
            ->getJson('/api/v1/store/languages')
            ->assertForbidden()
            ->assertJsonPath('message', 'Store-scoped account required.');
    }

    /** @return array{User, Store} */
    private function memberWithRole(string $role): array
    {
        $user = User::factory()->create();
        $store = Store::factory()->create();

        StoreMembership::factory()->create([
            'user_id' => $user,
            'store_id' => $store,
            'status' => MembershipStatus::Active,
        ]);
        app(ScopedRoleAssignmentService::class)->assignStoreRole($user, $store, $role);

        return [$user, $store];
    }
}
