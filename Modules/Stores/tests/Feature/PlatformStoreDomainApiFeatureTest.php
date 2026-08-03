<?php

declare(strict_types=1);

namespace Modules\Stores\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Authentication\Models\User;
use Modules\Authentication\Services\ScopedRoleAssignmentService;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StoreDomain;
use Tests\TestCase;

final class PlatformStoreDomainApiFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_store_creation_initializes_platform_and_custom_domains(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin, 'web')
            ->postJson('/api/v1/platform/stores', [
                'name' => 'Fashion Hub',
                'slug' => 'fashion-hub',
                'primary_domain' => 'fashionhub.example',
                'locale_settings' => [
                    'date_format' => 'd/m/Y',
                    'time_format' => '12h',
                    'weight_unit' => 'lb',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.primary_domain', 'fashionhub.example');

        $store = Store::query()->where('public_id', $response->json('data.id'))->firstOrFail();

        $this->assertDatabaseHas('store_domains', [
            'store_id' => $store->getKey(),
            'domain' => 'fashion-hub.shopnxe.com',
            'domain_type' => 'platform',
            'is_primary' => false,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('store_domains', [
            'store_id' => $store->getKey(),
            'domain' => 'fashionhub.example',
            'domain_type' => 'custom',
            'is_primary' => true,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('store_locale_settings', [
            'store_id' => $store->getKey(),
            'date_format' => 'd/m/Y',
            'time_format' => '12h',
            'weight_unit' => 'lb',
        ]);
    }

    public function test_super_admin_can_list_add_and_update_store_domains(): void
    {
        $admin = $this->superAdmin();
        $store = Store::factory()->create(['primary_domain' => 'first.example']);
        $firstDomain = $store->domains()->create([
            'domain' => 'first.example',
            'domain_type' => 'custom',
            'is_primary' => true,
            'status' => 'active',
            'ssl_status' => 'active',
            'verified_at' => now(),
        ]);

        $this->actingAs($admin, 'web')
            ->getJson("/api/v1/platform/stores/{$store->public_id}/domains")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $firstDomain->public_id)
            ->assertJsonPath('data.0.is_primary', true);

        $createResponse = $this->actingAs($admin, 'web')
            ->postJson("/api/v1/platform/stores/{$store->public_id}/domains", [
                'domain' => 'checkout.example',
                'domain_type' => 'custom',
            ])
            ->assertCreated()
            ->assertJsonPath('data.domain', 'checkout.example')
            ->assertJsonPath('data.is_primary', false);

        $domainId = (string) $createResponse->json('data.id');

        $this->actingAs($admin, 'web')
            ->patchJson("/api/v1/platform/stores/{$store->public_id}/domains/{$domainId}", [
                'is_primary' => true,
                'status' => 'active',
                'ssl_status' => 'active',
                'is_verified' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_primary', true)
            ->assertJsonPath('data.is_verified', true)
            ->assertJsonPath('data.ssl_status', 'active');

        $this->assertDatabaseHas('stores', [
            'id' => $store->getKey(),
            'primary_domain' => 'checkout.example',
        ]);
        $this->assertDatabaseHas('store_domains', [
            'id' => $firstDomain->getKey(),
            'is_primary' => false,
        ]);
        $this->assertDatabaseHas('store_domains', [
            'public_id' => $domainId,
            'is_primary' => true,
            'status' => 'active',
            'ssl_status' => 'active',
        ]);

        $this->actingAs($admin, 'web')
            ->patchJson("/api/v1/platform/stores/{$store->public_id}/domains/{$domainId}", [
                'is_primary' => false,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('is_primary');
    }

    public function test_domain_names_are_globally_unique(): void
    {
        $admin = $this->superAdmin();
        $firstStore = Store::factory()->create();
        $secondStore = Store::factory()->create();
        StoreDomain::query()->create([
            'store_id' => $firstStore->getKey(),
            'domain' => 'shared.example',
            'domain_type' => 'custom',
            'is_primary' => true,
            'status' => 'active',
            'ssl_status' => 'active',
        ]);

        $this->actingAs($admin, 'web')
            ->postJson("/api/v1/platform/stores/{$secondStore->public_id}/domains", [
                'domain' => 'shared.example',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('domain');
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->platform()->create();
        app(ScopedRoleAssignmentService::class)->assignPlatformRole($admin, 'Super Admin');

        return $admin;
    }
}
