<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MediaStatus;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Modules\Authentication\Models\User;
use Modules\Stores\Contracts\StoreProvisioner;
use Modules\Stores\Models\Store;
use Tests\TestCase;

final class MediaAiRestApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        Bus::fake();
        config([
            'media-management.disk' => 'private',
            'media-management.allowed_disks' => ['private'],
            'services.openai.api_key' => 'test-openai-key',
            'services.openai.media_image_model' => 'gpt-image-2',
            'services.openai.media_analysis_model' => 'gpt-5-mini',
            'services.openai.media_quality' => 'medium',
        ]);
    }

    public function test_store_can_generate_ai_media_and_filter_the_library_by_source(): void
    {
        $owner = User::factory()->create();
        $store = $this->provisionStore($owner, 'AI Generation Store', 'ai-generation-store');
        $headers = $this->headers($store);
        Http::fake([
            'api.openai.com/v1/images/generations' => Http::response([
                'data' => [[
                    'b64_json' => base64_encode($this->pngBytes()),
                    'revised_prompt' => 'A studio product photograph.',
                ]],
            ], 200, ['x-request-id' => 'req_generation_123']),
        ]);

        $mediaId = (string) $this->actingAs($owner, 'web')
            ->postJson('/api/v1/store/media/ai/generate', [
                'prompt' => 'Create a clean product photograph of a reusable bottle.',
                'image_type' => 'product photography',
                'style' => 'studio',
                'aspect_ratio' => '4:5',
                'image_count' => 1,
                'quality' => 'medium',
            ], $headers)
            ->assertCreated()
            ->assertJsonPath('data.0.metadata.source', 'ai_generated')
            ->assertJsonPath('data.0.metadata.ai.model', 'gpt-image-2')
            ->json('data.0.id');

        $media = Media::query()->where('public_id', $mediaId)->firstOrFail();
        Storage::disk('private')->assertExists($media->path);
        $this->assertDatabaseHas('media_ai_results', [
            'media_id' => $media->getKey(),
            'provider' => 'openai',
            'model' => 'gpt-image-2',
            'operation' => 'generate_image',
            'status' => 'completed',
        ]);
        $this->actingAs($owner, 'web')
            ->getJson('/api/v1/store/media?source=ai_generated', $headers)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $mediaId);
        $this->actingAs($owner, 'web')
            ->getJson('/api/v1/store/media?source=uploaded', $headers)
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.openai.com/v1/images/generations'
            && $request['model'] === 'gpt-image-2'
            && $request['size'] === '1024x1280'
            && $request['n'] === 1
            && $request->hasHeader('Authorization', 'Bearer test-openai-key'));
    }

    public function test_store_can_generate_alt_text_for_ready_image_media(): void
    {
        $owner = User::factory()->create();
        $store = $this->provisionStore($owner, 'AI Metadata Store', 'ai-metadata-store');
        $media = $this->uploadReadyMedia($owner, $store, 'blue-bottle.png');
        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'id' => 'resp_metadata_123',
                'output_text' => json_encode([
                    'alt_text' => 'Blue reusable bottle on a white background.',
                ], JSON_THROW_ON_ERROR),
                'usage' => ['input_tokens' => 25, 'output_tokens' => 12],
            ]),
        ]);

        $this->actingAs($owner, 'web')
            ->postJson("/api/v1/store/media/{$media->public_id}/ai", [
                'operation' => 'generate_alt_text',
            ], $this->headers($store))
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.operation', 'generate_alt_text')
            ->assertJsonPath('media.alt_text', 'Blue reusable bottle on a white background.')
            ->assertJsonPath('generated_media', null);

        self::assertSame(
            'Blue reusable bottle on a white background.',
            $media->refresh()->alt_text,
        );
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.openai.com/v1/responses'
            && $request['store'] === false
            && $request['model'] === 'gpt-5-mini'
            && str_starts_with((string) $request['input'][0]['content'][1]['image_url'], 'data:image/png;base64,'));
    }

    public function test_store_can_create_a_background_removed_derivative(): void
    {
        $owner = User::factory()->create();
        $store = $this->provisionStore($owner, 'AI Edit Store', 'ai-edit-store');
        $media = $this->uploadReadyMedia($owner, $store, 'catalog-item.png');
        Http::fake([
            'api.openai.com/v1/images/edits' => Http::response([
                'data' => [[
                    'b64_json' => base64_encode($this->pngBytes()),
                    'revised_prompt' => 'Isolated catalog item.',
                ]],
            ], 200, ['x-request-id' => 'req_edit_123']),
        ]);

        $generatedId = (string) $this->actingAs($owner, 'web')
            ->postJson("/api/v1/store/media/{$media->public_id}/ai", [
                'operation' => 'remove_background',
                'quality' => 'high',
            ], $this->headers($store))
            ->assertCreated()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('generated_media.metadata.source', 'ai_generated')
            ->assertJsonPath('generated_media.metadata.ai.source_media_id', $media->public_id)
            ->json('generated_media.id');

        $generated = Media::query()->where('public_id', $generatedId)->firstOrFail();
        Storage::disk('private')->assertExists($generated->path);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.openai.com/v1/images/edits'
            && str_contains($request->body(), 'transparent')
            && str_contains($request->body(), 'gpt-image-2'));
    }

    public function test_provider_failure_is_safe_and_marks_the_operation_failed(): void
    {
        $owner = User::factory()->create();
        $store = $this->provisionStore($owner, 'AI Failure Store', 'ai-failure-store');
        $media = $this->uploadReadyMedia($owner, $store, 'failure.png');
        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'error' => ['type' => 'server_error', 'code' => 'provider_failure'],
            ], 500, ['x-request-id' => 'req_failure_123']),
        ]);

        $this->actingAs($owner, 'web')
            ->postJson("/api/v1/store/media/{$media->public_id}/ai", [
                'operation' => 'generate_tags',
            ], $this->headers($store))
            ->assertStatus(502)
            ->assertJsonPath('message', 'The AI media provider rejected the request.')
            ->assertJsonMissing(['error' => ['code' => 'provider_failure']]);

        $this->assertDatabaseHas('media_ai_results', [
            'media_id' => $media->getKey(),
            'operation' => 'generate_tags',
            'status' => 'failed',
        ]);
    }

    public function test_ai_media_operations_are_store_scoped(): void
    {
        $owner = User::factory()->create();
        $store = $this->provisionStore($owner, 'Source AI Store', 'source-ai-store');
        $media = $this->uploadReadyMedia($owner, $store, 'private.png');
        $otherOwner = User::factory()->create();
        $otherStore = $this->provisionStore($otherOwner, 'Other AI Store', 'other-ai-store');

        $this->actingAs($otherOwner, 'web')
            ->postJson("/api/v1/store/media/{$media->public_id}/ai", [
                'operation' => 'generate_alt_text',
            ], $this->headers($otherStore))
            ->assertNotFound();
        Http::assertNothingSent();
    }

    private function uploadReadyMedia(User $owner, Store $store, string $filename): Media
    {
        $mediaId = (string) $this->actingAs($owner, 'web')
            ->postJson('/api/v1/store/media/uploads', [
                'file' => UploadedFile::fake()->image($filename, 10, 10),
            ], $this->headers($store))
            ->assertCreated()
            ->json('data.id');
        $media = Media::query()->where('public_id', $mediaId)->firstOrFail();
        $media->forceFill(['status' => MediaStatus::Ready])->save();

        return $media->refresh();
    }

    private function pngBytes(): string
    {
        $bytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZQmcAAAAASUVORK5CYII=',
            true,
        );
        self::assertIsString($bytes);

        return $bytes;
    }

    /** @return array<string, string> */
    private function headers(Store $store): array
    {
        return ['X-Store-ID' => (string) $store->public_id];
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
}
