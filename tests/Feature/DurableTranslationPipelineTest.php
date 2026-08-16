<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TranslationRequestStatus;
use App\Jobs\Translations\TranslateContentJob;
use App\Models\Brand;
use App\Models\TranslationRequest;
use App\Support\Translations\TranslationProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Modules\Authentication\Models\User;
use Modules\Settings\Actions\EnsureLanguageCatalog;
use Modules\Settings\Models\Language;
use Modules\Stores\Contracts\StoreProvisioner;
use Modules\Stores\Models\Store;
use Tests\TestCase;

final class DurableTranslationPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_commits_and_translation_is_queued_with_store_scoped_status(): void
    {
        app(EnsureLanguageCatalog::class)->ensure();
        $owner = User::factory()->create();
        $store = $this->provisionStore($owner, 'Async Brand Store', 'async-brand-store');
        $this->enableLanguage($store, 'de');
        Queue::fake([TranslateContentJob::class]);
        Http::preventStrayRequests();

        $response = $this->actingAs($owner, 'web')
            ->withHeader('X-Store-ID', (string) $store->public_id)
            ->postJson('/api/v1/store/brands', [
                'translations' => [[
                    'locale' => 'en',
                    'name' => 'Acme',
                    'slug' => 'acme',
                    'description' => 'Saved before translation.',
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.translation_request.status', 'pending')
            ->assertJsonPath('data.translation_request.target_locales.0', 'de');

        $brand = Brand::query()->where('public_id', $response->json('data.id'))->firstOrFail();
        $request = TranslationRequest::query()->withoutGlobalScopes()->firstOrFail();
        $response->assertJsonPath(
            'data.translation_request.status_url',
            "/api/v1/store/translation-requests/{$request->public_id}",
        );

        $this->assertDatabaseHas('brand_translations', [
            'brand_id' => $brand->getKey(),
            'locale' => 'en',
            'description' => 'Saved before translation.',
        ]);
        $this->assertDatabaseMissing('brand_translations', [
            'brand_id' => $brand->getKey(),
            'locale' => 'de',
        ]);
        Queue::assertPushedOn('translations', TranslateContentJob::class);
        Http::assertNothingSent();

        $this->actingAs($owner, 'web')
            ->withHeader('X-Store-ID', (string) $store->public_id)
            ->getJson("/api/v1/store/translation-requests/{$request->public_id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        $otherOwner = User::factory()->create();
        $otherStore = $this->provisionStore($otherOwner, 'Other Async Store', 'other-async-store');
        $this->actingAs($otherOwner, 'web')
            ->withHeader('X-Store-ID', (string) $otherStore->public_id)
            ->getJson("/api/v1/store/translation-requests/{$request->public_id}")
            ->assertNotFound();
    }

    public function test_changed_source_supersedes_work_before_calling_openai(): void
    {
        app(EnsureLanguageCatalog::class)->ensure();
        $owner = User::factory()->create();
        $store = $this->provisionStore($owner, 'Stale Translation Store', 'stale-translation-store');
        $this->enableLanguage($store, 'de');
        Queue::fake([TranslateContentJob::class]);

        $response = $this->actingAs($owner, 'web')
            ->withHeader('X-Store-ID', (string) $store->public_id)
            ->postJson('/api/v1/store/brands', [
                'translations' => [[
                    'locale' => 'en',
                    'name' => 'Before',
                    'slug' => 'before',
                    'description' => 'Before queue execution.',
                ]],
            ])
            ->assertCreated();

        $brand = Brand::query()->where('public_id', $response->json('data.id'))->firstOrFail();
        $request = TranslationRequest::query()->withoutGlobalScopes()->firstOrFail();
        DB::table('brand_translations')
            ->where('brand_id', $brand->getKey())
            ->where('locale', 'en')
            ->update(['description' => 'Changed after commit.', 'updated_at' => now()->addSecond()]);

        Http::preventStrayRequests();
        app(TranslationProcessor::class)->process((int) $request->getKey());

        self::assertSame(TranslationRequestStatus::Superseded, $request->refresh()->status);
        $this->assertDatabaseMissing('brand_translations', [
            'brand_id' => $brand->getKey(),
            'locale' => 'de',
        ]);
        Http::assertNothingSent();
    }

    private function provisionStore(User $owner, string $name, string $slug): Store
    {
        config(['stores.platform_domain' => 'stores.example.test']);

        return app(StoreProvisioner::class)->provision(
            $owner,
            $name,
            $slug,
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
