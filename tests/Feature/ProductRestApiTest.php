<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Authentication\Models\User;
use Modules\Authentication\Services\ScopedRoleAssignmentService;
use Modules\Catalog\Models\Product;
use Modules\Settings\Actions\EnsureLanguageCatalog;
use Modules\Stores\Contracts\StoreProvisioner;
use Modules\Stores\Enums\MembershipStatus;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StoreMembership;
use Tests\TestCase;

final class ProductRestApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_manage_products_with_commerce_fields_through_rest(): void
    {
        app(EnsureLanguageCatalog::class)->ensure();
        $owner = User::factory()->create();
        $store = $this->provisionStore($owner, 'REST Product Store', 'rest-product-store');
        $headers = ['X-Store-ID' => (string) $store->public_id];

        $this->getJson('/api/v1/store/products', $headers)->assertUnauthorized();

        $response = $this->actingAs($owner, 'web')->postJson('/api/v1/store/products', [
            'vendor' => 'ShopNXE Test Vendor',
            'fulfillment_type' => 'physical',
            'track_inventory' => true,
            'status' => 'active',
            'sku' => 'REST-SKU-001',
            'downloadfile' => 'catalog/files/manual.pdf',
            'availability' => 'Ships in 2 days',
            'price' => 125.5,
            'costprice' => 80.25,
            'retailprice' => 140,
            'msrpprice' => 150,
            'saleprice' => 120,
            'calculatedprice' => 120,
            'sortorder' => 7,
            'is_featured' => 1,
            'currentinv' => 30,
            'lowinv' => 5,
            'warranty' => 'Two-year warranty',
            'weight' => 2.5,
            'width' => 10,
            'height' => 8,
            'proddepth' => 4,
            'fixedshippingcost' => 9.95,
            'freeshipping' => 0,
            'ratingtotal' => 45,
            'numratings' => 10,
            'numsold' => 20,
            'numviews' => 100,
            'allowpurchases' => 1,
            'hideprice' => 0,
            'is_login_for_price' => 0,
            'is_global_search' => 1,
            'condition' => 'New',
            'showcondition' => 1,
            'pre_order' => 1,
            'releasedate' => '2026-09-01T10:00:00+00:00',
            'releasedateremove' => 0,
            'minqty' => 1,
            'maxqty' => 8,
            'tax_class_id' => 2,
            'show_related_product' => 4,
            'prodpoints' => 25,
            'reviews_on' => 1,
            'upc' => '123456789012',
            'hs_code' => '640411',
            'gtin' => '00012345678905',
            'mpn' => 'MPN-REST-1',
            'bpn' => 'BPN-REST-1',
            'translations' => [[
                'locale' => 'en',
                'title' => 'REST Trail Runner',
                'slug' => 'rest-trail-runner',
                'description' => 'Created through REST.',
                'seo_title' => 'REST Trail Runner shoe',
                'lock_it' => true,
            ]],
        ], $headers);

        $productId = (string) $response
            ->assertCreated()
            ->assertJsonPath('data.sku', 'REST-SKU-001')
            ->assertJsonPath('data.price', '125.5000')
            ->assertJsonPath('data.currentinv', 30)
            ->assertJsonPath('data.show_related_product', 4)
            ->assertJsonPath('data.prodpoints', 25)
            ->assertJsonPath('data.reviews_on', 1)
            ->assertJsonPath('data.translations.0.title', 'REST Trail Runner')
            ->json('data.id');

        $product = Product::query()->where('public_id', $productId)->firstOrFail();
        self::assertSame('REST-SKU-001', $product->sku);
        self::assertSame('125.5000', $product->price);
        self::assertSame(4, $product->show_related_product);
        self::assertSame(25, $product->prodpoints);
        self::assertSame(1, $product->reviews_on);
        self::assertNotNull($product->published_at);

        $this->actingAs($owner, 'web')
            ->getJson('/api/v1/store/products?search=REST-SKU&sku=REST-SKU-001&sort_by=price', $headers)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $productId);

        $this->actingAs($owner, 'web')
            ->getJson("/api/v1/store/products/{$productId}", $headers)
            ->assertOk()
            ->assertJsonPath('data.gtin', '00012345678905');

        $this->actingAs($owner, 'web')
            ->patchJson("/api/v1/store/products/{$productId}", [
                'saleprice' => 110.75,
                'currentinv' => 22,
                'reviews_on' => 0,
                'warranty' => null,
            ], $headers)
            ->assertOk()
            ->assertJsonPath('data.saleprice', '110.7500')
            ->assertJsonPath('data.currentinv', 22)
            ->assertJsonPath('data.reviews_on', 0)
            ->assertJsonPath('data.warranty', null);

        $otherOwner = User::factory()->create();
        $otherStore = $this->provisionStore($otherOwner, 'Other REST Store', 'other-rest-store');
        $this->actingAs($otherOwner, 'web')
            ->getJson("/api/v1/store/products/{$productId}", [
                'X-Store-ID' => (string) $otherStore->public_id,
            ])
            ->assertNotFound();

        $this->actingAs($owner, 'web')
            ->deleteJson("/api/v1/store/products/{$productId}", [], $headers)
            ->assertNoContent();
        $this->assertDatabaseMissing('products', ['id' => $product->getKey()]);
    }

    public function test_product_rest_reads_require_membership_and_writes_require_manage_products(): void
    {
        app(EnsureLanguageCatalog::class)->ensure();
        $owner = User::factory()->create();
        $store = $this->provisionStore($owner, 'Permission Store', 'permission-store');
        $salesUser = User::factory()->create();
        StoreMembership::query()->create([
            'store_id' => $store->getKey(),
            'user_id' => $salesUser->getKey(),
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);
        app(ScopedRoleAssignmentService::class)->assignStoreRole($salesUser, $store, 'Sales');
        $headers = ['X-Store-ID' => (string) $store->public_id];

        $this->actingAs($salesUser, 'web')
            ->getJson('/api/v1/store/products', $headers)
            ->assertOk();

        $this->actingAs($salesUser, 'web')
            ->postJson('/api/v1/store/products', [
                'translations' => [[
                    'locale' => 'en',
                    'title' => 'Forbidden Product',
                    'slug' => 'forbidden-product',
                ]],
            ], $headers)
            ->assertForbidden();
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
