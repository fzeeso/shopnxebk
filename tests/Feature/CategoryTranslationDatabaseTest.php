<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Stores\Models\Store;
use Tests\TestCase;

final class CategoryTranslationDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_translations_store_page_title_and_search_keywords(): void
    {
        self::assertTrue(Schema::hasColumns('category_translations', [
            'page_title',
            'search_keywords',
        ]));

        $store = Store::factory()->create();
        $categoryId = DB::table('categories')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'store_id' => $store->getKey(),
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('category_translations')->insert([
            'store_id' => $store->getKey(),
            'category_id' => $categoryId,
            'locale' => 'en',
            'title' => 'Running Shoes',
            'slug' => 'running-shoes',
            'seo_description' => 'Shop running shoes.',
            'page_title' => 'Running Shoes for Every Runner',
            'search_keywords' => 'running shoes, trainers, athletic footwear',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('category_translations', [
            'category_id' => $categoryId,
            'locale' => 'en',
            'page_title' => 'Running Shoes for Every Runner',
            'search_keywords' => 'running shoes, trainers, athletic footwear',
        ]);
    }
}
