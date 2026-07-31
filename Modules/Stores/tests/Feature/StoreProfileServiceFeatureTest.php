<?php

declare(strict_types=1);

namespace Modules\Stores\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Authentication\Enums\AccessScope;
use Modules\Authentication\Models\User;
use Modules\Authentication\Services\ScopedRoleAssignmentService;
use Modules\Settings\Actions\EnsureCurrencyCatalog;
use Modules\Settings\Actions\EnsureLanguageCatalog;
use Modules\Stores\Enums\StoreStatus;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StoreMembership;
use Tests\TestCase;

final class StoreProfileServiceFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_owner_creates_views_and_updates_own_store_profile_and_settings(): void
    {
        app(EnsureCurrencyCatalog::class)->ensure();
        app(EnsureLanguageCatalog::class)->ensure();

        $owner = User::factory()->create();
        $storeId = (string) $this->actingAs($owner, 'web')
            ->postJson('/api/v1/stores', [
                'name' => 'Merchant Store',
                'slug' => 'merchant-store',
                'currency_code' => 'gbp',
                'language_code' => 'pt-br',
                'preferences' => ['order_prefix' => 'MS'],
            ])->assertCreated()
            ->assertJsonPath('data.currency_code', 'GBP')
            ->assertJsonPath('data.language_code', 'pt_BR')
            ->json('data.id');

        $this->actingAs($owner, 'web')
            ->withHeader('X-Store-ID', $storeId)
            ->getJson('/api/v1/store')
            ->assertOk()
            ->assertJsonPath('data.id', $storeId);

        $this->actingAs($owner, 'web')
            ->withHeader('X-Store-ID', $storeId)
            ->patchJson('/api/v1/store/profile', [
                'legal_name' => 'Merchant Store Limited',
                'business_type' => 'services',
            ])->assertOk()
            ->assertJsonPath('data.legal_name', 'Merchant Store Limited');

        $this->actingAs($owner, 'web')
            ->withHeader('X-Store-ID', $storeId)
            ->patchJson('/api/v1/store/settings', [
                'currency_code' => 'usd',
                'language_code' => 'pt-br',
                'timezone' => 'Europe/London',
                'preferences' => ['guest_checkout' => true],
            ])->assertOk()
            ->assertJsonPath('data.currency_code', 'USD')
            ->assertJsonPath('data.language_code', 'pt_BR')
            ->assertJsonPath('data.preferences.order_prefix', 'MS')
            ->assertJsonPath('data.preferences.guest_checkout', true);

        $this->actingAs($owner, 'web')
            ->withHeader('X-Store-ID', $storeId)
            ->patchJson('/api/v1/store/settings', [
                'currency_code' => 'ZZZ',
                'language_code' => 'zz',
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['currency_code', 'language_code']);
    }

    public function test_store_staff_can_view_but_cannot_manage_or_cross_store_boundary(): void
    {
        [$sales, $store] = $this->memberWithRole('Sales');

        $this->actingAs($sales, 'web')
            ->withHeader('X-Store-ID', $store->public_id)
            ->getJson('/api/v1/store')
            ->assertOk();
        $this->actingAs($sales, 'web')
            ->withHeader('X-Store-ID', $store->public_id)
            ->patchJson('/api/v1/store/profile', ['name' => 'Forbidden'])
            ->assertForbidden()
            ->assertJsonPath('message', 'The manage store permission is required.');

        $this->actingAs(User::factory()->create(), 'web')
            ->withHeader('X-Store-ID', $store->public_id)
            ->getJson('/api/v1/store')
            ->assertForbidden()
            ->assertJsonPath('message', 'Active store membership is required.');
    }

    public function test_platform_accounts_and_platform_controlled_store_fields_are_rejected(): void
    {
        [$owner, $store] = $this->memberWithRole('Owner');

        $this->actingAs($owner, 'web')
            ->withHeader('X-Store-ID', $store->public_id)
            ->patchJson('/api/v1/store/settings', [
                'plan_id' => 99,
                'status' => 'active',
                'is_ai_enabled' => true,
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['plan_id', 'status', 'is_ai_enabled']);

        $this->actingAs(User::factory()->platform()->create(), 'web')
            ->postJson('/api/v1/stores', ['name' => 'Forbidden', 'slug' => 'forbidden'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Store-scoped account required.');
    }

    public function test_platform_admin_creates_and_updates_a_merchant_with_store_roles_only(): void
    {
        Notification::fake();
        $admin = User::factory()->platform()->create();
        app(ScopedRoleAssignmentService::class)->assignPlatformRole($admin, 'Super Admin');

        $created = $this->actingAs($admin, 'web')
            ->postJson('/api/v1/platform/merchants', [
                'owner' => [
                    'name' => 'Merchant Test Owner',
                    'email' => 'MERCHANT.TEST@EXAMPLE.TEST',
                    'password' => 'Strong!Password123',
                    'password_confirmation' => 'Strong!Password123',
                ],
                'store' => [
                    'name' => 'Merchant Test Store',
                    'slug' => 'merchant-test-store',
                    'timezone' => 'UTC',
                ],
                'roles' => ['Owner', 'Manager', 'Sales', 'Inventory'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.owner.scope', AccessScope::Store->value)
            ->assertJsonCount(4, 'data.owner.roles');

        $storeId = (string) $created->json('data.store.id');
        $this->actingAs($admin, 'web')
            ->patchJson("/api/v1/platform/merchants/{$storeId}", [
                'owner' => [
                    'name' => 'Updated Merchant Owner',
                    'email' => 'updated.merchant@example.test',
                ],
                'store' => [
                    'name' => 'Updated Merchant Store',
                    'slug' => 'updated-merchant-store',
                    'status' => 'suspended',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.store.status', 'suspended')
            ->assertJsonCount(4, 'data.owner.roles');

        $this->actingAs($admin, 'web')
            ->getJson('/api/v1/platform/merchants?page=1&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 1);

        $owner = User::query()->where('email', 'updated.merchant@example.test')->firstOrFail();
        $store = Store::query()->where('public_id', $storeId)->firstOrFail();
        self::assertSame(4, DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->where('model_id', $owner->getKey())
            ->where('store_id', $store->getKey())
            ->count());
        self::assertSame(0, DB::table('store_memberships')->where('user_id', $admin->getKey())->count());
    }

    public function test_platform_admin_manages_the_searchable_paginated_store_catalog(): void
    {
        app(EnsureCurrencyCatalog::class)->ensure();
        app(EnsureLanguageCatalog::class)->ensure();
        $admin = User::factory()->platform()->create();
        app(ScopedRoleAssignmentService::class)->assignPlatformRole($admin, 'Super Admin');

        $created = $this->actingAs($admin, 'web')->postJson('/api/v1/platform/stores', [
            'name' => 'Alpha Direct Store',
            'slug' => 'ALPHA-DIRECT-STORE',
            'email' => 'CONTACT@EXAMPLE.TEST',
            'primary_domain' => 'ALPHA.EXAMPLE.TEST',
            'currency_code' => 'gbp',
            'language_code' => 'pt-br',
            'business_type' => 'services',
            'is_verified' => true,
        ])->assertCreated()
            ->assertJsonPath('data.slug', 'alpha-direct-store')
            ->assertJsonPath('data.legal_name', 'Alpha Direct Store')
            ->assertJsonPath('data.email', 'contact@example.test')
            ->assertJsonPath('data.currency_code', 'GBP')
            ->assertJsonPath('data.language_code', 'pt_BR')
            ->assertJsonPath('data.status', 'pending');

        $storeId = (string) $created->json('data.id');
        Store::factory()->create([
            'name' => 'Alpha Second Store',
            'status' => StoreStatus::Pending,
            'currency_code' => 'GBP',
            'is_verified' => true,
        ]);
        Store::factory()->create(['name' => 'Unmatched Store', 'is_verified' => false]);

        $this->actingAs($admin, 'web')
            ->getJson('/api/v1/platform/stores?search=ALPHA&status=pending&currency_code=gbp&is_verified=true&sort=name&direction=asc&per_page=1&page=2')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Alpha Second Store')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2);

        $this->actingAs($admin, 'web')->patchJson("/api/v1/platform/stores/{$storeId}", [
            'status' => 'active',
            'is_pos_enabled' => true,
            'launched_at' => '2026-07-31T08:30:00+00:00',
        ])->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.is_pos_enabled', true);

        $this->actingAs($admin, 'web')->getJson("/api/v1/platform/stores/{$storeId}")
            ->assertOk()
            ->assertJsonPath('data.id', $storeId);

        $store = Store::query()->where('public_id', $storeId)->firstOrFail();
        self::assertSame(0, DB::table('store_memberships')->where('store_id', $store->getKey())->count());
    }

    public function test_platform_store_catalog_enforces_scope_permission_and_safe_input(): void
    {
        $billing = User::factory()->platform()->create();
        app(ScopedRoleAssignmentService::class)->assignPlatformRole($billing, 'Billing');
        $this->actingAs($billing, 'web')->getJson('/api/v1/platform/stores')->assertForbidden();
        $this->actingAs(User::factory()->create(), 'web')->getJson('/api/v1/platform/stores')->assertForbidden();

        $admin = User::factory()->platform()->create();
        app(ScopedRoleAssignmentService::class)->assignPlatformRole($admin, 'Super Admin');
        $this->actingAs($admin, 'web')
            ->getJson('/api/v1/platform/stores?per_page=101&status=unknown')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page', 'status']);

        $this->actingAs($admin, 'web')->postJson('/api/v1/platform/stores', [
            'name' => 'Unsafe Store',
            'slug' => 'unsafe-store',
            'plan_id' => 99,
            'settings' => ['unsafe' => true],
            'owner' => ['email' => 'owner@example.test'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['plan_id', 'settings', 'owner']);

        $this->actingAs($admin, 'web')
            ->patchJson('/api/v1/platform/stores/01ARZ3NDEKTSV4RRFFQ69G5FAV', ['name' => 'Missing'])
            ->assertNotFound()
            ->assertJsonPath('message', 'Not found.');
    }

    public function test_store_owner_creates_store_user_with_all_store_roles(): void
    {
        Notification::fake();
        [$owner, $store] = $this->memberWithRole('Owner');

        $created = $this->actingAs($owner, 'web')
            ->withHeader('X-Store-ID', $store->public_id)
            ->postJson('/api/v1/store/users', [
                'name' => 'Store Test User',
                'email' => 'STORE.TEST.USER@EXAMPLE.TEST',
                'password' => 'Strong!Password123',
                'password_confirmation' => 'Strong!Password123',
                'roles' => ['Owner', 'Manager', 'Sales', 'Inventory'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.scope', AccessScope::Store->value)
            ->assertJsonPath('data.membership.status', 'active')
            ->assertJsonCount(4, 'data.roles');

        $user = User::query()->where('public_id', (string) $created->json('data.id'))->firstOrFail();
        self::assertSame(4, DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->where('model_id', $user->getKey())
            ->where('store_id', $store->getKey())
            ->count());

        $this->actingAs($owner, 'web')
            ->withHeader('X-Store-ID', $store->public_id)
            ->getJson('/api/v1/store/users?page=2&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonStructure([
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'from', 'last_page', 'path', 'per_page', 'to', 'total'],
            ]);

        $this->actingAs($owner, 'web')
            ->withHeader('X-Store-ID', $store->public_id)
            ->postJson('/api/v1/store/users', [
                'name' => 'Invalid Platform Role',
                'email' => 'invalid-platform-role@example.test',
                'password' => 'Strong!Password123',
                'password_confirmation' => 'Strong!Password123',
                'roles' => ['Super Admin'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['roles.0']);
    }

    /** @return array{User, Store} */
    private function memberWithRole(string $role): array
    {
        $user = User::factory()->create();
        $store = Store::factory()->create();
        StoreMembership::factory()->create(['user_id' => $user, 'store_id' => $store]);
        app(ScopedRoleAssignmentService::class)->assignStoreRole($user, $store, $role);

        return [$user, $store];
    }
}
