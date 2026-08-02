<?php

declare(strict_types=1);

namespace Modules\Authentication\Tests\Feature;

use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\Authentication\Actions\EnsureAuthorizationCatalog;
use Modules\Authentication\Enums\AccessScope;
use Modules\Authentication\Models\Role;
use Modules\Authentication\Models\User;
use Modules\Authentication\Services\ScopedRoleAssignmentService;
use Modules\Stores\Enums\MembershipStatus;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StoreMembership;
use Tests\TestCase;

final class AccountScopeBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_and_store_accounts_are_exclusive_and_interfaces_never_mix(): void
    {
        app(EnsureAuthorizationCatalog::class)->ensure();
        $roleAssignments = app(ScopedRoleAssignmentService::class);

        $platformUser = User::factory()->platform()->create();
        $roleAssignments->assignPlatformRole($platformUser, 'Support');

        $this->actingAs($platformUser, 'web')
            ->getJson('/api/v1/auth/interfaces')
            ->assertOk()
            ->assertJsonPath('data.platform_admin.available', true)
            ->assertJsonPath('data.platform_admin.roles.0', 'Support')
            ->assertJsonPath('data.store_admin.available', false)
            ->assertJsonCount(0, 'data.store_admin.stores');
        $this->actingAs($platformUser, 'web')
            ->getJson('/api/v1/auth/stores')
            ->assertForbidden()
            ->assertJsonPath('message', 'Store-scoped account required.');

        $storeUser = User::factory()->create();
        $alpha = Store::factory()->create(['name' => 'Alpha Merchant']);
        $beta = Store::factory()->create(['name' => 'Beta Merchant']);
        StoreMembership::factory()->create(['user_id' => $storeUser, 'store_id' => $alpha]);
        StoreMembership::factory()->create(['user_id' => $storeUser, 'store_id' => $beta]);
        $roleAssignments->assignStoreRole($storeUser, $alpha, 'Owner');
        $roleAssignments->assignStoreRole($storeUser, $beta, 'Inventory');

        $response = $this->actingAs($storeUser, 'web')
            ->getJson('/api/v1/auth/interfaces')
            ->assertOk()
            ->assertJsonPath('data.platform_admin.interface', 'platform_admin')
            ->assertJsonPath('data.platform_admin.label', 'Platform Admin (SaaS Owner)')
            ->assertJsonPath('data.platform_admin.available', false)
            ->assertJsonCount(0, 'data.platform_admin.roles')
            ->assertJsonCount(0, 'data.platform_admin.permissions')
            ->assertJsonPath('data.store_admin.interface', 'store_admin')
            ->assertJsonPath('data.store_admin.label', 'Store Admin (Merchant)')
            ->assertJsonPath('data.store_admin.available', true)
            ->assertJsonCount(2, 'data.store_admin.stores');

        $alphaAccess = $response->json('data.store_admin.stores.0');
        $betaAccess = $response->json('data.store_admin.stores.1');
        self::assertSame($alpha->public_id, $alphaAccess['id']);
        self::assertSame(['Owner'], $alphaAccess['roles']);
        self::assertContains('manage orders', $alphaAccess['permissions']);
        self::assertArrayNotHasKey('store_id', $alphaAccess);
        self::assertSame($beta->public_id, $betaAccess['id']);
        self::assertSame(['Inventory'], $betaAccess['roles']);
        self::assertSame(['access store', 'manage products'], $betaAccess['permissions']);

        self::assertThrows(
            fn () => $roleAssignments->assignStoreRole($platformUser, $alpha, 'Owner'),
            DomainException::class,
            'Only Store-scoped users may receive Store roles.',
        );
        self::assertThrows(
            fn () => $roleAssignments->assignPlatformRole($storeUser, 'Support'),
            DomainException::class,
            'Only Platform-scoped users may receive Platform roles.',
        );
    }

    public function test_platform_account_cannot_receive_store_token(): void
    {
        $platformUser = User::factory()->platform()->create(['email' => 'platform@example.test']);
        $store = Store::factory()->create();

        $this->postJson('/api/v1/auth/token', [
            'email' => $platformUser->email,
            'password' => 'password',
            'device_name' => 'admin-browser',
            'store_id' => $store->public_id,
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Store-scoped account required.');
    }

    public function test_super_admin_creates_and_updates_platform_users_with_platform_roles_only(): void
    {
        Notification::fake();
        $admin = User::factory()->platform()->create();
        $roles = app(ScopedRoleAssignmentService::class);
        $roles->assignPlatformRole($admin, 'Super Admin');

        $created = $this->actingAs($admin, 'web')
            ->postJson('/api/v1/platform/users', [
                'name' => 'Platform Test Staff',
                'email' => 'PLATFORM.TEST.STAFF@EXAMPLE.TEST',
                'password' => 'Strong!Password123',
                'password_confirmation' => 'Strong!Password123',
                'roles' => ['Support', 'Billing'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.scope', AccessScope::Platform->value)
            ->assertJsonPath('data.roles.0', 'Billing')
            ->assertJsonPath('data.roles.1', 'Support');

        $publicId = (string) $created->json('data.id');
        $this->actingAs($admin, 'web')
            ->patchJson("/api/v1/platform/users/{$publicId}", [
                'name' => 'Updated Platform Staff',
                'email' => 'updated.platform.staff@example.test',
                'password' => 'Replacement!Password123',
                'password_confirmation' => 'Replacement!Password123',
                'roles' => ['Support'],
            ])
            ->assertOk()
            ->assertJsonPath('data.roles.0', 'Support');

        $updated = User::query()->where('public_id', $publicId)->firstOrFail();
        self::assertTrue(Hash::check('Replacement!Password123', $updated->password));
        self::assertSame(AccessScope::Platform, $updated->scope);

        $this->actingAs($admin, 'web')
            ->postJson('/api/v1/platform/users', [
                'name' => 'Invalid Role',
                'email' => 'invalid-role@example.test',
                'password' => 'Strong!Password123',
                'password_confirmation' => 'Strong!Password123',
                'roles' => ['Owner'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['roles.0']);
    }

    public function test_platform_store_list_projects_primary_member_and_searches_store_domain_and_user(): void
    {
        $admin = User::factory()->platform()->create();
        app(ScopedRoleAssignmentService::class)->assignPlatformRole($admin, 'Super Admin');

        Store::factory()->count(11)->create();
        $store = Store::factory()->create([
            'name' => 'Graph Merchant Hub',
            'primary_domain' => 'graph-merchant.example.test',
        ]);
        $owner = User::factory()->create([
            'name' => 'Ayesha Store Owner',
            'email' => 'ayesha.owner@example.test',
        ]);
        StoreMembership::factory()->create([
            'store_id' => $store,
            'user_id' => $owner,
        ]);

        $this->actingAs($admin, 'web')
            ->getJson('/api/v1/platform/stores')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 12);

        foreach ([$store->name, $store->primary_domain, $owner->name, $owner->email] as $search) {
            $this->actingAs($admin, 'web')
                ->getJson('/api/v1/platform/stores?per_page=20&search='.rawurlencode($search))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', (string) $store->public_id)
                ->assertJsonPath('data.0.name', $store->name)
                ->assertJsonPath('data.0.primary_domain', $store->primary_domain)
                ->assertJsonPath('data.0.owner.id', (string) $owner->public_id)
                ->assertJsonPath('data.0.owner.name', $owner->name)
                ->assertJsonPath('data.0.owner.email', $owner->email);
        }
    }

    public function test_database_rejects_cross_scope_memberships_roles_and_scope_changes(): void
    {
        app(EnsureAuthorizationCatalog::class)->ensure();
        $roleAssignments = app(ScopedRoleAssignmentService::class);
        $platformUser = User::factory()->platform()->create();
        $storeUser = User::factory()->create();
        $store = Store::factory()->create();
        $ownerRole = Role::query()->where('name', 'Owner')->firstOrFail();

        try {
            DB::transaction(fn () => DB::table('store_users')->insert([
                'public_id' => (string) Str::ulid(),
                'store_id' => $store->getKey(),
                'user_id' => $platformUser->getKey(),
                'status' => MembershipStatus::Active->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
            self::fail('Platform membership insert should have failed.');
        } catch (QueryException $exception) {
            self::assertStringContainsString('Only Store-scoped users may have Store memberships.', $exception->getMessage());
        }

        try {
            DB::transaction(fn () => DB::table('model_has_roles')->insert([
                'role_id' => $ownerRole->getKey(),
                'model_type' => User::class,
                'model_id' => $storeUser->getKey(),
                'store_id' => $store->getKey(),
            ]));
            self::fail('Role insert without membership should have failed.');
        } catch (QueryException $exception) {
            self::assertStringContainsString('Active Store membership is required before assigning a Store role.', $exception->getMessage());
        }

        $roleAssignments->assignPlatformRole($platformUser, 'Support');
        try {
            DB::transaction(fn () => DB::table('users')->where('id', $platformUser->getKey())->update(['scope' => AccessScope::Store->value]));
            self::fail('Scope change with an existing role should have failed.');
        } catch (QueryException $exception) {
            self::assertStringContainsString('Remove memberships, roles, and direct permissions before changing user scope.', $exception->getMessage());
        }
    }
}
