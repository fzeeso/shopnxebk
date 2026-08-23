<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers\Api\V1;

use App\Http\Requests\ProductIndexRequest;
use App\Http\Requests\ProductWriteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Catalog\Http\Resources\ProductResource;
use Modules\Catalog\Services\ProductManagementService;

final class ProductController extends Controller
{
    private const INPUT_MAP = [
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

    public function index(ProductIndexRequest $request, ProductManagementService $service): JsonResponse
    {
        $data = $request->validated();
        $filter = [];
        foreach ([
            'search' => 'search',
            'locale' => 'locale',
            'sku' => 'sku',
            'status' => 'status',
            'fulfillment_type' => 'fulfillmentType',
            'condition' => 'condition',
            'is_featured' => 'isFeatured',
            'brand_id' => 'brandId',
            'category_id' => 'categoryId',
        ] as $rest => $internal) {
            if (array_key_exists($rest, $data)) {
                $filter[$internal] = $data[$rest];
            }
        }
        $sortBy = match ($data['sort_by'] ?? 'created_at') {
            'created_at' => 'createdAt',
            'updated_at' => 'updatedAt',
            'published_at' => 'publishedAt',
            'sort_order' => 'sortOrder',
            default => $data['sort_by'] ?? 'createdAt',
        };

        return ProductResource::collection($service->list($this->user($request), [
            'page' => $data['page'] ?? 1,
            'perPage' => $data['per_page'] ?? 25,
            'filter' => $filter,
            'sortBy' => $sortBy,
            'sortDirection' => $data['sort_direction'] ?? 'desc',
        ]))->response();
    }

    public function store(ProductWriteRequest $request, ProductManagementService $service): JsonResponse
    {
        return response()->json([
            'data' => new ProductResource(
                $service->create($this->user($request), $this->serviceInput($request->validated())),
            ),
        ], 201);
    }

    public function show(Request $request, string $product, ProductManagementService $service): JsonResponse
    {
        return response()->json([
            'data' => new ProductResource($service->show($this->user($request), $product)),
        ]);
    }

    public function update(
        ProductWriteRequest $request,
        string $product,
        ProductManagementService $service,
    ): JsonResponse {
        return response()->json([
            'data' => new ProductResource(
                $service->update(
                    $this->user($request),
                    $product,
                    $this->serviceInput($request->validated()),
                ),
            ),
        ]);
    }

    public function destroy(Request $request, string $product, ProductManagementService $service): JsonResponse
    {
        $service->delete($this->user($request), $product);

        return response()->json(null, 204);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function serviceInput(array $data): array
    {
        $input = [];
        foreach (self::INPUT_MAP as $rest => $internal) {
            if (array_key_exists($rest, $data)) {
                $input[$internal] = $data[$rest];
            }
        }
        if (array_key_exists('translations', $data)) {
            $input['translations'] = array_map(static function (array $translation): array {
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

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
