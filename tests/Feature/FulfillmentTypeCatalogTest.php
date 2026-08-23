<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Authentication\Models\User;
use Modules\Catalog\Actions\EnsureFulfillmentTypeCatalog;
use Modules\Settings\Actions\EnsureLanguageCatalog;
use Modules\Settings\Models\Language;
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
}
