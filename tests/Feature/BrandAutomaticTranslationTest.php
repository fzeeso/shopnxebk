<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\Authentication\Models\User;
use Modules\Settings\Actions\EnsureLanguageCatalog;
use Modules\Settings\Models\Language;
use Modules\Stores\Contracts\StoreProvisioner;
use Modules\Stores\Models\Store;
use Tests\TestCase;

final class BrandAutomaticTranslationTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_and_edit_translate_unlocked_locales_without_overwriting_a_locked_locale(): void
    {
        app(EnsureLanguageCatalog::class)->ensure();
        $owner = User::factory()->create();
        $store = $this->provisionStore($owner);
        $this->enableLanguages($store, ['de', 'ar']);
        config([
            'services.openai.api_key' => 'test-api-key',
            'services.openai.translation_model' => 'gpt-5-mini',
        ]);

        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $input = json_decode((string) $request->data()['input'][1]['content'], true, 512, JSON_THROW_ON_ERROR);
            $source = $input['source'];
            $translations = [];

            foreach ($input['target_locales'] as $locale) {
                $updated = str_contains((string) $source['description'], 'Updated');
                $translations[] = [
                    'locale' => $locale,
                    'name' => $source['name'],
                    'description' => match ($locale) {
                        'de' => $updated ? 'Aktualisierte Beschreibung.' : 'Ursprüngliche Beschreibung.',
                        'ar' => $updated ? 'وصف محدّث.' : 'وصف أصلي.',
                        default => $source['description'],
                    },
                    'seo_title' => $source['seo_title'],
                    'seo_description' => $source['seo_description'],
                ];
            }

            return Http::response([
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode(['translations' => $translations], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    ]],
                ]],
            ]);
        });

        $headers = ['X-Store-ID' => (string) $store->public_id];
        $brandId = (string) $this->actingAs($owner, 'web')
            ->withHeaders($headers)
            ->postJson('/api/v1/store/brands', [
                'translations' => [[
                    'locale' => 'en',
                    'name' => 'Acme',
                    'slug' => 'acme',
                    'description' => 'Original description.',
                    'seo_title' => 'Acme products',
                    'seo_description' => null,
                ]],
            ])
            ->assertCreated()
            ->json('data.id');

        $brand = Brand::query()->where('public_id', $brandId)->firstOrFail();
        $this->assertDatabaseHas('brand_translations', [
            'brand_id' => $brand->getKey(),
            'locale' => 'de',
            'description' => 'Ursprüngliche Beschreibung.',
            'lock_it' => false,
        ]);
        $this->assertDatabaseHas('brand_translations', [
            'brand_id' => $brand->getKey(),
            'locale' => 'ar',
            'description' => 'وصف أصلي.',
            'lock_it' => false,
        ]);

        $this->actingAs($owner, 'web')
            ->withHeaders($headers)
            ->patchJson("/api/v1/store/brands/{$brandId}", [
                'translations' => [[
                    'locale' => 'de',
                    'name' => 'Acme Sonderanfertigung',
                    'slug' => 'acme-sonderanfertigung',
                    'description' => 'Von einem Händler verfasst.',
                    'seo_title' => null,
                    'seo_description' => null,
                    'lock_it' => true,
                ]],
            ])
            ->assertOk();

        $this->actingAs($owner, 'web')
            ->withHeaders($headers)
            ->patchJson("/api/v1/store/brands/{$brandId}", [
                'translations' => [[
                    'locale' => 'en',
                    'name' => 'Acme',
                    'slug' => 'acme',
                    'description' => 'Updated description.',
                    'seo_title' => 'Updated Acme products',
                    'seo_description' => null,
                ]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('brand_translations', [
            'brand_id' => $brand->getKey(),
            'locale' => 'de',
            'name' => 'Acme Sonderanfertigung',
            'description' => 'Von einem Händler verfasst.',
            'lock_it' => true,
        ]);
        $this->assertDatabaseHas('brand_translations', [
            'brand_id' => $brand->getKey(),
            'locale' => 'ar',
            'description' => 'وصف محدّث.',
            'lock_it' => false,
        ]);
        Http::assertSentCount(3);
    }

    private function provisionStore(User $owner): Store
    {
        config(['stores.platform_domain' => 'stores.example.test']);

        return app(StoreProvisioner::class)->provision(
            $owner,
            'Brand Translation Store',
            'brand-translation-store',
            ['theme_template_key' => 'default'],
        );
    }

    /** @param list<string> $locales */
    private function enableLanguages(Store $store, array $locales): void
    {
        $now = now();
        foreach ($locales as $locale) {
            $language = Language::query()->where('locale', $locale)->firstOrFail();
            DB::table('store_languages')->updateOrInsert(
                ['store_id' => $store->getKey(), 'language_id' => $language->getKey()],
                ['is_default' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            );
        }
    }
}
