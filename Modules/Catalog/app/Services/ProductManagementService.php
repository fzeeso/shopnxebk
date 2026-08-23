<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use App\Models\Brand;
use App\Support\Translations\TranslationCoordinator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Models\User;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Models\PlatformTaxonomyNode;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductType;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Models\Store;

final readonly class ProductManagementService
{
    private const TRANSLATION_FIELDS = ['title', 'description', 'seo_title', 'seo_description'];

    private const COMMERCE_ATTRIBUTE_MAP = [
        'sku' => 'sku',
        'downloadFile' => 'downloadfile',
        'availability' => 'availability',
        'price' => 'price',
        'costPrice' => 'costprice',
        'retailPrice' => 'retailprice',
        'msrpPrice' => 'msrpprice',
        'salePrice' => 'saleprice',
        'calculatedPrice' => 'calculatedprice',
        'sortOrder' => 'sortorder',
        'isFeatured' => 'is_featured',
        'currentInventory' => 'currentinv',
        'lowInventory' => 'lowinv',
        'warranty' => 'warranty',
        'weight' => 'weight',
        'width' => 'width',
        'height' => 'height',
        'productDepth' => 'proddepth',
        'fixedShippingCost' => 'fixedshippingcost',
        'freeShipping' => 'freeshipping',
        'ratingTotal' => 'ratingtotal',
        'numRatings' => 'numratings',
        'numSold' => 'numsold',
        'numViews' => 'numviews',
        'allowPurchases' => 'allowpurchases',
        'hidePrice' => 'hideprice',
        'loginForPrice' => 'is_login_for_price',
        'globalSearch' => 'is_global_search',
        'condition' => 'condition',
        'showCondition' => 'showcondition',
        'preOrder' => 'pre_order',
        'releaseDate' => 'releasedate',
        'releaseDateRemove' => 'releasedateremove',
        'minQuantity' => 'minqty',
        'maxQuantity' => 'maxqty',
        'taxClassId' => 'tax_class_id',
        'showRelatedProduct' => 'show_related_product',
        'productPoints' => 'prodpoints',
        'reviewsOn' => 'reviews_on',
        'upc' => 'upc',
        'hsCode' => 'hs_code',
        'gtin' => 'gtin',
        'mpn' => 'mpn',
        'bpn' => 'bpn',
    ];

    public function __construct(
        private StoreContext $context,
        private CatalogAccessService $access,
        private CatalogTranslationManager $translations,
        private TranslationCoordinator $translationCoordinator,
    ) {}

    /** @param array<string, mixed> $arguments */
    public function list(User $user, array $arguments): LengthAwarePaginator
    {
        $store = $this->store($user, false);
        $data = Validator::make($arguments, [
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'filter.search' => ['sometimes', 'nullable', 'string', 'max:200'],
            'filter.locale' => ['sometimes', 'nullable', 'string', 'max:35'],
            'filter.sku' => ['sometimes', 'nullable', 'string', 'max:250'],
            'filter.status' => ['sometimes', 'nullable', 'in:draft,active,archived'],
            'filter.fulfillmentType' => ['sometimes', 'nullable', 'in:physical,digital,software,service'],
            'filter.condition' => ['sometimes', 'nullable', 'in:New,Used,Refurbished'],
            'filter.isFeatured' => ['sometimes', 'boolean'],
            'filter.brandId' => ['sometimes', 'nullable', 'ulid'],
            'filter.categoryId' => ['sometimes', 'nullable', 'ulid'],
            'sortBy' => ['sometimes', 'in:createdAt,updatedAt,status,publishedAt,price,sortOrder'],
            'sortDirection' => ['sometimes', 'in:ASC,DESC,asc,desc'],
        ])->validate();
        $filter = $data['filter'] ?? [];
        $query = Product::query()
            ->where('store_id', $store->getKey())
            ->with([
                'brand',
                'platformTaxonomyNode',
                'productType',
                'translations',
                'categories.parent',
                'categories.translations',
            ])
            ->withCount('categories');

        if (($filter['search'] ?? null) !== null && trim((string) $filter['search']) !== '') {
            $search = trim((string) $filter['search']);
            $query->where(function ($query) use ($filter, $search): void {
                $query->where('sku', 'ILIKE', "%{$search}%")
                    ->orWhere('upc', 'ILIKE', "%{$search}%")
                    ->orWhere('gtin', 'ILIKE', "%{$search}%")
                    ->orWhere('mpn', 'ILIKE', "%{$search}%")
                    ->orWhereHas('translations', function ($query) use ($filter, $search): void {
                        $query->where(function ($query) use ($search): void {
                            $query->where('title', 'ILIKE', "%{$search}%")
                                ->orWhere('slug', 'ILIKE', "%{$search}%");
                        });
                        if (($filter['locale'] ?? null) !== null) {
                            $query->whereRaw('LOWER(locale) = ?', [$this->localeKey((string) $filter['locale'])]);
                        }
                    });
            });
        }
        foreach ([
            'sku' => 'sku',
            'status' => 'status',
            'fulfillmentType' => 'fulfillment_type',
            'condition' => 'condition',
            'isFeatured' => 'is_featured',
        ] as $input => $column) {
            if (($filter[$input] ?? null) !== null) {
                $query->where($column, $filter[$input]);
            }
        }
        if (($filter['brandId'] ?? null) !== null) {
            $query->where('brand_id', $this->brand($store, (string) $filter['brandId'])->getKey());
        }
        if (($filter['categoryId'] ?? null) !== null) {
            $categoryId = $this->category($store, (string) $filter['categoryId'])->getKey();
            $query->whereHas('categories', fn ($query) => $query->where('categories.id', $categoryId));
        }

        $sortColumn = match ($data['sortBy'] ?? 'createdAt') {
            'updatedAt' => 'updated_at',
            'status' => 'status',
            'publishedAt' => 'published_at',
            'price' => 'price',
            'sortOrder' => 'sortorder',
            default => 'created_at',
        };
        $query->orderBy($sortColumn, strtolower((string) ($data['sortDirection'] ?? 'DESC')))
            ->orderByDesc('id');

        return $query->paginate((int) ($data['perPage'] ?? 20), ['*'], 'page', (int) ($data['page'] ?? 1));
    }

    public function show(User $user, string $publicId): Product
    {
        $store = $this->store($user, false);

        return $this->product($store, $publicId)->load([
            'brand',
            'platformTaxonomyNode',
            'productType',
            'translations',
            'categories.parent',
            'categories.translations',
        ]);
    }

    /** @param array<string, mixed> $input */
    public function create(User $user, array $input): Product
    {
        $store = $this->store($user, true);
        $data = $this->validate($input, true);

        return DB::transaction(function () use ($data, $store, $user): Product {
            $brand = ($data['brandId'] ?? null) === null ? null : $this->brand($store, (string) $data['brandId']);
            $taxonomyNode = ($data['platformTaxonomyNodeId'] ?? null) === null
                ? null
                : $this->platformTaxonomyNode((string) $data['platformTaxonomyNodeId']);
            $productType = ($data['productTypeId'] ?? null) === null
                ? null
                : $this->productType($store, (string) $data['productTypeId']);
            $status = (string) ($data['status'] ?? 'draft');
            $product = Product::query()->create([
                'store_id' => $store->getKey(),
                'brand_id' => $brand?->getKey(),
                'platform_taxonomy_node_id' => $taxonomyNode?->getKey(),
                'vendor' => $data['vendor'] ?? null,
                'product_type_id' => $productType?->getKey(),
                'fulfillment_type' => $data['fulfillmentType'] ?? 'physical',
                'track_inventory' => $data['trackInventory'] ?? true,
                'status' => $status,
                'has_variants' => false,
                'published_at' => $status === 'active' ? now() : null,
                ...$this->commerceAttributes($data),
            ]);
            $this->syncCategories(
                $product,
                $store,
                $data['categoryIds'] ?? [],
                $data['primaryCategoryId'] ?? null,
            );
            $sourceLocale = $this->translations->sync(
                $store,
                'product_translations',
                'product_id',
                (int) $product->getKey(),
                $this->translationRows($data['translations']),
                self::TRANSLATION_FIELDS,
                ['title'],
            );
            $request = $this->translationCoordinator->request(
                store: $store,
                contentType: 'product',
                contentId: (int) $product->getKey(),
                expectedSourceLocale: $sourceLocale,
                requestedBy: (int) $user->getKey(),
            );

            return $product->load([
                'brand',
                'platformTaxonomyNode',
                'productType',
                'translations',
                'categories.parent',
                'categories.translations',
            ])
                ->setRelation('translationRequest', $request);
        });
    }

    /** @param array<string, mixed> $input */
    public function update(User $user, string $publicId, array $input): Product
    {
        $store = $this->store($user, true);
        $product = $this->product($store, $publicId);
        $data = $this->validate($input, false);
        if ($data === []) {
            throw ValidationException::withMessages(['input' => ['At least one field must be supplied.']]);
        }

        return DB::transaction(function () use ($product, $data, $store, $user): Product {
            $attributes = [];
            foreach ([
                'vendor' => 'vendor',
                'fulfillmentType' => 'fulfillment_type',
                'trackInventory' => 'track_inventory',
                'status' => 'status',
            ] as $input => $column) {
                if (array_key_exists($input, $data)) {
                    $attributes[$column] = $data[$input];
                }
            }
            $attributes = [...$attributes, ...$this->commerceAttributes($data)];
            if (array_key_exists('brandId', $data)) {
                $attributes['brand_id'] = $data['brandId'] === null
                    ? null
                    : $this->brand($store, (string) $data['brandId'])->getKey();
            }
            if (array_key_exists('platformTaxonomyNodeId', $data)) {
                $attributes['platform_taxonomy_node_id'] = $data['platformTaxonomyNodeId'] === null
                    ? null
                    : $this->platformTaxonomyNode((string) $data['platformTaxonomyNodeId'])->getKey();
            }
            if (array_key_exists('productTypeId', $data)) {
                $attributes['product_type_id'] = $data['productTypeId'] === null
                    ? null
                    : $this->productType($store, (string) $data['productTypeId'])->getKey();
            }
            if (($attributes['status'] ?? null) === 'active' && $product->published_at === null) {
                $attributes['published_at'] = now();
            } elseif (($attributes['status'] ?? null) === 'draft') {
                $attributes['published_at'] = null;
            }
            $product->fill($attributes)->save();

            if (array_key_exists('categoryIds', $data) || array_key_exists('primaryCategoryId', $data)) {
                $categoryIds = $data['categoryIds'] ?? $product->categories()->pluck('categories.public_id')->all();
                $primaryId = array_key_exists('primaryCategoryId', $data)
                    ? $data['primaryCategoryId']
                    : $product->categories()->wherePivot('is_primary', true)->value('categories.public_id');
                $this->syncCategories($product, $store, $categoryIds, $primaryId);
            }

            if (isset($data['translations'])) {
                $sourceLocale = $this->translations->sync(
                    $store,
                    'product_translations',
                    'product_id',
                    (int) $product->getKey(),
                    $this->translationRows($data['translations']),
                    self::TRANSLATION_FIELDS,
                    ['title'],
                );
                $missingOnly = false;
            } else {
                $sourceLocale = $this->translations->sourceLocale(
                    $store,
                    'product_translations',
                    'product_id',
                    (int) $product->getKey(),
                );
                $missingOnly = true;
            }
            $request = $this->translationCoordinator->request(
                store: $store,
                contentType: 'product',
                contentId: (int) $product->getKey(),
                expectedSourceLocale: $sourceLocale,
                missingOnly: $missingOnly,
                requestedBy: (int) $user->getKey(),
            );

            return $product->refresh()->load([
                'brand',
                'platformTaxonomyNode',
                'productType',
                'translations',
                'categories.parent',
                'categories.translations',
            ])
                ->setRelation('translationRequest', $request);
        });
    }

    public function delete(User $user, string $publicId): void
    {
        $store = $this->store($user, true);
        $this->product($store, $publicId)->delete();
    }

    private function store(User $user, bool $manage): Store
    {
        $store = $this->context->require();
        $manage ? $this->access->ensureCanManageProducts($user, $store) : $this->access->ensureCanView($user, $store);

        return $store;
    }

    private function product(Store $store, string $publicId): Product
    {
        return Product::query()
            ->where('store_id', $store->getKey())
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    private function category(Store $store, string $publicId): Category
    {
        return Category::query()
            ->where('store_id', $store->getKey())
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    private function brand(Store $store, string $publicId): Brand
    {
        return Brand::query()
            ->where('store_id', $store->getKey())
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    private function platformTaxonomyNode(string $publicId): PlatformTaxonomyNode
    {
        return PlatformTaxonomyNode::query()
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    private function productType(Store $store, string $publicId): ProductType
    {
        return ProductType::query()
            ->where('store_id', $store->getKey())
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    /** @param list<string> $categoryIds */
    private function syncCategories(Product $product, Store $store, array $categoryIds, ?string $primaryCategoryId): void
    {
        $categoryIds = array_values(array_unique(array_map('strval', $categoryIds)));
        if ($primaryCategoryId !== null && ! in_array($primaryCategoryId, $categoryIds, true)) {
            throw ValidationException::withMessages([
                'input.primaryCategoryId' => ['The primary category must also appear in categoryIds.'],
            ]);
        }
        $categories = Category::query()
            ->where('store_id', $store->getKey())
            ->whereIn('public_id', $categoryIds)
            ->get()
            ->keyBy('public_id');
        if ($categories->count() !== count($categoryIds)) {
            throw ValidationException::withMessages([
                'input.categoryIds' => ['Every category must exist in the selected Store.'],
            ]);
        }

        DB::table('product_categories')->where('product_id', $product->getKey())->delete();
        $now = now();
        $rows = [];
        foreach ($categoryIds as $index => $publicId) {
            $rows[] = [
                'store_id' => $store->getKey(),
                'product_id' => $product->getKey(),
                'category_id' => $categories->get($publicId)->getKey(),
                'sort_order' => $index,
                'is_primary' => $publicId === $primaryCategoryId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if ($rows !== []) {
            DB::table('product_categories')->insert($rows);
        }
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function validate(array $input, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return Validator::make($input, [
            'brandId' => ['sometimes', 'nullable', 'ulid'],
            'platformTaxonomyNodeId' => ['sometimes', 'nullable', 'ulid'],
            'vendor' => ['sometimes', 'nullable', 'string', 'max:255'],
            'productTypeId' => ['sometimes', 'nullable', 'ulid'],
            'fulfillmentType' => ['sometimes', 'in:physical,digital,software,service'],
            'trackInventory' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'in:draft,active,archived'],
            'sku' => ['sometimes', 'string', 'max:250'],
            'downloadFile' => ['sometimes', 'string', 'max:250'],
            'availability' => ['sometimes', 'string', 'max:250'],
            'price' => ['sometimes', 'numeric', 'decimal:0,4', 'min:0', 'max:9999999999999999.9999'],
            'costPrice' => ['sometimes', 'numeric', 'decimal:0,4', 'min:0', 'max:9999999999999999.9999'],
            'retailPrice' => ['sometimes', 'numeric', 'decimal:0,4', 'min:0', 'max:9999999999999999.9999'],
            'msrpPrice' => ['sometimes', 'numeric', 'decimal:0,4', 'min:0', 'max:9999999999999999.9999'],
            'salePrice' => ['sometimes', 'numeric', 'decimal:0,4', 'min:0', 'max:9999999999999999.9999'],
            'calculatedPrice' => ['sometimes', 'numeric', 'decimal:0,4', 'min:0', 'max:9999999999999999.9999'],
            'sortOrder' => ['sometimes', 'integer'],
            'isFeatured' => ['sometimes', 'integer', 'between:0,1'],
            'currentInventory' => ['sometimes', 'integer'],
            'lowInventory' => ['sometimes', 'integer'],
            'warranty' => ['sometimes', 'nullable', 'string'],
            'weight' => ['sometimes', 'numeric', 'decimal:0,4', 'min:0', 'max:9999999999999999.9999'],
            'width' => ['sometimes', 'numeric', 'decimal:0,4', 'min:0', 'max:9999999999999999.9999'],
            'height' => ['sometimes', 'numeric', 'decimal:0,4', 'min:0', 'max:9999999999999999.9999'],
            'productDepth' => ['sometimes', 'numeric', 'decimal:0,4', 'min:0', 'max:9999999999999999.9999'],
            'fixedShippingCost' => ['sometimes', 'numeric', 'decimal:0,4', 'min:0', 'max:9999999999999999.9999'],
            'freeShipping' => ['sometimes', 'integer', 'between:0,1'],
            'ratingTotal' => ['sometimes', 'integer', 'min:0', 'max:2147483647'],
            'numRatings' => ['sometimes', 'integer', 'min:0', 'max:2147483647'],
            'numSold' => ['sometimes', 'integer', 'min:0', 'max:2147483647'],
            'numViews' => ['sometimes', 'integer', 'min:0', 'max:2147483647'],
            'allowPurchases' => ['sometimes', 'integer', 'between:0,1'],
            'hidePrice' => ['sometimes', 'integer', 'between:0,1'],
            'loginForPrice' => ['sometimes', 'integer', 'between:0,1'],
            'globalSearch' => ['sometimes', 'integer', 'between:0,1'],
            'condition' => ['sometimes', 'in:New,Used,Refurbished'],
            'showCondition' => ['sometimes', 'integer', 'between:0,1'],
            'preOrder' => ['sometimes', 'integer', 'between:0,1'],
            'releaseDate' => ['sometimes', 'nullable', 'date'],
            'releaseDateRemove' => ['sometimes', 'integer', 'between:0,1'],
            'minQuantity' => ['sometimes', 'integer', 'min:0', 'max:2147483647'],
            'maxQuantity' => ['sometimes', 'integer', 'min:0', 'max:2147483647'],
            'taxClassId' => ['sometimes', 'integer', 'min:0', 'max:2147483647'],
            'showRelatedProduct' => ['sometimes', 'integer', 'min:0', 'max:2147483647'],
            'productPoints' => ['sometimes', 'integer', 'min:0', 'max:2147483647'],
            'reviewsOn' => ['sometimes', 'integer', 'between:0,1'],
            'upc' => ['sometimes', 'nullable', 'string', 'max:32'],
            'hsCode' => ['sometimes', 'nullable', 'string', 'max:32'],
            'gtin' => ['sometimes', 'nullable', 'string', 'max:32'],
            'mpn' => ['sometimes', 'nullable', 'string', 'max:32'],
            'bpn' => ['sometimes', 'nullable', 'string', 'max:32'],
            'categoryIds' => ['sometimes', 'array', 'max:100'],
            'categoryIds.*' => ['required', 'ulid', 'distinct'],
            'primaryCategoryId' => ['sometimes', 'nullable', 'ulid'],
            'translations' => [$required, 'array', 'min:1'],
            'translations.*.locale' => ['required_with:translations', 'string', 'max:35', 'regex:/^[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})*$/'],
            'translations.*.title' => ['required_with:translations', 'string', 'max:255'],
            'translations.*.slug' => ['required_with:translations', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string'],
            'translations.*.seoTitle' => ['nullable', 'string', 'max:255'],
            'translations.*.seoDescription' => ['nullable', 'string'],
            'translations.*.lockIt' => ['sometimes', 'boolean'],
        ])->validate();
    }

    /** @param list<array<string, mixed>> $translations @return list<array<string, mixed>> */
    private function translationRows(array $translations): array
    {
        return array_map(static fn (array $translation): array => [
            'locale' => $translation['locale'],
            'title' => $translation['title'],
            'slug' => $translation['slug'],
            'description' => $translation['description'] ?? null,
            'seo_title' => $translation['seoTitle'] ?? null,
            'seo_description' => $translation['seoDescription'] ?? null,
            ...Arr::has($translation, 'lockIt') ? ['lock_it' => $translation['lockIt']] : [],
        ], $translations);
    }

    private function localeKey(string $locale): string
    {
        return strtolower(str_replace('-', '_', trim($locale)));
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function commerceAttributes(array $data): array
    {
        $attributes = [];
        foreach (self::COMMERCE_ATTRIBUTE_MAP as $input => $column) {
            if (array_key_exists($input, $data)) {
                $attributes[$column] = $data[$input];
            }
        }

        return $attributes;
    }
}
