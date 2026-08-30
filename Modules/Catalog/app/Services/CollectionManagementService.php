<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use App\Support\Translations\TranslationCoordinator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Models\User;
use Modules\Catalog\Models\Collection as CatalogCollection;
use Modules\Catalog\Models\CollectionAiJob;
use Modules\Catalog\Models\CollectionRule;
use Modules\Catalog\Models\Product;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Models\Store;

final readonly class CollectionManagementService
{
    private const TRANSLATION_FIELDS = ['title', 'description', 'seo_title', 'seo_description'];

    private const STRING_RULES = [
        'vendor' => ['equals', 'not_equals', 'contains'],
        'sku' => ['equals', 'not_equals', 'contains'],
        'status' => ['equals', 'not_equals'],
        'product_type' => ['equals', 'not_equals', 'contains'],
        'title' => ['equals', 'not_equals', 'contains'],
        'brand' => ['equals', 'not_equals', 'contains'],
        'tag' => ['equals', 'not_equals', 'contains'],
    ];

    private const NUMBER_RULES = [
        'price' => ['equals', 'not_equals', 'greater_than', 'greater_than_or_equal', 'less_than', 'less_than_or_equal'],
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
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:200'],
            'locale' => ['sometimes', 'nullable', 'string', 'max:35'],
            'parent_id' => ['sometimes', 'nullable', 'ulid'],
            'root_only' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'collection_type' => ['sometimes', 'nullable', 'in:manual,rule_based,ai_generated'],
            'sort_by' => ['sometimes', 'in:sort_order,created_at,updated_at'],
            'sort_direction' => ['sometimes', 'in:asc,desc'],
        ])->validate();

        $query = CatalogCollection::query()
            ->where('store_id', $store->getKey())
            ->with(['translations', 'parent'])
            ->withCount(['children', 'products', 'rules', 'aiJobs']);

        if (($data['search'] ?? null) !== null && trim((string) $data['search']) !== '') {
            $search = trim((string) $data['search']);
            $query->whereHas('translations', function (Builder $query) use ($data, $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'ILIKE', "%{$search}%")
                        ->orWhere('slug', 'ILIKE', "%{$search}%");
                });
                if (($data['locale'] ?? null) !== null) {
                    $query->whereRaw('LOWER(locale) = ?', [$this->localeKey((string) $data['locale'])]);
                }
            });
        }
        if (array_key_exists('is_active', $data)) {
            $query->where('is_active', (bool) $data['is_active']);
        }
        if (($data['collection_type'] ?? null) !== null) {
            $query->where('collection_type', $data['collection_type']);
        }
        if (($data['root_only'] ?? false) === true) {
            $query->whereNull('parent_id');
        } elseif (($data['parent_id'] ?? null) !== null) {
            $query->where('parent_id', $this->collection($store, (string) $data['parent_id'])->getKey());
        }

        $sortColumn = $data['sort_by'] ?? 'sort_order';
        $query->orderBy($sortColumn, $data['sort_direction'] ?? 'asc')->orderBy('id');

        return $query->paginate(
            (int) ($data['per_page'] ?? 25),
            ['*'],
            'page',
            (int) ($data['page'] ?? 1),
        );
    }

    public function show(User $user, string $publicId): CatalogCollection
    {
        $store = $this->store($user, false);

        return $this->loaded($this->collection($store, $publicId));
    }

    /** @param array<string, mixed> $input */
    public function create(User $user, array $input): CatalogCollection
    {
        $store = $this->store($user, true);
        $data = $this->validateWrite($input, true);

        return DB::transaction(function () use ($data, $store, $user): CatalogCollection {
            $parent = ($data['parent_id'] ?? null) === null
                ? null
                : $this->collection($store, (string) $data['parent_id']);
            $collection = CatalogCollection::query()->create([
                'store_id' => $store->getKey(),
                'parent_id' => $parent?->getKey(),
                'image_url' => $data['image_url'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $data['sort_order'] ?? 0,
                'collection_type' => $data['collection_type'] ?? 'manual',
                'rules_match_type' => $data['rules_match_type'] ?? 'all',
                'ai_prompt' => $data['ai_prompt'] ?? null,
                'ai_model' => $data['ai_model'] ?? null,
            ]);
            $sourceLocale = $this->translations->sync(
                $store,
                'collection_translations',
                'collection_id',
                (int) $collection->getKey(),
                $this->translationRows($data['translations']),
                self::TRANSLATION_FIELDS,
                ['title'],
            );
            if (array_key_exists('rules', $data)) {
                $this->syncRules($collection, $data['rules']);
            }
            if (array_key_exists('products', $data)) {
                $this->syncManualProducts($collection, $store, $data['products']);
            }
            $request = $this->translationCoordinator->request(
                store: $store,
                contentType: 'collection',
                contentId: (int) $collection->getKey(),
                expectedSourceLocale: $sourceLocale,
                requestedBy: (int) $user->getKey(),
            );

            return $this->loaded($collection)->setRelation('translationRequest', $request);
        });
    }

    /** @param array<string, mixed> $input */
    public function update(User $user, string $publicId, array $input): CatalogCollection
    {
        $store = $this->store($user, true);
        $collection = $this->collection($store, $publicId);
        $data = $this->validateWrite($input, false);
        if ($data === []) {
            throw ValidationException::withMessages(['input' => ['At least one field must be supplied.']]);
        }

        return DB::transaction(function () use ($collection, $data, $store, $user): CatalogCollection {
            $attributes = [];
            foreach ([
                'image_url',
                'is_active',
                'sort_order',
                'collection_type',
                'rules_match_type',
                'ai_prompt',
                'ai_model',
            ] as $field) {
                if (array_key_exists($field, $data)) {
                    $attributes[$field] = $data[$field];
                }
            }
            if (array_key_exists('parent_id', $data)) {
                $parent = $data['parent_id'] === null
                    ? null
                    : $this->collection($store, (string) $data['parent_id']);
                $this->ensureValidParent($collection, $parent);
                $attributes['parent_id'] = $parent?->getKey();
            }
            $collection->fill($attributes)->save();

            if ($collection->collection_type === 'manual' && ($data['rules'] ?? []) !== []) {
                throw ValidationException::withMessages([
                    'rules' => ['Manual collections cannot contain rules.'],
                ]);
            }
            if ($collection->collection_type === 'ai_generated' && trim((string) $collection->ai_prompt) === '') {
                throw ValidationException::withMessages([
                    'ai_prompt' => ['An AI-generated collection requires an AI prompt.'],
                ]);
            }

            if (array_key_exists('collection_type', $data) && $collection->collection_type === 'manual') {
                $collection->rules()->delete();
                DB::table('product_collections')
                    ->where('store_id', $store->getKey())
                    ->where('collection_id', $collection->getKey())
                    ->whereIn('added_by', ['rule', 'ai'])
                    ->where('is_pinned', false)
                    ->delete();
            }

            if (array_key_exists('rules', $data)) {
                $this->syncRules($collection, $data['rules']);
            }
            if (array_key_exists('products', $data)) {
                $this->syncManualProducts($collection, $store, $data['products']);
            }
            if (isset($data['translations'])) {
                $sourceLocale = $this->translations->sync(
                    $store,
                    'collection_translations',
                    'collection_id',
                    (int) $collection->getKey(),
                    $this->translationRows($data['translations']),
                    self::TRANSLATION_FIELDS,
                    ['title'],
                );
                $missingOnly = false;
            } else {
                $sourceLocale = $this->translations->sourceLocale(
                    $store,
                    'collection_translations',
                    'collection_id',
                    (int) $collection->getKey(),
                );
                $missingOnly = true;
            }
            $request = $this->translationCoordinator->request(
                store: $store,
                contentType: 'collection',
                contentId: (int) $collection->getKey(),
                expectedSourceLocale: $sourceLocale,
                missingOnly: $missingOnly,
                requestedBy: (int) $user->getKey(),
            );

            return $this->loaded($collection->refresh())->setRelation('translationRequest', $request);
        });
    }

    public function delete(User $user, string $publicId): void
    {
        $store = $this->store($user, true);
        $this->collection($store, $publicId)->delete();
    }

    /** @param list<array<string, mixed>> $rules */
    public function replaceRules(User $user, string $publicId, array $rules): CatalogCollection
    {
        $store = $this->store($user, true);
        $collection = $this->collection($store, $publicId);
        $rules = $this->validatedRules($rules);
        $this->ensureAutomated($collection);

        return DB::transaction(function () use ($collection, $rules): CatalogCollection {
            $this->syncRules($collection, $rules);

            return $this->loaded($collection->refresh());
        });
    }

    /** @param list<array<string, mixed>> $products */
    public function replaceManualProducts(User $user, string $publicId, array $products): CatalogCollection
    {
        $store = $this->store($user, true);
        $collection = $this->collection($store, $publicId);
        $products = $this->validatedProducts($products);

        return DB::transaction(function () use ($collection, $products, $store): CatalogCollection {
            $this->syncManualProducts($collection, $store, $products);

            return $this->loaded($collection->refresh());
        });
    }

    /** @return array{collection: CatalogCollection, matched_count: int} */
    public function refreshMembership(User $user, string $publicId): array
    {
        $store = $this->store($user, true);

        return DB::transaction(function () use ($publicId, $store): array {
            $collection = CatalogCollection::query()
                ->where('store_id', $store->getKey())
                ->where('public_id', $publicId)
                ->lockForUpdate()
                ->firstOrFail();
            $this->ensureAutomated($collection);
            $rules = $collection->rules()->get();
            if ($rules->isEmpty()) {
                throw ValidationException::withMessages([
                    'rules' => ['At least one saved rule is required before membership can be refreshed.'],
                ]);
            }

            $products = Product::query()
                ->where('products.store_id', $store->getKey())
                ->where(function (Builder $query) use ($collection, $rules): void {
                    foreach ($rules as $index => $rule) {
                        $method = $collection->rules_match_type === 'any' && $index > 0 ? 'orWhere' : 'where';
                        $query->{$method}(fn (Builder $condition) => $this->applyRule($condition, $rule));
                    }
                })
                ->orderBy('sortorder')
                ->orderBy('products.id')
                ->pluck('products.id');

            DB::table('product_collections')
                ->where('store_id', $store->getKey())
                ->where('collection_id', $collection->getKey())
                ->whereIn('added_by', ['rule', 'ai'])
                ->where('is_pinned', false)
                ->delete();

            $existing = DB::table('product_collections')
                ->where('store_id', $store->getKey())
                ->where('collection_id', $collection->getKey())
                ->pluck('product_id')
                ->mapWithKeys(static fn ($id): array => [(int) $id => true]);
            $addedBy = $collection->collection_type === 'ai_generated' ? 'ai' : 'rule';
            $now = now();
            $rows = [];
            foreach ($products as $position => $productId) {
                if ($existing->has((int) $productId)) {
                    continue;
                }
                $rows[] = [
                    'store_id' => $store->getKey(),
                    'collection_id' => $collection->getKey(),
                    'product_id' => $productId,
                    'sort_order' => $position,
                    'added_by' => $addedBy,
                    'is_pinned' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if ($rows !== []) {
                DB::table('product_collections')->insert($rows);
            }

            $collection->forceFill(['ai_last_run_at' => $now])->save();

            return [
                'collection' => $this->loaded($collection->refresh()),
                'matched_count' => $products->count(),
            ];
        });
    }

    public function products(User $user, string $publicId, int $perPage = 25): LengthAwarePaginator
    {
        $store = $this->store($user, false);
        $collection = $this->collection($store, $publicId);

        return $collection->products()
            ->with('translations')
            ->paginate(max(1, min($perPage, 100)));
    }

    public function aiJobs(User $user, string $publicId, int $perPage = 25): LengthAwarePaginator
    {
        $store = $this->store($user, false);
        $collection = $this->collection($store, $publicId);

        return CollectionAiJob::query()
            ->where('store_id', $store->getKey())
            ->where('collection_id', $collection->getKey())
            ->latest('created_at')
            ->latest('id')
            ->paginate(max(1, min($perPage, 100)));
    }

    private function store(User $user, bool $manage): Store
    {
        $store = $this->context->require();
        $manage
            ? $this->access->ensureCanManageProducts($user, $store)
            : $this->access->ensureCanView($user, $store);

        return $store;
    }

    private function collection(Store $store, string $publicId): CatalogCollection
    {
        return CatalogCollection::query()
            ->where('store_id', $store->getKey())
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    private function loaded(CatalogCollection $collection): CatalogCollection
    {
        return $collection
            ->load(['translations', 'parent', 'rules'])
            ->loadCount(['children', 'products', 'rules', 'aiJobs']);
    }

    private function ensureValidParent(CatalogCollection $collection, ?CatalogCollection $parent): void
    {
        for ($candidate = $parent; $candidate instanceof CatalogCollection; $candidate = $candidate->parent()->first()) {
            if ($candidate->is($collection)) {
                throw ValidationException::withMessages([
                    'parent_id' => ['A collection cannot be its own parent or a child of one of its descendants.'],
                ]);
            }
        }
    }

    private function ensureAutomated(CatalogCollection $collection): void
    {
        if ($collection->collection_type === 'manual') {
            throw ValidationException::withMessages([
                'collection_type' => ['Manual collections do not evaluate rules.'],
            ]);
        }
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function validateWrite(array $input, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';
        $data = Validator::make($input, [
            'parent_id' => ['sometimes', 'nullable', 'ulid'],
            'image_url' => ['sometimes', 'nullable', 'string', 'max:500', 'regex:/^(?:\/|https?:\/\/)/i'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'collection_type' => ['sometimes', 'in:manual,rule_based,ai_generated'],
            'rules_match_type' => ['sometimes', 'in:all,any'],
            'ai_prompt' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'ai_model' => ['sometimes', 'nullable', 'string', 'max:100'],
            'translations' => [$required, 'array', 'list', 'min:1'],
            'translations.*.locale' => ['required_with:translations', 'string', 'max:35', 'regex:/^[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})*$/'],
            'translations.*.title' => ['required_with:translations', 'string', 'max:255'],
            'translations.*.slug' => ['required_with:translations', 'string', 'max:255'],
            'translations.*.description' => ['sometimes', 'nullable', 'string'],
            'translations.*.seo_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'translations.*.seo_description' => ['sometimes', 'nullable', 'string'],
            'translations.*.lock_it' => ['sometimes', 'boolean'],
            'rules' => ['sometimes', 'array', 'list', 'max:100'],
            'rules.*.field' => ['required', 'string', 'max:50'],
            'rules.*.operator' => ['required', 'string', 'max:20'],
            'rules.*.value' => ['required', 'string', 'max:255'],
            'rules.*.position' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'products' => ['sometimes', 'array', 'list', 'max:1000'],
            'products.*.product_id' => ['required', 'ulid', 'distinct'],
            'products.*.sort_order' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'products.*.is_pinned' => ['sometimes', 'boolean'],
        ])->validate();

        if (array_key_exists('rules', $data)) {
            $data['rules'] = $this->validatedRules($data['rules']);
        }
        if (array_key_exists('products', $data)) {
            $data['products'] = $this->validatedProducts($data['products']);
        }
        $type = (string) ($data['collection_type'] ?? 'manual');
        if ($creating && $type === 'manual' && ($data['rules'] ?? []) !== []) {
            throw ValidationException::withMessages(['rules' => ['Manual collections cannot contain rules.']]);
        }
        if ($creating && $type === 'ai_generated' && trim((string) ($data['ai_prompt'] ?? '')) === '') {
            throw ValidationException::withMessages(['ai_prompt' => ['An AI-generated collection requires an AI prompt.']]);
        }

        return $data;
    }

    /** @param list<array<string, mixed>> $rules @return list<array<string, mixed>> */
    private function validatedRules(array $rules): array
    {
        $data = Validator::make(['rules' => $rules], [
            'rules' => ['array', 'list', 'max:100'],
            'rules.*.field' => ['required', 'string', 'max:50'],
            'rules.*.operator' => ['required', 'string', 'max:20'],
            'rules.*.value' => ['required', 'string', 'max:255'],
            'rules.*.position' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
        ])->validate()['rules'];

        foreach ($data as $index => $rule) {
            $allowed = self::STRING_RULES[$rule['field']] ?? self::NUMBER_RULES[$rule['field']] ?? null;
            if ($allowed === null || ! in_array($rule['operator'], $allowed, true)) {
                throw ValidationException::withMessages([
                    "rules.{$index}.operator" => ['The field/operator combination is not supported.'],
                ]);
            }
            if (isset(self::NUMBER_RULES[$rule['field']]) && ! is_numeric($rule['value'])) {
                throw ValidationException::withMessages([
                    "rules.{$index}.value" => ['This rule requires a numeric value.'],
                ]);
            }
        }

        return $data;
    }

    /** @param list<array<string, mixed>> $products @return list<array<string, mixed>> */
    private function validatedProducts(array $products): array
    {
        return Validator::make(['products' => $products], [
            'products' => ['array', 'list', 'max:1000'],
            'products.*.product_id' => ['required', 'ulid', 'distinct'],
            'products.*.sort_order' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'products.*.is_pinned' => ['sometimes', 'boolean'],
        ])->validate()['products'];
    }

    /** @param list<array<string, mixed>> $rules */
    private function syncRules(CatalogCollection $collection, array $rules): void
    {
        $collection->rules()->delete();
        foreach ($rules as $index => $rule) {
            $collection->rules()->create([
                'store_id' => $collection->store_id,
                'field' => $rule['field'],
                'operator' => $rule['operator'],
                'value' => $rule['value'],
                'position' => $rule['position'] ?? $index,
            ]);
        }
    }

    /** @param list<array<string, mixed>> $products */
    private function syncManualProducts(CatalogCollection $collection, Store $store, array $products): void
    {
        $resolved = Product::query()
            ->where('store_id', $store->getKey())
            ->whereIn('public_id', array_column($products, 'product_id'))
            ->get(['id', 'public_id'])
            ->keyBy('public_id');
        if ($resolved->count() !== count($products)) {
            throw ValidationException::withMessages([
                'products' => ['Every Product must exist in the selected Store.'],
            ]);
        }

        DB::table('product_collections')
            ->where('store_id', $store->getKey())
            ->where('collection_id', $collection->getKey())
            ->where('added_by', 'manual')
            ->delete();

        $now = now();
        foreach ($products as $index => $item) {
            DB::table('product_collections')->updateOrInsert([
                'collection_id' => $collection->getKey(),
                'product_id' => $resolved[(string) $item['product_id']]->getKey(),
            ], [
                'store_id' => $store->getKey(),
                'sort_order' => $item['sort_order'] ?? $index,
                'added_by' => 'manual',
                'is_pinned' => $item['is_pinned'] ?? false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function applyRule(Builder $query, CollectionRule $rule): void
    {
        if ($rule->field === 'price') {
            $operators = [
                'equals' => '=',
                'not_equals' => '!=',
                'greater_than' => '>',
                'greater_than_or_equal' => '>=',
                'less_than' => '<',
                'less_than_or_equal' => '<=',
            ];
            $query->where('price', $operators[$rule->operator], $rule->value);

            return;
        }

        if (in_array($rule->field, ['vendor', 'sku', 'status'], true)) {
            $this->applyString($query, 'products.'.$rule->field, $rule->operator, $rule->value);

            return;
        }

        if ($rule->field === 'title') {
            $this->applyRelatedString($query, 'translations', 'title', $rule);

            return;
        }

        if ($rule->field === 'brand') {
            $this->applyRelatedString($query, 'brand.translations', 'name', $rule);

            return;
        }

        if ($rule->field === 'product_type') {
            $method = $rule->operator === 'not_equals' ? 'whereDoesntHave' : 'whereHas';
            $operator = $rule->operator === 'not_equals' ? 'equals' : $rule->operator;
            $query->{$method}('productType', function (Builder $productType) use ($operator, $rule): void {
                $productType->where(function (Builder $identity) use ($operator, $rule): void {
                    $this->applyString($identity, 'code', $operator, $rule->value);
                    $identity->orWhereHas('translations', fn (Builder $translation) => $this->applyString(
                        $translation,
                        'name',
                        $operator,
                        $rule->value,
                    ));
                });
            });

            return;
        }

        $method = $rule->operator === 'not_equals' ? 'whereNotExists' : 'whereExists';
        $operator = $rule->operator === 'not_equals' ? 'equals' : $rule->operator;
        $query->{$method}(function ($tag) use ($operator, $rule): void {
            $tag->selectRaw('1')
                ->from('product_tags')
                ->join('tags', function ($join): void {
                    $join->on('tags.id', '=', 'product_tags.tag_id')
                        ->on('tags.store_id', '=', 'product_tags.store_id');
                })
                ->whereColumn('product_tags.product_id', 'products.id')
                ->whereColumn('product_tags.store_id', 'products.store_id');
            $this->applyString($tag, 'tags.name', $operator, $rule->value);
        });
    }

    private function applyRelatedString(
        Builder $query,
        string $relation,
        string $column,
        CollectionRule $rule,
    ): void {
        $method = $rule->operator === 'not_equals' ? 'whereDoesntHave' : 'whereHas';
        $operator = $rule->operator === 'not_equals' ? 'equals' : $rule->operator;
        $query->{$method}($relation, fn (Builder $related) => $this->applyString(
            $related,
            $column,
            $operator,
            $rule->value,
        ));
    }

    private function applyString(mixed $query, string $column, string $operator, string $value): void
    {
        $normalized = mb_strtolower(trim($value));
        match ($operator) {
            'equals' => $query->whereRaw("LOWER({$column}) = ?", [$normalized]),
            'not_equals' => $query->whereRaw("COALESCE(LOWER({$column}), '') <> ?", [$normalized]),
            default => $query->where($column, 'ILIKE', '%'.$this->escapeLike($value).'%'),
        };
    }

    /** @param list<array<string, mixed>> $translations @return list<array<string, mixed>> */
    private function translationRows(array $translations): array
    {
        return array_map(static fn (array $translation): array => [
            'locale' => $translation['locale'],
            'title' => $translation['title'],
            'slug' => $translation['slug'],
            'description' => $translation['description'] ?? null,
            'seo_title' => $translation['seo_title'] ?? null,
            'seo_description' => $translation['seo_description'] ?? null,
            ...Arr::has($translation, 'lock_it') ? ['lock_it' => $translation['lock_it']] : [],
        ], $translations);
    }

    private function localeKey(string $locale): string
    {
        return strtolower(str_replace('-', '_', trim($locale)));
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($value));
    }
}
