<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Stores\Models\Store;
use Tests\TestCase;

final class ProductCommerceFieldsDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_commerce_fields_exist_and_apply_requested_defaults(): void
    {
        self::assertTrue(Schema::hasColumns('products', [
            'sku',
            'downloadfile',
            'availability',
            'price',
            'costprice',
            'retailprice',
            'msrpprice',
            'saleprice',
            'calculatedprice',
            'sortorder',
            'is_featured',
            'currentinv',
            'lowinv',
            'warranty',
            'weight',
            'width',
            'height',
            'proddepth',
            'fixedshippingcost',
            'freeshipping',
            'ratingtotal',
            'numratings',
            'numsold',
            'numviews',
            'allowpurchases',
            'hideprice',
            'is_login_for_price',
            'is_global_search',
            'condition',
            'showcondition',
            'pre_order',
            'releasedate',
            'releasedateremove',
            'minqty',
            'maxqty',
            'tax_class_id',
            'show_related_product',
            'prodpoints',
            'reviews_on',
            'upc',
            'hs_code',
            'gtin',
            'mpn',
            'bpn',
        ]));

        $productId = $this->createProduct();
        $product = DB::table('products')->find($productId);

        self::assertNotNull($product);
        foreach (['sku', 'downloadfile', 'availability', 'upc', 'hs_code', 'gtin', 'mpn', 'bpn'] as $column) {
            self::assertSame('', $product->{$column});
        }
        foreach (['price', 'costprice', 'retailprice', 'msrpprice', 'saleprice', 'calculatedprice', 'weight', 'width', 'height', 'proddepth', 'fixedshippingcost'] as $column) {
            self::assertSame('0.0000', $product->{$column});
        }
        foreach (['sortorder', 'is_featured', 'currentinv', 'lowinv', 'freeshipping', 'ratingtotal', 'numratings', 'numsold', 'numviews', 'hideprice', 'is_login_for_price', 'is_global_search', 'showcondition', 'pre_order', 'releasedateremove', 'minqty', 'maxqty', 'tax_class_id', 'show_related_product', 'prodpoints', 'reviews_on'] as $column) {
            self::assertSame(0, $product->{$column});
        }
        self::assertSame(1, $product->allowpurchases);
        self::assertSame('New', $product->condition);
        self::assertNull($product->warranty);
        self::assertNull($product->releasedate);
    }

    public function test_product_condition_rejects_an_unsupported_value(): void
    {
        $this->expectException(QueryException::class);

        DB::table('products')->where('id', $this->createProduct())->update([
            'condition' => 'Damaged',
        ]);
    }

    private function createProduct(): int
    {
        $store = Store::factory()->create();

        return DB::table('products')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'store_id' => $store->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
