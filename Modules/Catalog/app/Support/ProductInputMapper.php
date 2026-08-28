<?php

declare(strict_types=1);

namespace Modules\Catalog\Support;

final class ProductInputMapper
{
    private const FIELD_MAP = [
        'brand_id' => 'brandId',
        'platform_taxonomy_node_id' => 'platformTaxonomyNodeId',
        'vendor' => 'vendor',
        'product_type_id' => 'productTypeId',
        'fulfillment_type' => 'fulfillmentType',
        'track_inventory' => 'trackInventory',
        'status' => 'status',
        'category_ids' => 'categoryIds',
        'primary_category_id' => 'primaryCategoryId',
        'sku' => 'sku',
        'downloadfile' => 'downloadFile',
        'availability' => 'availability',
        'price' => 'price',
        'costprice' => 'costPrice',
        'retailprice' => 'retailPrice',
        'msrpprice' => 'msrpPrice',
        'saleprice' => 'salePrice',
        'calculatedprice' => 'calculatedPrice',
        'sortorder' => 'sortOrder',
        'is_featured' => 'isFeatured',
        'currentinv' => 'currentInventory',
        'lowinv' => 'lowInventory',
        'warranty' => 'warranty',
        'weight' => 'weight',
        'width' => 'width',
        'height' => 'height',
        'proddepth' => 'productDepth',
        'fixedshippingcost' => 'fixedShippingCost',
        'freeshipping' => 'freeShipping',
        'ratingtotal' => 'ratingTotal',
        'numratings' => 'numRatings',
        'numsold' => 'numSold',
        'numviews' => 'numViews',
        'allowpurchases' => 'allowPurchases',
        'hideprice' => 'hidePrice',
        'is_login_for_price' => 'loginForPrice',
        'is_global_search' => 'globalSearch',
        'condition' => 'condition',
        'showcondition' => 'showCondition',
        'pre_order' => 'preOrder',
        'releasedate' => 'releaseDate',
        'releasedateremove' => 'releaseDateRemove',
        'minqty' => 'minQuantity',
        'maxqty' => 'maxQuantity',
        'tax_class_id' => 'taxClassId',
        'show_related_product' => 'showRelatedProduct',
        'prodpoints' => 'productPoints',
        'reviews_on' => 'reviewsOn',
        'upc' => 'upc',
        'hs_code' => 'hsCode',
        'gtin' => 'gtin',
        'mpn' => 'mpn',
        'bpn' => 'bpn',
    ];

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public static function fromRest(array $data): array
    {
        $input = [];
        foreach (self::FIELD_MAP as $rest => $internal) {
            if (array_key_exists($rest, $data)) {
                $input[$internal] = $data[$rest];
            }
        }

        if (array_key_exists('translations', $data) && is_array($data['translations'])) {
            $input['translations'] = array_map(static function (mixed $translation): array {
                if (! is_array($translation)) {
                    return [];
                }

                $mapped = [];
                foreach ([
                    'locale' => 'locale',
                    'title' => 'title',
                    'slug' => 'slug',
                    'description' => 'description',
                    'seo_title' => 'seoTitle',
                    'seo_description' => 'seoDescription',
                    'lock_it' => 'lockIt',
                ] as $rest => $internal) {
                    if (array_key_exists($rest, $translation)) {
                        $mapped[$internal] = $translation[$rest];
                    }
                }

                return $mapped;
            }, $data['translations']);
        }

        return $input;
    }
}
