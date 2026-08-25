<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Authentication\Models\User;
use Modules\Catalog\Models\Product;
use Modules\Settings\Actions\EnsureLanguageCatalog;
use Modules\Settings\Models\Language;
use Modules\Stores\Contracts\StoreProvisioner;
use Modules\Stores\Models\Store;
use Tests\TestCase;

final class ProductOptionVariantRestApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_manages_multilingual_options_values_and_complete_variant_combinations(): void
    {
        app(EnsureLanguageCatalog::class)->ensure();
        $owner = User::factory()->create();
        $store = $this->provisionStore($owner);
        $this->enableLanguage($store, 'ur');
        $headers = ['X-Store-ID' => (string) $store->public_id];

        $productId = (string) $this->actingAs($owner, 'web')->postJson('/api/v1/store/products', [
            'translations' => [
                [
                    'locale' => 'en',
                    'title' => 'Multilingual Shirt',
                    'slug' => 'multilingual-shirt',
                    'lock_it' => true,
                ],
                [
                    'locale' => 'ur',
                    'title' => 'کثیر لسانی قمیض',
                    'slug' => 'multilingual-shirt-ur',
                    'lock_it' => true,
                ],
            ],
        ], $headers)->assertCreated()->json('data.id');

        $colour = $this->actingAs($owner, 'web')->postJson("/api/v1/store/products/{$productId}/options", [
            'position' => 0,
            'translations' => [
                ['locale' => 'en', 'name' => 'Color', 'lock_it' => true],
                ['locale' => 'ur', 'name' => 'رنگ'],
            ],
            'values' => [
                ['position' => 0, 'translations' => [
                    ['locale' => 'en', 'value' => 'Red'],
                    ['locale' => 'ur', 'value' => 'سرخ'],
                ]],
                ['position' => 1, 'translations' => [
                    ['locale' => 'en', 'value' => 'Blue'],
                    ['locale' => 'ur', 'value' => 'نیلا'],
                ]],
            ],
        ], $headers)->assertCreated();
        $redId = (string) $colour->json('data.values.0.id');

        $size = $this->actingAs($owner, 'web')->postJson("/api/v1/store/products/{$productId}/options", [
            'position' => 1,
            'translations' => [
                ['locale' => 'en', 'name' => 'Size'],
                ['locale' => 'ur', 'name' => 'سائز'],
            ],
            'values' => [
                ['position' => 0, 'translations' => [
                    ['locale' => 'en', 'value' => 'Small'],
                    ['locale' => 'ur', 'value' => 'چھوٹا'],
                ]],
                ['position' => 1, 'translations' => [
                    ['locale' => 'en', 'value' => 'Large'],
                    ['locale' => 'ur', 'value' => 'بڑا'],
                ]],
            ],
        ], $headers)->assertCreated();
        $smallId = (string) $size->json('data.values.0.id');

        $variant = $this->actingAs($owner, 'web')->postJson("/api/v1/store/products/{$productId}/variants", [
            'sku' => 'SHIRT-RED-S',
            'price_amount_minor' => 2499,
            'currency_code' => 'USD',
            'inventory_qty' => 12,
            'option_value_ids' => [$redId, $smallId],
            'translations' => [
                ['locale' => 'en', 'title' => 'Red / Small'],
                ['locale' => 'ur', 'title' => 'سرخ / چھوٹا'],
            ],
        ], $headers)
            ->assertCreated()
            ->assertJsonPath('data.translations.1.title', 'سرخ / چھوٹا')
            ->assertJsonPath('data.option_values.0.option_translations.1.name', 'رنگ')
            ->assertJsonPath('data.option_values.0.translations.1.value', 'سرخ');
        $variantId = (string) $variant->json('data.id');

        $this->actingAs($owner, 'web')->postJson("/api/v1/store/products/{$productId}/variants", [
            'price_amount_minor' => 2599,
            'currency_code' => 'USD',
            'option_value_ids' => [$redId],
        ], $headers)->assertUnprocessable();

        $this->actingAs($owner, 'web')->postJson("/api/v1/store/products/{$productId}/variants", [
            'price_amount_minor' => 2599,
            'currency_code' => 'USD',
            'option_value_ids' => [$redId, $smallId],
        ], $headers)->assertUnprocessable();

        $colourId = (string) $colour->json('data.id');
        $this->actingAs($owner, 'web')
            ->deleteJson("/api/v1/store/products/{$productId}/options/{$colourId}/values/{$redId}", [], $headers)
            ->assertUnprocessable();

        $this->actingAs($owner, 'web')->getJson("/api/v1/store/products/{$productId}", $headers)
            ->assertOk()
            ->assertJsonPath('data.has_variants', true)
            ->assertJsonPath('data.options.0.translations.1.name', 'رنگ')
            ->assertJsonPath('data.variants.0.id', $variantId);

        $this->actingAs($owner, 'web')
            ->deleteJson("/api/v1/store/products/{$productId}/variants/{$variantId}", [], $headers)
            ->assertNoContent();

        self::assertFalse((bool) Product::query()->where('public_id', $productId)->value('has_variants'));
    }

    public function test_option_translations_reject_locales_that_are_not_active_for_the_store(): void
    {
        app(EnsureLanguageCatalog::class)->ensure();
        $owner = User::factory()->create();
        $store = $this->provisionStore($owner);
        $headers = ['X-Store-ID' => (string) $store->public_id];
        $productId = (string) $this->actingAs($owner, 'web')->postJson('/api/v1/store/products', [
            'translations' => [['locale' => 'en', 'title' => 'Shirt', 'slug' => 'shirt']],
        ], $headers)->assertCreated()->json('data.id');

        $this->actingAs($owner, 'web')->postJson("/api/v1/store/products/{$productId}/options", [
            'translations' => [['locale' => 'ur', 'name' => 'رنگ']],
        ], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('translations.0.locale');
    }

    private function provisionStore(User $owner): Store
    {
        config(['stores.platform_domain' => 'stores.example.test']);

        return app(StoreProvisioner::class)->provision(
            $owner,
            'Option Store',
            'option-store-'.strtolower((string) str()->ulid()),
            ['theme_template_key' => 'default'],
        );
    }

    private function enableLanguage(Store $store, string $locale): void
    {
        $language = Language::query()->where('locale', $locale)->firstOrFail();
        DB::table('store_languages')->updateOrInsert(
            ['store_id' => $store->getKey(), 'language_id' => $language->getKey()],
            ['is_default' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        );
    }
}
