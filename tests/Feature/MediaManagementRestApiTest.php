<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MediaStatus;
use App\Enums\MediaVariantName;
use App\Jobs\Media\ExtractMediaMetadata;
use App\Jobs\Media\FinalizeMediaProcessing;
use App\Jobs\Media\GenerateMediaVariants;
use App\Jobs\Media\OptimizeMedia;
use App\Models\Media;
use App\Models\MediaVariant;
use App\Services\Media\MediaProcessor;
use App\Services\Media\MediaService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Authentication\Models\User;
use Modules\Authentication\Services\ScopedRoleAssignmentService;
use Modules\Catalog\Models\Product;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Contracts\StoreProvisioner;
use Modules\Stores\Enums\MembershipStatus;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StoreMembership;
use RuntimeException;
use Tests\TestCase;

final class MediaManagementRestApiTest extends TestCase
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
        ]);
    }

    public function test_store_can_upload_list_complete_and_view_its_media(): void
    {
        $owner = User::factory()->create();
        $store = $this->provisionStore($owner, 'Media Store', 'media-store');
        $headers = $this->headers($store);

        $response = $this->actingAs($owner, 'web')->postJson('/api/v1/store/media/uploads', [
            'file' => UploadedFile::fake()->image('front-view.png', 120, 80),
            'title' => 'Front view',
            'alt_text' => 'Product from the front',
            'metadata' => ['source' => 'merchant'],
        ], $headers);

        $mediaId = (string) $response
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.original_filename', 'front-view.png')
            ->assertJsonPath('data.title', 'Front view')
            ->json('data.id');
        $media = Media::query()->where('public_id', $mediaId)->firstOrFail();

        self::assertStringContainsString(
            "stores/{$store->public_id}/media/".now()->format('Y/m')."/{$mediaId}",
            $media->path,
        );
        Storage::disk('private')->assertExists($media->path);
        $this->assertDatabaseHas('media', [
            'public_id' => $mediaId,
            'store_id' => $store->getKey(),
            'status' => 'pending',
            'mime_type' => 'image/png',
        ]);

        $this->actingAs($owner, 'web')
            ->getJson('/api/v1/store/media', $headers)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $mediaId);
        $this->actingAs($owner, 'web')
            ->getJson("/api/v1/store/media/{$mediaId}", $headers)
            ->assertOk()
            ->assertJsonPath('data.id', $mediaId);

        $this->actingAs($owner, 'web')
            ->postJson("/api/v1/store/media/{$mediaId}/complete", [], $headers)
            ->assertAccepted()
            ->assertJsonPath('data.status', 'processing');
        Bus::assertChained([
            ExtractMediaMetadata::class,
            OptimizeMedia::class,
            GenerateMediaVariants::class,
            FinalizeMediaProcessing::class,
        ]);
    }

    public function test_media_access_and_mutation_are_store_scoped_and_permission_protected(): void
    {
        $owner = User::factory()->create();
        $store = $this->provisionStore($owner, 'Owner Media Store', 'owner-media-store');
        $media = $this->uploadReadyMedia($owner, $store, 'owner.png');

        $otherOwner = User::factory()->create();
        $otherStore = $this->provisionStore($otherOwner, 'Other Media Store', 'other-media-store');
        $this->actingAs($otherOwner, 'web')
            ->getJson("/api/v1/store/media/{$media->public_id}", $this->headers($otherStore))
            ->assertNotFound();
        $this->actingAs($otherOwner, 'web')
            ->deleteJson("/api/v1/store/media/{$media->public_id}", [], $this->headers($otherStore))
            ->assertNotFound();

        $salesUser = User::factory()->create();
        StoreMembership::query()->create([
            'store_id' => $store->getKey(),
            'user_id' => $salesUser->getKey(),
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);
        app(ScopedRoleAssignmentService::class)->assignStoreRole($salesUser, $store, 'Sales');
        $this->actingAs($salesUser, 'web')
            ->getJson("/api/v1/store/media/{$media->public_id}", $this->headers($store))
            ->assertOk();
        $this->actingAs($salesUser, 'web')
            ->postJson('/api/v1/store/media/uploads', [
                'file' => UploadedFile::fake()->image('forbidden.png'),
            ], $this->headers($store))
            ->assertForbidden();
    }

    public function test_processing_service_extracts_metadata_optimizes_and_generates_derivatives(): void
    {
        $owner = User::factory()->create();
        $store = $this->provisionStore($owner, 'Processing Store', 'processing-store');
        $mediaId = (string) $this->actingAs($owner, 'web')
            ->postJson('/api/v1/store/media/uploads', [
                'file' => UploadedFile::fake()->image('processing.png', 100, 50),
            ], $this->headers($store))
            ->assertCreated()
            ->json('data.id');
        $media = Media::query()->where('public_id', $mediaId)->firstOrFail();
        $processor = app(MediaProcessor::class);

        $processor->extractMetadata($media);
        $processor->optimize($media->refresh());
        $processor->generateVariants($media->refresh());
        $processor->markReady($media->refresh());

        $media->refresh()->load('variants');
        self::assertSame(100, $media->width);
        self::assertSame(50, $media->height);
        self::assertSame(MediaStatus::Ready, $media->status);
        self::assertCount(5, $media->variants);
        foreach ($media->variants as $variant) {
            Storage::disk($variant->disk)->assertExists($variant->path);
        }
    }

    public function test_terminal_processing_failure_is_recorded_on_the_media_row(): void
    {
        $owner = User::factory()->create();
        $store = $this->provisionStore($owner, 'Failure Store', 'failure-store');
        $mediaId = (string) $this->actingAs($owner, 'web')
            ->postJson('/api/v1/store/media/uploads', [
                'file' => UploadedFile::fake()->image('failure.png'),
            ], $this->headers($store))
            ->assertCreated()
            ->json('data.id');
        $media = Media::query()->where('public_id', $mediaId)->firstOrFail();

        (new ExtractMediaMetadata((int) $media->getKey(), (int) $store->getKey()))
            ->failed(new RuntimeException('Expected processing failure.'));

        $media->refresh();
        self::assertSame(MediaStatus::Failed, $media->status);
        self::assertSame(
            ExtractMediaMetadata::class,
            $media->metadata['processing_failure']['job'] ?? null,
        );
        self::assertSame(
            'Expected processing failure.',
            $media->metadata['processing_failure']['message'] ?? null,
        );
    }

    public function test_product_media_is_reusable_and_primary_media_is_consistent(): void
    {
        $owner = User::factory()->create();
        $store = $this->provisionStore($owner, 'Reusable Media Store', 'reusable-media-store');
        $headers = $this->headers($store);
        $firstProductId = $this->createProduct($owner, $store, 'first-product');
        $secondProductId = $this->createProduct($owner, $store, 'second-product');
        $firstMedia = $this->uploadReadyMedia($owner, $store, 'first.png');
        $secondMedia = $this->uploadReadyMedia($owner, $store, 'second.png');

        $this->actingAs($owner, 'web')->postJson(
            "/api/v1/store/products/{$firstProductId}/media",
            ['media_id' => $firstMedia->public_id, 'sort_order' => 10, 'is_primary' => true],
            $headers,
        )->assertCreated()->assertJsonPath('data.is_primary', true);
        $this->actingAs($owner, 'web')->postJson(
            "/api/v1/store/products/{$secondProductId}/media",
            ['media_id' => $firstMedia->public_id],
            $headers,
        )->assertCreated();
        $this->actingAs($owner, 'web')->postJson(
            "/api/v1/store/products/{$firstProductId}/media",
            ['media_id' => $secondMedia->public_id, 'sort_order' => 1],
            $headers,
        )->assertCreated();
        $this->actingAs($owner, 'web')->putJson(
            "/api/v1/store/products/{$firstProductId}/media/{$secondMedia->public_id}/primary",
            [],
            $headers,
        )->assertOk()->assertJsonPath('data.is_primary', true);

        $firstProduct = Product::query()->where('public_id', $firstProductId)->firstOrFail();
        $secondProduct = Product::query()->where('public_id', $secondProductId)->firstOrFail();
        self::assertCount(2, $firstProduct->media);
        self::assertCount(1, $secondProduct->media);
        self::assertSame($secondMedia->getKey(), $firstProduct->primaryMedia()->firstOrFail()->getKey());
        $this->assertDatabaseHas('media_usages', [
            'media_id' => $firstMedia->getKey(),
            'resource_type' => Product::class,
            'resource_id' => $secondProduct->getKey(),
        ]);

        $this->actingAs($owner, 'web')->deleteJson(
            "/api/v1/store/products/{$firstProductId}/media/{$secondMedia->public_id}",
            [],
            $headers,
        )->assertNoContent();
        $firstProduct->unsetRelation('media');
        self::assertSame($firstMedia->getKey(), $firstProduct->primaryMedia()->firstOrFail()->getKey());
    }

    public function test_product_variant_media_can_be_attached_and_detached(): void
    {
        $owner = User::factory()->create();
        $store = $this->provisionStore($owner, 'Variant Media Store', 'variant-media-store');
        $productId = $this->createProduct($owner, $store, 'variant-product');
        $product = Product::query()->where('public_id', $productId)->firstOrFail();
        $variantPublicId = (string) Str::ulid();
        DB::table('product_variants')->insert([
            'public_id' => $variantPublicId,
            'store_id' => $store->getKey(),
            'product_id' => $product->getKey(),
            'price_amount_minor' => 1000,
            'currency_code' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $media = $this->uploadReadyMedia($owner, $store, 'variant.png');

        $this->actingAs($owner, 'web')->postJson(
            "/api/v1/store/product-variants/{$variantPublicId}/media",
            ['media_id' => $media->public_id, 'sort_order' => 3],
            $this->headers($store),
        )->assertCreated()->assertJsonPath('data.sort_order', 3);
        $this->assertDatabaseHas('product_variant_media', [
            'store_id' => $store->getKey(),
            'media_id' => $media->getKey(),
            'sort_order' => 3,
        ]);

        $this->actingAs($owner, 'web')->deleteJson(
            "/api/v1/store/product-variants/{$variantPublicId}/media/{$media->public_id}",
            [],
            $this->headers($store),
        )->assertNoContent();
        $this->assertDatabaseMissing('product_variant_media', ['media_id' => $media->getKey()]);
    }

    public function test_duplicate_checksum_lookup_is_limited_to_the_current_store(): void
    {
        $owner = User::factory()->create();
        $store = $this->provisionStore($owner, 'Duplicate Store', 'duplicate-store');
        $first = $this->uploadReadyMedia($owner, $store, 'same.png');
        $second = $this->uploadReadyMedia($owner, $store, 'same.png');
        self::assertSame($first->checksum, $second->checksum);

        $store->makeCurrent();
        app(StoreContext::class)->set($store);
        $duplicate = app(MediaService::class)->findDuplicate($owner, (string) $first->checksum);
        self::assertNotNull($duplicate);
        self::assertSame($store->getKey(), $duplicate->store_id);
        app(StoreContext::class)->clear();
        Store::forgetCurrent();
    }

    public function test_media_variant_name_is_unique_per_media(): void
    {
        $owner = User::factory()->create();
        $store = $this->provisionStore($owner, 'Variant Constraint Store', 'variant-constraint-store');
        $media = $this->uploadReadyMedia($owner, $store, 'constraint.png');
        $attributes = [
            'media_id' => $media->getKey(),
            'variant' => MediaVariantName::Original,
            'disk' => 'private',
            'path' => $media->path,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'width' => 10,
            'height' => 10,
        ];
        MediaVariant::query()->create($attributes);

        $this->expectException(QueryException::class);
        MediaVariant::query()->create($attributes);
    }

    public function test_media_delete_is_recoverable_and_detaches_active_relationships(): void
    {
        $owner = User::factory()->create();
        $store = $this->provisionStore($owner, 'Delete Media Store', 'delete-media-store');
        $productId = $this->createProduct($owner, $store, 'delete-product');
        $media = $this->uploadReadyMedia($owner, $store, 'delete.png');
        $headers = $this->headers($store);
        $this->actingAs($owner, 'web')->postJson(
            "/api/v1/store/products/{$productId}/media",
            ['media_id' => $media->public_id, 'is_primary' => true],
            $headers,
        )->assertCreated();

        $this->actingAs($owner, 'web')
            ->deleteJson("/api/v1/store/media/{$media->public_id}", [], $headers)
            ->assertNoContent();

        $this->assertDatabaseHas('media', [
            'id' => $media->getKey(),
            'status' => MediaStatus::Deleted->value,
            'visibility' => 'private',
        ]);
        $this->assertDatabaseMissing('product_media', ['media_id' => $media->getKey()]);
        $this->assertDatabaseHas('media_usages', ['media_id' => $media->getKey()]);
        Storage::disk('private')->assertExists($media->path);
        $this->actingAs($owner, 'web')
            ->getJson("/api/v1/store/media/{$media->public_id}", $headers)
            ->assertNotFound();
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

    private function createProduct(User $owner, Store $store, string $slug): string
    {
        return (string) $this->actingAs($owner, 'web')
            ->postJson('/api/v1/store/products', [
                'translations' => [[
                    'locale' => 'en',
                    'title' => Str::headline($slug),
                    'slug' => $slug,
                ]],
            ], $this->headers($store))
            ->assertCreated()
            ->json('data.id');
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
