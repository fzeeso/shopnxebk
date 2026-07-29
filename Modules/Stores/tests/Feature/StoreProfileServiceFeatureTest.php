<?php

declare(strict_types=1);

namespace Modules\Stores\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Authentication\Models\User;
use Modules\Authentication\Services\ScopedRoleAssignmentService;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StoreMembership;
use Tests\TestCase;

final class StoreProfileServiceFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_owner_creates_views_and_updates_own_store_profile_and_settings(): void
    {
        $owner = User::factory()->create();
        $storeId = (string) $this->actingAs($owner, 'web')
            ->postJson('/api/v1/stores', [
                'name' => 'Merchant Store',
                'slug' => 'merchant-store',
                'currency_code' => 'gbp',
                'preferences' => ['order_prefix' => 'MS'],
            ])->assertCreated()
            ->assertJsonPath('data.currency_code', 'GBP')
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
                'timezone' => 'Europe/London',
                'preferences' => ['guest_checkout' => true],
            ])->assertOk()
            ->assertJsonPath('data.preferences.order_prefix', 'MS')
            ->assertJsonPath('data.preferences.guest_checkout', true);
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
