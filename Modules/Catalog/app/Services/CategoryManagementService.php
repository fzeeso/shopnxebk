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
use Modules\Catalog\Models\Category;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Models\Store;

final readonly class CategoryManagementService
{
    private const TRANSLATION_FIELDS = [
        'title',
        'description',
        'image_url',
        'banner_url',
        'seo_title',
        'seo_description',
        'page_title',
        'search_keywords',
        'category_template',
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
            'filter.parentId' => ['sometimes', 'nullable', 'ulid'],
            'filter.rootOnly' => ['sometimes', 'boolean'],
            'filter.isActive' => ['sometimes', 'boolean'],
            'sortBy' => ['sometimes', 'in:sortOrder,createdAt,updatedAt'],
            'sortDirection' => ['sometimes', 'in:ASC,DESC,asc,desc'],
        ])->validate();
        $filter = $data['filter'] ?? [];
        $query = Category::query()
            ->where('store_id', $store->getKey())
            ->with(['translations', 'parent.translations'])
            ->withCount(['children', 'products']);

        if (($filter['search'] ?? null) !== null && trim((string) $filter['search']) !== '') {
            $search = trim((string) $filter['search']);
            $query->whereHas('translations', function ($query) use ($filter, $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('title', 'ILIKE', "%{$search}%")
                        ->orWhere('slug', 'ILIKE', "%{$search}%");
                });
                if (($filter['locale'] ?? null) !== null) {
                    $query->whereRaw('LOWER(locale) = ?', [$this->localeKey((string) $filter['locale'])]);
                }
            });
        }
        if (array_key_exists('isActive', $filter)) {
            $query->where('is_active', (bool) $filter['isActive']);
        }
        if (($filter['rootOnly'] ?? false) === true) {
            $query->whereNull('parent_id');
        } elseif (($filter['parentId'] ?? null) !== null) {
            $query->where('parent_id', $this->category($store, (string) $filter['parentId'])->getKey());
        }

        $sortColumn = match ($data['sortBy'] ?? 'sortOrder') {
            'createdAt' => 'created_at',
            'updatedAt' => 'updated_at',
            default => 'sort_order',
        };
        $query->orderBy($sortColumn, strtolower((string) ($data['sortDirection'] ?? 'ASC')))
            ->orderBy('id');

        return $query->paginate((int) ($data['perPage'] ?? 20), ['*'], 'page', (int) ($data['page'] ?? 1));
    }

    public function show(User $user, string $publicId): Category
    {
        $store = $this->store($user, false);

        return $this->category($store, $publicId)
            ->load(['translations', 'parent.translations', 'children.translations'])
            ->loadCount(['children', 'products']);
    }

    /** @param array<string, mixed> $input */
    public function create(User $user, array $input): Category
    {
        $store = $this->store($user, true);
        $data = $this->validate($input, true);

        return DB::transaction(function () use ($data, $store, $user): Category {
            $parent = ($data['parentId'] ?? null) === null
                ? null
                : $this->category($store, (string) $data['parentId']);
            $category = Category::query()->create([
                'store_id' => $store->getKey(),
                'parent_id' => $parent?->getKey(),
                'image_url' => $data['imageUrl'] ?? null,
                'is_active' => $data['isActive'] ?? true,
                'sort_order' => $data['sortOrder'] ?? 0,
            ]);
            $sourceLocale = $this->translations->sync(
                $store,
                'category_translations',
                'category_id',
                (int) $category->getKey(),
                $this->translationRows($data['translations']),
                self::TRANSLATION_FIELDS,
                ['title'],
            );
            $request = $this->translationCoordinator->request(
                store: $store,
                contentType: 'category',
                contentId: (int) $category->getKey(),
                expectedSourceLocale: $sourceLocale,
                requestedBy: (int) $user->getKey(),
            );

            return $category->load(['translations', 'parent.translations'])
                ->loadCount(['children', 'products'])
                ->setRelation('translationRequest', $request);
        });
    }

    /** @param array<string, mixed> $input */
    public function update(User $user, string $publicId, array $input): Category
    {
        $store = $this->store($user, true);
        $category = $this->category($store, $publicId);
        $data = $this->validate($input, false);
        if ($data === []) {
            throw ValidationException::withMessages(['input' => ['At least one field must be supplied.']]);
        }

        return DB::transaction(function () use ($category, $data, $store, $user): Category {
            $attributes = [];
            foreach (['imageUrl' => 'image_url', 'isActive' => 'is_active', 'sortOrder' => 'sort_order'] as $input => $column) {
                if (array_key_exists($input, $data)) {
                    $attributes[$column] = $data[$input];
                }
            }
            if (array_key_exists('parentId', $data)) {
                $parent = $data['parentId'] === null ? null : $this->category($store, (string) $data['parentId']);
                $this->ensureValidParent($category, $parent);
                $attributes['parent_id'] = $parent?->getKey();
            }
            $category->fill($attributes)->save();

            if (isset($data['translations'])) {
                $sourceLocale = $this->translations->sync(
                    $store,
                    'category_translations',
                    'category_id',
                    (int) $category->getKey(),
                    $this->translationRows($data['translations']),
                    self::TRANSLATION_FIELDS,
                    ['title'],
                );
                $missingOnly = false;
            } else {
                $sourceLocale = $this->translations->sourceLocale(
                    $store,
                    'category_translations',
                    'category_id',
                    (int) $category->getKey(),
                );
                $missingOnly = true;
            }
            $request = $this->translationCoordinator->request(
                store: $store,
                contentType: 'category',
                contentId: (int) $category->getKey(),
                expectedSourceLocale: $sourceLocale,
                missingOnly: $missingOnly,
                requestedBy: (int) $user->getKey(),
            );

            return $category->refresh()->load(['translations', 'parent.translations'])
                ->loadCount(['children', 'products'])
                ->setRelation('translationRequest', $request);
        });
    }

    public function delete(User $user, string $publicId): void
    {
        $store = $this->store($user, true);
        $this->category($store, $publicId)->delete();
    }

    private function store(User $user, bool $manage): Store
    {
        $store = $this->context->require();
        $manage ? $this->access->ensureCanManageProducts($user, $store) : $this->access->ensureCanView($user, $store);

        return $store;
    }

    private function category(Store $store, string $publicId): Category
    {
        return Category::query()
            ->where('store_id', $store->getKey())
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    private function ensureValidParent(Category $category, ?Category $parent): void
    {
        for ($candidate = $parent; $candidate instanceof Category; $candidate = $candidate->parent()->first()) {
            if ($candidate->is($category)) {
                throw ValidationException::withMessages([
                    'input.parentId' => ['A category cannot be its own parent or a child of one of its descendants.'],
                ]);
            }
        }
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function validate(array $input, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return Validator::make($input, [
            'parentId' => ['sometimes', 'nullable', 'ulid'],
            'imageUrl' => ['sometimes', 'nullable', 'string', 'max:500', 'regex:/^(?:\/|https?:\/\/)/i'],
            'isActive' => ['sometimes', 'boolean'],
            'sortOrder' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'translations' => [$required, 'array', 'min:1'],
            'translations.*.locale' => ['required_with:translations', 'string', 'max:35', 'regex:/^[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})*$/'],
            'translations.*.title' => ['required_with:translations', 'string', 'max:255'],
            'translations.*.slug' => ['required_with:translations', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string'],
            'translations.*.imageUrl' => ['nullable', 'string', 'max:500', 'regex:/^(?:\/|https?:\/\/)/i'],
            'translations.*.bannerUrl' => ['nullable', 'string', 'max:500', 'regex:/^(?:\/|https?:\/\/)/i'],
            'translations.*.seoTitle' => ['nullable', 'string', 'max:255'],
            'translations.*.seoDescription' => ['nullable', 'string'],
            'translations.*.pageTitle' => ['nullable', 'string', 'max:255'],
            'translations.*.searchKeywords' => ['nullable', 'string'],
            'translations.*.categoryTemplate' => ['nullable', 'string', 'max:120'],
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
            'image_url' => $translation['imageUrl'] ?? null,
            'banner_url' => $translation['bannerUrl'] ?? null,
            'seo_title' => $translation['seoTitle'] ?? null,
            'seo_description' => $translation['seoDescription'] ?? null,
            'page_title' => $translation['pageTitle'] ?? null,
            'search_keywords' => $translation['searchKeywords'] ?? null,
            'category_template' => $translation['categoryTemplate'] ?? null,
            ...Arr::has($translation, 'lockIt') ? ['lock_it' => $translation['lockIt']] : [],
        ], $translations);
    }

    private function localeKey(string $locale): string
    {
        return strtolower(str_replace('-', '_', trim($locale)));
    }
}
