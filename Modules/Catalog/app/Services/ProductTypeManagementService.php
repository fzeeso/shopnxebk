<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use App\Support\Translations\TranslationCoordinator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Models\User;
use Modules\Catalog\Models\PlatformTaxonomyNode;
use Modules\Catalog\Models\ProductType;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Models\Store;

final readonly class ProductTypeManagementService
{
    private const TRANSLATION_FIELDS = ['name', 'description'];

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
            'filter.code' => ['sometimes', 'nullable', 'string', 'max:100'],
            'filter.platformTaxonomyNodeId' => ['sometimes', 'nullable', 'ulid'],
            'filter.isActive' => ['sometimes', 'boolean'],
            'sortBy' => ['sometimes', 'in:sortOrder,code,createdAt,updatedAt'],
            'sortDirection' => ['sometimes', 'in:ASC,DESC,asc,desc'],
        ])->validate();
        $filter = $data['filter'] ?? [];
        $query = ProductType::query()
            ->where('store_id', $store->getKey())
            ->with(['platformTaxonomyNode', 'translations'])
            ->withCount('products');

        if (($filter['search'] ?? null) !== null && trim((string) $filter['search']) !== '') {
            $search = trim((string) $filter['search']);
            $query->whereHas('translations', function ($query) use ($filter, $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'ILIKE', "%{$search}%")
                        ->orWhere('slug', 'ILIKE', "%{$search}%");
                });
                if (($filter['locale'] ?? null) !== null) {
                    $query->whereRaw('LOWER(locale) = ?', [$this->localeKey((string) $filter['locale'])]);
                }
            });
        }
        if (($filter['code'] ?? null) !== null) {
            $query->where('code', trim((string) $filter['code']));
        }
        if (($filter['platformTaxonomyNodeId'] ?? null) !== null) {
            $query->where(
                'platform_taxonomy_node_id',
                $this->platformTaxonomyNode((string) $filter['platformTaxonomyNodeId'])->getKey(),
            );
        }
        if (array_key_exists('isActive', $filter)) {
            $query->where('is_active', (bool) $filter['isActive']);
        }

        $sortColumn = match ($data['sortBy'] ?? 'sortOrder') {
            'code' => 'code',
            'createdAt' => 'created_at',
            'updatedAt' => 'updated_at',
            default => 'sort_order',
        };
        $query->orderBy($sortColumn, strtolower((string) ($data['sortDirection'] ?? 'ASC')))
            ->orderBy('id');

        return $query->paginate((int) ($data['perPage'] ?? 20), ['*'], 'page', (int) ($data['page'] ?? 1));
    }

    public function show(User $user, string $publicId): ProductType
    {
        $store = $this->store($user, false);

        return $this->productType($store, $publicId)
            ->load(['platformTaxonomyNode', 'translations'])
            ->loadCount('products');
    }

    /** @param array<string, mixed> $input */
    public function create(User $user, array $input): ProductType
    {
        $store = $this->store($user, true);
        $data = $this->validate($input, true);

        return DB::transaction(function () use ($data, $store, $user): ProductType {
            $taxonomyNode = ($data['platformTaxonomyNodeId'] ?? null) === null
                ? null
                : $this->platformTaxonomyNode((string) $data['platformTaxonomyNodeId']);
            $productType = ProductType::query()->create([
                'store_id' => $store->getKey(),
                'code' => trim((string) $data['code']),
                'platform_taxonomy_node_id' => $taxonomyNode?->getKey(),
                'is_active' => $data['isActive'] ?? true,
                'sort_order' => $data['sortOrder'] ?? 0,
            ]);
            $sourceLocale = $this->translations->sync(
                $store,
                'product_type_translations',
                'product_type_id',
                (int) $productType->getKey(),
                $this->translationRows($data['translations']),
                self::TRANSLATION_FIELDS,
                ['name'],
            );
            $request = $this->translationCoordinator->request(
                store: $store,
                contentType: 'product_type',
                contentId: (int) $productType->getKey(),
                expectedSourceLocale: $sourceLocale,
                requestedBy: (int) $user->getKey(),
            );

            return $productType->load(['platformTaxonomyNode', 'translations'])
                ->loadCount('products')
                ->setRelation('translationRequest', $request);
        });
    }

    /** @param array<string, mixed> $input */
    public function update(User $user, string $publicId, array $input): ProductType
    {
        $store = $this->store($user, true);
        $productType = $this->productType($store, $publicId);
        $data = $this->validate($input, false);
        if ($data === []) {
            throw ValidationException::withMessages(['input' => ['At least one field must be supplied.']]);
        }

        return DB::transaction(function () use ($productType, $data, $store, $user): ProductType {
            $attributes = [];
            foreach (['code' => 'code', 'isActive' => 'is_active', 'sortOrder' => 'sort_order'] as $input => $column) {
                if (array_key_exists($input, $data)) {
                    $attributes[$column] = $input === 'code' ? trim((string) $data[$input]) : $data[$input];
                }
            }
            if (array_key_exists('platformTaxonomyNodeId', $data)) {
                $attributes['platform_taxonomy_node_id'] = $data['platformTaxonomyNodeId'] === null
                    ? null
                    : $this->platformTaxonomyNode((string) $data['platformTaxonomyNodeId'])->getKey();
            }
            $productType->fill($attributes)->save();

            if (isset($data['translations'])) {
                $sourceLocale = $this->translations->sync(
                    $store,
                    'product_type_translations',
                    'product_type_id',
                    (int) $productType->getKey(),
                    $this->translationRows($data['translations']),
                    self::TRANSLATION_FIELDS,
                    ['name'],
                );
                $missingOnly = false;
            } else {
                $sourceLocale = $this->translations->sourceLocale(
                    $store,
                    'product_type_translations',
                    'product_type_id',
                    (int) $productType->getKey(),
                );
                $missingOnly = true;
            }
            $request = $this->translationCoordinator->request(
                store: $store,
                contentType: 'product_type',
                contentId: (int) $productType->getKey(),
                expectedSourceLocale: $sourceLocale,
                missingOnly: $missingOnly,
                requestedBy: (int) $user->getKey(),
            );

            return $productType->refresh()->load(['platformTaxonomyNode', 'translations'])
                ->loadCount('products')
                ->setRelation('translationRequest', $request);
        });
    }

    public function delete(User $user, string $publicId): void
    {
        $store = $this->store($user, true);
        $this->productType($store, $publicId)->delete();
    }

    private function store(User $user, bool $manage): Store
    {
        $store = $this->context->require();
        $manage ? $this->access->ensureCanManageProducts($user, $store) : $this->access->ensureCanView($user, $store);

        return $store;
    }

    private function productType(Store $store, string $publicId): ProductType
    {
        return ProductType::query()
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

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function validate(array $input, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return Validator::make($input, [
            'code' => [$required, 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/'],
            'platformTaxonomyNodeId' => ['sometimes', 'nullable', 'ulid'],
            'isActive' => ['sometimes', 'boolean'],
            'sortOrder' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'translations' => [$required, 'array', 'min:1'],
            'translations.*.locale' => ['required_with:translations', 'string', 'max:35', 'regex:/^[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})*$/'],
            'translations.*.name' => ['required_with:translations', 'string', 'max:255'],
            'translations.*.slug' => ['required_with:translations', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string'],
            'translations.*.lockIt' => ['sometimes', 'boolean'],
        ])->validate();
    }

    /** @param list<array<string, mixed>> $translations @return list<array<string, mixed>> */
    private function translationRows(array $translations): array
    {
        return array_map(static fn (array $translation): array => [
            'locale' => $translation['locale'],
            'name' => $translation['name'],
            'slug' => $translation['slug'],
            'description' => $translation['description'] ?? null,
            ...Arr::has($translation, 'lockIt') ? ['lock_it' => $translation['lockIt']] : [],
        ], $translations);
    }

    private function localeKey(string $locale): string
    {
        return strtolower(str_replace('-', '_', trim($locale)));
    }
}
