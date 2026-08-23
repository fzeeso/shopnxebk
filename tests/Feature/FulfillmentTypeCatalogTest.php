<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Authentication\Models\User;
use Modules\Authentication\Services\ScopedRoleAssignmentService;
use Modules\Catalog\Actions\EnsureFulfillmentTypeCatalog;
use Modules\Settings\Actions\EnsureLanguageCatalog;
use Modules\Settings\Models\Language;
use Modules\Stores\Contracts\StoreProvisioner;
use Tests\TestCase;

final class FulfillmentTypeCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_schema_and_defaults_cover_every_language(): void
    {
        self::assertTrue(Schema::hasColumns('fulfillment_types', [
            'id',
            'code',
            'is_active',
            'sort_order',
            'created_at',
            'updated_at',
        ]));
        self::assertTrue(Schema::hasColumns('fulfillment_type_translations', [
            'id',
            'fulfillment_type_id',
            'locale',
            'name',
            'description',
        ]));

        app(EnsureLanguageCatalog::class)->ensure();
        app(EnsureFulfillmentTypeCatalog::class)->ensure();

        $languageCount = Language::query()->count();

        $this->assertDatabaseCount('fulfillment_types', 6);
        $this->assertDatabaseCount('fulfillment_type_translations', 6 * $languageCount);
        $this->assertDatabaseHas('fulfillment_types', [
            'code' => 'merchant',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $this->assertDatabaseHas('fulfillment_types', [
            'code' => 'digital',
            'is_active' => true,
            'sort_order' => 6,
        ]);
        $this->assertDatabaseHas('fulfillment_type_translations', [
            'locale' => 'ur',
            'name' => 'ڈیجیٹل ترسیل',
        ]);
    }

    public function test_platform_admin_can_list_the_localized_catalog(): void
    {
        app(EnsureLanguageCatalog::class)->ensure();
        app(EnsureFulfillmentTypeCatalog::class)->ensure();

        $user = User::factory()->platform()->create();

        $this->getJson('/api/v1/platform/settings/fulfillment-types')
            ->assertUnauthorized();

        $response = $this->actingAs($user, 'web')
            ->getJson('/api/v1/platform/settings/fulfillment-types');

        $response
            ->assertOk()
            ->assertJsonCount(6, 'data')
            ->assertJsonPath('data.0.code', 'merchant')
            ->assertJsonPath('data.0.sort_order', 1)
            ->assertJsonPath('data.5.code', 'digital')
            ->assertJsonCount(Language::query()->count(), 'data.0.translations');
    }

    public function test_platform_admin_can_create_show_and_update_a_fulfillment_type(): void
    {
        app(EnsureLanguageCatalog::class)->ensure();
        $viewer = User::factory()->platform()->create();
        $admin = User::factory()->platform()->create();
        app(ScopedRoleAssignmentService::class)->assignPlatformRole($admin, 'Super Admin');

        $this->actingAs($viewer, 'web')
            ->postJson('/api/v1/platform/settings/fulfillment-types', [
                'code' => 'contract_fulfillment',
                'translations' => [[
                    'locale' => 'en',
                    'name' => 'Contract fulfillment',
                ]],
            ])
            ->assertForbidden();

        $this->actingAs($admin, 'web')
            ->postJson('/api/v1/platform/settings/fulfillment-types', [
                'code' => 'contract_fulfillment',
                'sort_order' => 20,
                'translations' => [[
                    'locale' => 'en',
                    'name' => 'Contract fulfillment',
                    'description' => 'Fulfilled by a contracted partner.',
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'contract_fulfillment')
            ->assertJsonPath('data.translations.0.name', 'Contract fulfillment');

        $this->actingAs($admin, 'web')
            ->getJson('/api/v1/platform/settings/fulfillment-types/contract_fulfillment')
            ->assertOk()
            ->assertJsonPath('data.sort_order', 20);

        $this->actingAs($admin, 'web')
            ->patchJson('/api/v1/platform/settings/fulfillment-types/contract_fulfillment', [
                'is_active' => false,
                'sort_order' => 30,
                'translations' => [[
                    'locale' => 'en',
                    'name' => 'Partner fulfillment',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.sort_order', 30)
            ->assertJsonPath('data.translations.0.name', 'Partner fulfillment');
    }

    public function test_store_member_can_list_only_active_fulfillment_types(): void
    {
        app(EnsureLanguageCatalog::class)->ensure();
        app(EnsureFulfillmentTypeCatalog::class)->ensure();
        $owner = User::factory()->create();
        config(['stores.platform_domain' => 'stores.example.test']);
        $store = app(StoreProvisioner::class)->provision(
            $owner,
            'Fulfillment Store',
            'fulfillment-store',
            ['theme_template_key' => 'default'],
        );

        $this->actingAs($owner, 'web')
            ->getJson('/api/v1/store/fulfillment-types', [
                'X-Store-ID' => (string) $store->public_id,
            ])
            ->assertOk()
            ->assertJsonCount(6, 'data')
            ->assertJsonPath('data.0.code', 'merchant');
    }
}
