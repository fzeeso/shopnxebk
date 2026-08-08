<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Authentication\Models\User;
use Modules\Authentication\Services\ScopedRoleAssignmentService;
use Modules\Settings\Actions\EnsureLanguageCatalog;
use Modules\Settings\Models\Language;
use Modules\Stores\Contracts\StoreProvisioner;
use Modules\Stores\Models\PolicyType;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StorePolicy;
use Modules\Stores\Services\PlatformStoreAdminService;
use Tests\TestCase;

final class StorePolicyProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_provisioning_creates_every_policy_type_as_disabled(): void
    {
        $store = $this->provisionStore();
        $types = PolicyType::query()->orderBy('sort_order')->get();
        $policies = StorePolicy::query()
            ->withoutGlobalScopes()
            ->where('store_id', $store->getKey())
            ->with('policyType')
            ->get();

        self::assertCount(8, $types);
        self::assertCount($types->count(), $policies);

        foreach ($types as $type) {
            $policy = $policies->firstWhere('policy_type_id', $type->getKey());

            self::assertInstanceOf(StorePolicy::class, $policy);
            self::assertSame('disabled', $policy->statusValue());
            self::assertSame($type->name, $policy->title);
            self::assertNull($policy->published_at);
        }
    }

    public function test_owner_can_edit_enable_publish_and_disable_a_precreated_policy(): void
    {
        app(EnsureLanguageCatalog::class)->ensure();
        $owner = User::factory()->create();
        $store = $this->provisionStore($owner, 'Policy API Store', 'policy-api-store');
        $policy = StorePolicy::query()
            ->withoutGlobalScopes()
            ->where('store_id', $store->getKey())
            ->whereHas('policyType', fn ($query) => $query->where('code', 'privacy'))
            ->firstOrFail();
        $language = Language::query()->where('locale', 'en')->firstOrFail();
        $headers = ['X-Store-ID' => (string) $store->public_id];

        $this->actingAs($owner, 'web')->withHeaders($headers)
            ->patchJson("/api/v1/store/policies/{$policy->public_id}", [
                'title' => 'Our Privacy Policy',
                'slug' => 'privacy-notice',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'disabled')
            ->assertJsonPath('data.title', 'Our Privacy Policy');

        $this->actingAs($owner, 'web')->withHeaders($headers)
            ->putJson("/api/v1/store/policies/{$policy->public_id}/translations/{$language->public_id}", [
                'title' => 'Our Privacy Policy',
                'content' => 'We protect customer information.',
                'lock_it' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.lock_it', true);

        $this->actingAs($owner, 'web')->withHeaders($headers)
            ->postJson("/api/v1/store/policies/{$policy->public_id}/publish")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->actingAs($owner, 'web')->withHeaders($headers)
            ->postJson("/api/v1/store/policies/{$policy->public_id}/unpublish")
            ->assertOk()
            ->assertJsonPath('data.status', 'disabled');

        $this->actingAs($owner, 'web')->withHeaders($headers)
            ->postJson("/api/v1/store/policies/{$policy->public_id}/enable")
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');

        $this->actingAs($owner, 'web')->withHeaders($headers)
            ->postJson("/api/v1/store/policies/{$policy->public_id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->withHeaders($headers)
            ->getJson('/api/v1/storefront/policies/privacy-notice')
            ->assertOk()
            ->assertJsonPath('data.title', 'Our Privacy Policy');

        $this->actingAs($owner, 'web')->withHeaders($headers)
            ->postJson("/api/v1/store/policies/{$policy->public_id}/disable")
            ->assertOk()
            ->assertJsonPath('data.status', 'disabled')
            ->assertJsonPath('data.published_at', null);

        $this->withHeaders($headers)
            ->getJson('/api/v1/storefront/policies/privacy-notice')
            ->assertNotFound();

        $this->actingAs($owner, 'web')->withHeaders($headers)
            ->deleteJson("/api/v1/store/policies/{$policy->public_id}")
            ->assertNoContent();

        $this->assertDatabaseHas('store_policies', [
            'id' => $policy->getKey(),
            'status' => 'disabled',
        ]);
    }

    public function test_new_policy_types_are_added_disabled_to_existing_stores(): void
    {
        $store = $this->provisionStore();
        $admin = User::factory()->platform()->create();
        app(ScopedRoleAssignmentService::class)->assignPlatformRole($admin, 'Super Admin');

        $this->actingAs($admin, 'web')->postJson('/api/v1/platform/policy-types', [
            'code' => 'warranty',
            'name' => 'Warranty Policy',
            'description' => 'Product warranty terms.',
            'sort_order' => 90,
        ])->assertCreated();

        $type = PolicyType::query()->where('code', 'warranty')->firstOrFail();
        $this->assertDatabaseHas('store_policies', [
            'store_id' => $store->getKey(),
            'policy_type_id' => $type->getKey(),
            'title' => 'Warranty Policy',
            'status' => 'disabled',
            'published_at' => null,
        ]);
    }

    public function test_direct_platform_store_creation_also_creates_disabled_policies(): void
    {
        config(['stores.platform_domain' => 'stores.example.test']);
        $admin = User::factory()->platform()->create();
        app(ScopedRoleAssignmentService::class)->assignPlatformRole($admin, 'Super Admin');

        $store = app(PlatformStoreAdminService::class)->create($admin, [
            'name' => 'Platform Policy Store',
            'slug' => 'platform-policy-store',
        ]);

        self::assertSame(
            PolicyType::query()->count(),
            StorePolicy::query()->withoutGlobalScopes()->where('store_id', $store->getKey())->count(),
        );
        $this->assertDatabaseMissing('store_policies', [
            'store_id' => $store->getKey(),
            'status' => 'draft',
        ]);
        $this->assertDatabaseMissing('store_policies', [
            'store_id' => $store->getKey(),
            'status' => 'published',
        ]);
    }

    private function provisionStore(
        ?User $owner = null,
        string $name = 'Policy Provisioned Store',
        string $slug = 'policy-provisioned-store',
    ): Store {
        config(['stores.platform_domain' => 'stores.example.test']);

        return app(StoreProvisioner::class)->provision(
            $owner ?? User::factory()->create(),
            $name,
            $slug,
            ['theme_template_key' => 'default'],
        );
    }
}
