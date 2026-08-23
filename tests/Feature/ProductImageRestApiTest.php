<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Authentication\Models\User;
use Modules\Authentication\Services\ScopedRoleAssignmentService;
use Modules\Catalog\Models\Product;
use Modules\Settings\Actions\EnsureLanguageCatalog;
use Modules\Stores\Contracts\StoreProvisioner;
use Modules\Stores\Enums\MembershipStatus;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StoreMembership;
use Tests\TestCase;

final class ProductImageRestApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_manage_nested_product_image_metadata_through_rest(): void
    {
        app(EnsureLanguageCatalog::class)->ensure();
        $owner = User::factory()->create();
        $store = $this->provisionStore($owner, 'Image API Store', 'image-api-store');
        $headers = ['X-Store-ID' => (string) $store->public_id];
        $productId = $this->createProduct($owner, $headers, 'image-api-product', 'Image API Product');

        $response = $this->actingAs($owner, 'web')->postJson(
            "/api/v1/store/products/{$productId}/images",
            [
                'url' => '/media/catalog/trail-runner-front.avif',
                'width' => 1600,
                'height' => 1200,
                'position' => 2,
                'translations' => [[
                    'locale' => 'en',
                    'alt_text' => 'Trail Runner front view',
                    'lock_it' => true,
                ]],
            ],
            $headers,
        );

        $imageId = (string) $response
            ->assertCreated()
            ->assertJsonPath('data.product_id', $productId)
            ->assertJsonPath('data.variant_id', null)
            ->assertJsonPath('data.width', 1600)
            ->assertJsonPath('data.position', 2)
            ->assertJsonPath('data.translations.0.alt_text', 'Trail Runner front view')
            ->assertJsonPath('data.translations.0.lock_it', true)
            ->json('data.id');

        $this->assertDatabaseHas('product_images', [
            'public_id' => $imageId,
            'store_id' => $store->getKey(),
            'url' => '/media/catalog/trail-runner-front.avif',
        ]);
        $this->assertDatabaseHas('product_image_translations', [
            'store_id' => $store->getKey(),
            'locale' => 'en',
            'alt_text' => 'Trail Runner front view',
            'lock_it' => true,
        ]);

        $this->actingAs($owner, 'web')
            ->getJson("/api/v1/store/products/{$productId}/images", $headers)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $imageId);

        $this->actingAs($owner, 'web')
            ->getJson("/api/v1/store/products/{$productId}/images/{$imageId}", $headers)
            ->assertOk()
            ->assertJsonPath('data.url', '/media/catalog/trail-runner-front.avif');

        $this->actingAs($owner, 'web')
            ->patchJson("/api/v1/store/products/{$productId}/images/{$imageId}", [
                'url' => 'https://cdn.example.test/trail-runner-front.webp',
                'position' => 0,
                'translations' => [[
                    'locale' => 'en',
                    'alt_text' => null,
                ]],
            ], $headers)
            ->assertOk()
            ->assertJsonPath('data.url', 'https://cdn.example.test/trail-runner-front.webp')
            ->assertJsonPath('data.position', 0)
            ->assertJsonPath('data.translations.0.alt_text', null)
            ->assertJsonPath('data.translations.0.lock_it', true);

        $otherProductId = $this->createProduct($owner, $headers, 'variant-owner', 'Variant Owner');
        $otherProduct = Product::query()->where('public_id', $otherProductId)->firstOrFail();
        $variantId = (string) Str::ulid();
        DB::table('product_variants')->insert([
            'public_id' => $variantId,
            'store_id' => $store->getKey(),
            'product_id' => $otherProduct->getKey(),
            'price_amount_minor' => 1000,
            'currency_code' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($owner, 'web')
            ->patchJson("/api/v1/store/products/{$productId}/images/{$imageId}", [
                'variant_id' => $variantId,
            ], $headers)
            ->assertNotFound();

        $this->actingAs($owner, 'web')
            ->postJson("/api/v1/store/products/{$productId}/images", [
                'url' => 'javascript:alert(1)',
            ], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('url');

        $otherOwner = User::factory()->create();
        $otherStore = $this->provisionStore($otherOwner, 'Other Image Store', 'other-image-store');
        $this->actingAs($otherOwner, 'web')
            ->getJson("/api/v1/store/products/{$productId}/images/{$imageId}", [
                'X-Store-ID' => (string) $otherStore->public_id,
            ])
            ->assertNotFound();

        $this->actingAs($owner, 'web')
            ->deleteJson("/api/v1/store/products/{$productId}/images/{$imageId}", [], $headers)
            ->assertNoContent();
        $this->assertDatabaseMissing('product_images', ['public_id' => $imageId]);
    }

    public function test_product_image_reads_require_membership_and_writes_require_manage_products(): void
    {
        app(EnsureLanguageCatalog::class)->ensure();
        $owner = User::factory()->create();
        $store = $this->provisionStore($owner, 'Image Permission Store', 'image-permission-store');
        $headers = ['X-Store-ID' => (string) $store->public_id];
        $productId = $this->createProduct($owner, $headers, 'permission-product', 'Permission Product');
        $salesUser = User::factory()->create();
        StoreMembership::query()->create([
            'store_id' => $store->getKey(),
            'user_id' => $salesUser->getKey(),
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);
        app(ScopedRoleAssignmentService::class)->assignStoreRole($salesUser, $store, 'Sales');

        $this->getJson("/api/v1/store/products/{$productId}/images", $headers)
            ->assertUnauthorized();

        $this->actingAs($salesUser, 'web')
            ->getJson("/api/v1/store/products/{$productId}/images", $headers)
            ->assertOk();

        $this->actingAs($salesUser, 'web')
            ->postJson("/api/v1/store/products/{$productId}/images", [
                'url' => '/media/catalog/forbidden.avif',
            ], $headers)
            ->assertForbidden();
    }

    /** @param array<string, string> $headers */
    private function createProduct(User $owner, array $headers, string $slug, string $title): string
    {
        return (string) $this->actingAs($owner, 'web')
            ->postJson('/api/v1/store/products', [
                'translations' => [[
                    'locale' => 'en',
                    'title' => $title,
                    'slug' => $slug,
                ]],
            ], $headers)
            ->assertCreated()
            ->json('data.id');
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
