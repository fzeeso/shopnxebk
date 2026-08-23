<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Resources;

use App\Http\Resources\TranslationRequestResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductTranslation;

/** @extends JsonResource<Product> */
final class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'brand_id' => $this->brandPublicId(),
            'platform_taxonomy_node_id' => $this->platformTaxonomyNodePublicId(),
            'vendor' => $this->vendor,
            'product_type_id' => $this->productTypePublicId(),
            'fulfillment_type' => $this->fulfillment_type,
            'track_inventory' => $this->track_inventory,
            'status' => $this->status,
            'has_variants' => $this->has_variants,
            'published_at' => $this->published_at?->toIso8601String(),
            'sku' => $this->sku,
            'downloadfile' => $this->downloadfile,
            'availability' => $this->availability,
            'price' => $this->price,
            'costprice' => $this->costprice,
            'retailprice' => $this->retailprice,
            'msrpprice' => $this->msrpprice,
            'saleprice' => $this->saleprice,
            'calculatedprice' => $this->calculatedprice,
            'sortorder' => $this->sortorder,
            'is_featured' => $this->is_featured,
            'currentinv' => $this->currentinv,
            'lowinv' => $this->lowinv,
            'warranty' => $this->warranty,
            'weight' => $this->weight,
            'width' => $this->width,
            'height' => $this->height,
            'proddepth' => $this->proddepth,
            'fixedshippingcost' => $this->fixedshippingcost,
            'freeshipping' => $this->freeshipping,
            'ratingtotal' => $this->ratingtotal,
            'numratings' => $this->numratings,
            'numsold' => $this->numsold,
            'numviews' => $this->numviews,
            'allowpurchases' => $this->allowpurchases,
            'hideprice' => $this->hideprice,
            'is_login_for_price' => $this->is_login_for_price,
            'is_global_search' => $this->is_global_search,
            'condition' => $this->condition,
            'showcondition' => $this->showcondition,
            'pre_order' => $this->pre_order,
            'releasedate' => $this->releasedate?->toIso8601String(),
            'releasedateremove' => $this->releasedateremove,
            'minqty' => $this->minqty,
            'maxqty' => $this->maxqty,
            'tax_class_id' => $this->tax_class_id,
            'show_related_product' => $this->show_related_product,
            'prodpoints' => $this->prodpoints,
            'reviews_on' => $this->reviews_on,
            'upc' => $this->upc,
            'hs_code' => $this->hs_code,
            'gtin' => $this->gtin,
            'mpn' => $this->mpn,
            'bpn' => $this->bpn,
            'primary_category_id' => $this->primaryCategoryPublicId(),
            'categories_count' => $this->whenCounted('categories'),
            'categories' => $this->whenLoaded('categories', fn () => $this->categories
                ->map(static fn (Category $category): array => [
                    'id' => $category->public_id,
                    'sort_order' => (int) $category->pivot->sort_order,
                    'is_primary' => (bool) $category->pivot->is_primary,
                ])
                ->values()),
            'translations' => $this->whenLoaded('translations', fn () => $this->translations
                ->map(static fn (ProductTranslation $translation): array => [
                    'locale' => $translation->locale,
                    'title' => $translation->title,
                    'slug' => $translation->slug,
                    'description' => $translation->description,
                    'seo_title' => $translation->seo_title,
                    'seo_description' => $translation->seo_description,
                    'lock_it' => $translation->lock_it,
                    'created_at' => $translation->created_at?->toIso8601String(),
                    'updated_at' => $translation->updated_at?->toIso8601String(),
                ])
                ->values()),
            'translation_request' => $this->when(
                $this->resource->relationLoaded('translationRequest'),
                fn () => $this->resource->getRelation('translationRequest') === null
                    ? null
                    : new TranslationRequestResource($this->resource->getRelation('translationRequest')),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
