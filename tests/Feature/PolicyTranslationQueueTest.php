<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\Translations\TranslateContentJob;
use App\Models\TranslationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Modules\Authentication\Models\User;
use Modules\Settings\Actions\EnsureLanguageCatalog;
use Modules\Settings\Models\Language;
use Modules\Stores\Contracts\StoreProvisioner;
use Modules\Stores\Models\StorePolicy;
use Tests\TestCase;

final class PolicyTranslationQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_policy_source_commits_before_translation_is_queued(): void
    {
        app(EnsureLanguageCatalog::class)->ensure();
        $owner = User::factory()->create();
        config(['stores.platform_domain' => 'stores.example.test']);
        $store = app(StoreProvisioner::class)->provision(
            $owner,
            'Queued Policy Store',
            'queued-policy-store',
            ['theme_template_key' => 'default'],
        );
        $english = Language::query()->where('locale', 'en')->firstOrFail();
        $german = Language::query()->where('locale', 'de')->firstOrFail();
        DB::table('store_languages')->updateOrInsert(
            ['store_id' => $store->getKey(), 'language_id' => $german->getKey()],
            ['is_default' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        );
        $policy = StorePolicy::query()
            ->withoutGlobalScopes()
            ->where('store_id', $store->getKey())
            ->firstOrFail();
        Queue::fake([TranslateContentJob::class]);
        Http::preventStrayRequests();

        $this->actingAs($owner, 'web')
            ->withHeader('X-Store-ID', (string) $store->public_id)
            ->putJson("/api/v1/store/policies/{$policy->public_id}/translations/{$english->public_id}", [
                'title' => 'Source policy',
                'content' => 'Committed before the provider runs.',
            ])
            ->assertOk()
            ->assertJsonPath('data.translation_request.status', 'pending');

        $this->assertDatabaseHas('store_policy_translations', [
            'store_policy_id' => $policy->getKey(),
            'language_id' => $english->getKey(),
            'content' => 'Committed before the provider runs.',
        ]);
        $this->assertDatabaseMissing('store_policy_translations', [
            'store_policy_id' => $policy->getKey(),
            'language_id' => $german->getKey(),
        ]);
        $this->assertDatabaseHas('translation_requests', [
            'store_id' => $store->getKey(),
            'content_type' => 'store_policy',
            'status' => 'pending',
        ]);
        self::assertSame(1, TranslationRequest::query()->withoutGlobalScopes()->count());
        Queue::assertPushedOn('translations', TranslateContentJob::class);
        Http::assertNothingSent();
    }
}
