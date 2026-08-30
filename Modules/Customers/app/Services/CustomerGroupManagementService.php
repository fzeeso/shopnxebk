<?php

declare(strict_types=1);

namespace Modules\Customers\Services;

use App\Support\Translations\TranslationCoordinator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Models\User;
use Modules\Customers\Contracts\CatalogTargetResolver;
use Modules\Customers\Enums\CustomerGroupCategoryAccess;
use Modules\Customers\Enums\CustomerGroupDiscountAppliesTo;
use Modules\Customers\Enums\CustomerGroupDiscountTarget;
use Modules\Customers\Models\Customer;
use Modules\Customers\Models\CustomerGroup;
use Modules\Customers\Models\CustomerGroupDiscount;
use Modules\Customers\Models\CustomerGroupTranslation;
use Modules\Settings\Models\Language;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Models\Store;

final readonly class CustomerGroupManagementService
{
    public function __construct(
        private StoreContext $context,
        private CustomerAccessService $access,
        private CatalogTargetResolver $catalogTargets,
        private TranslationCoordinator $translationCoordinator,
    ) {}

    /** @param array<string, mixed> $filters @return LengthAwarePaginator<int, CustomerGroup> */
    public function list(User $user, array $filters): LengthAwarePaginator
    {
        $store = $this->store($user, false);
        $query = CustomerGroup::query()
            ->where('store_id', $store->getKey())
            ->with($this->relations())
            ->withCount('customers');
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('code', 'ILIKE', "%{$search}%")
                    ->orWhereHas('translations', fn ($query) => $query->where('name', 'ILIKE', "%{$search}%"));
            });
        }

        return $query
            ->orderByDesc('is_default')
            ->orderBy('code')
            ->paginate(
                (int) ($filters['per_page'] ?? 25),
                ['*'],
                'page',
                (int) ($filters['page'] ?? 1),
            );
    }

    public function show(User $user, CustomerGroup $group): CustomerGroup
    {
        $store = $this->store($user, false);
        $this->ensureOwned($group, $store);

        return $this->load($group);
    }

    /** @param array<string, mixed> $data */
    public function create(User $user, array $data): CustomerGroup
    {
        $store = $this->store($user, true);
        $code = $this->normalizeCode((string) $data['code']);
        $this->ensureUniqueCode($store, $code);

        return DB::transaction(function () use ($code, $data, $store, $user): CustomerGroup {
            $isDefault = (bool) ($data['is_default'] ?? false);
            if ($isDefault) {
                $this->clearDefault($store);
            }
            $group = CustomerGroup::query()->create([
                'store_id' => $store->getKey(),
                'code' => $code,
                'default_discount_percentage' => $data['default_discount_percentage'] ?? 0,
                'discount_method' => trim((string) $data['discount_method']),
                'is_default' => $isDefault,
                'category_access_type' => $data['category_access_type'] ?? CustomerGroupCategoryAccess::All,
            ]);
            $sourceLocale = $this->createTranslations($group, $store, $data['translations']);
            $this->syncCategories($group, $store, $data['category_ids'] ?? []);
            $request = $this->translationCoordinator->request(
                store: $store,
                contentType: 'customer_group',
                contentId: (int) $group->getKey(),
                expectedSourceLocale: $sourceLocale,
                requestedBy: (int) $user->getKey(),
            );

            return $this->load($group)->setRelation('translationRequest', $request);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, CustomerGroup $group, array $data): CustomerGroup
    {
        $store = $this->store($user, true);
        $this->ensureOwned($group, $store);
        if ($data === []) {
            throw ValidationException::withMessages(['customer_group' => ['At least one field must be supplied.']]);
        }

        return DB::transaction(function () use ($data, $group, $store): CustomerGroup {
            $attributes = [];
            foreach (['default_discount_percentage', 'discount_method', 'category_access_type'] as $field) {
                if (array_key_exists($field, $data)) {
                    $attributes[$field] = $data[$field];
                }
            }
            if (array_key_exists('is_default', $data)) {
                $attributes['is_default'] = (bool) $data['is_default'];
            }
            if (array_key_exists('code', $data)) {
                $code = $this->normalizeCode((string) $data['code']);
                $this->ensureUniqueCode($store, $code, $group);
                $attributes['code'] = $code;
            }
            if (($attributes['is_default'] ?? false) === true) {
                $this->clearDefault($store, $group);
            }
            if (array_key_exists('discount_method', $attributes)) {
                $attributes['discount_method'] = trim((string) $attributes['discount_method']);
            }

            $group->fill($attributes)->save();
            if (array_key_exists('category_access_type', $attributes)
                && $attributes['category_access_type'] !== CustomerGroupCategoryAccess::Specific->value
                && $attributes['category_access_type'] !== CustomerGroupCategoryAccess::Specific) {
                $group->categories()->detach();
            }

            return $this->load($group->refresh());
        });
    }

    public function delete(User $user, CustomerGroup $group): void
    {
        $store = $this->store($user, true);
        $this->ensureOwned($group, $store);
        if ((bool) $group->is_default) {
            throw ValidationException::withMessages(['customer_group' => ['The default customer group cannot be deleted.']]);
        }
        if (Customer::query()->withTrashed()->where('customer_group_id', $group->getKey())->exists()) {
            throw ValidationException::withMessages(['customer_group' => ['A customer group with current or historical customers cannot be deleted.']]);
        }

        $group->delete();
    }

    /** @param array<string, mixed> $data */
    public function upsertTranslation(
        User $user,
        CustomerGroup $group,
        Language $language,
        array $data,
    ): CustomerGroupTranslation {
        $store = $this->store($user, true);
        $this->ensureOwned($group, $store);
        $language = $this->activeLanguage($store, (string) $language->public_id);

        return DB::transaction(function () use ($data, $group, $language, $store, $user): CustomerGroupTranslation {
            $translation = CustomerGroupTranslation::query()->firstOrNew([
                'customer_group_id' => $group->getKey(),
                'language_id' => $language->getKey(),
            ]);
            $translation->fill([
                'store_id' => $store->getKey(),
                'name' => trim((string) $data['name']),
                ...array_key_exists('lock_it', $data) ? ['lock_it' => (bool) $data['lock_it']] : [],
            ])->save();
            $request = $this->translationCoordinator->request(
                store: $store,
                contentType: 'customer_group',
                contentId: (int) $group->getKey(),
                expectedSourceLocale: (string) $language->locale,
                requestedBy: (int) $user->getKey(),
            );

            return $translation->refresh()->load('language')->setRelation('translationRequest', $request);
        });
    }

    public function deleteTranslation(User $user, CustomerGroup $group, Language $language): void
    {
        $store = $this->store($user, true);
        $this->ensureOwned($group, $store);
        $language = $this->activeLanguage($store, (string) $language->public_id);
        if ($this->defaultLanguageId($store) === (int) $language->getKey()) {
            throw ValidationException::withMessages(['translation' => ['The default Store-language customer-group name cannot be deleted.']]);
        }

        CustomerGroupTranslation::query()
            ->where('customer_group_id', $group->getKey())
            ->where('language_id', $language->getKey())
            ->firstOrFail()
            ->delete();
    }

    /** @param list<string> $categoryPublicIds */
    public function replaceCategories(
        User $user,
        CustomerGroup $group,
        array $categoryPublicIds,
    ): CustomerGroup {
        $store = $this->store($user, true);
        $this->ensureOwned($group, $store);
        $this->syncCategories($group, $store, $categoryPublicIds);

        return $this->load($group->refresh());
    }

    /** @param array<string, mixed> $data */
    public function createDiscount(
        User $user,
        CustomerGroup $group,
        array $data,
    ): CustomerGroupDiscount {
        $store = $this->store($user, true);
        $this->ensureOwned($group, $store);
        $attributes = $this->discountAttributes($group, $store, $data);

        return CustomerGroupDiscount::query()->create([
            'store_id' => $store->getKey(),
            'customer_group_id' => $group->getKey(),
            ...$attributes,
        ])->load(['category', 'product']);
    }

    /** @param array<string, mixed> $data */
    public function updateDiscount(
        User $user,
        CustomerGroup $group,
        CustomerGroupDiscount $discount,
        array $data,
    ): CustomerGroupDiscount {
        $store = $this->store($user, true);
        $this->ensureOwned($group, $store);
        $this->ensureDiscountOwned($discount, $group, $store);
        $discount->fill($this->discountAttributes($group, $store, $data, $discount))->save();

        return $discount->refresh()->load(['category', 'product']);
    }

    public function deleteDiscount(
        User $user,
        CustomerGroup $group,
        CustomerGroupDiscount $discount,
    ): void {
        $store = $this->store($user, true);
        $this->ensureOwned($group, $store);
        $this->ensureDiscountOwned($discount, $group, $store);
        $discount->delete();
    }

    /** @param list<array<string, mixed>> $translations */
    private function createTranslations(CustomerGroup $group, Store $store, array $translations): string
    {
        $seen = [];
        $defaultLanguageId = $this->defaultLanguageId($store);
        $sourceLocale = null;

        foreach ($translations as $translation) {
            $language = $this->activeLanguage($store, (string) $translation['language_id']);
            if (isset($seen[$language->getKey()])) {
                throw ValidationException::withMessages(['translations' => ['Each Store language may appear only once.']]);
            }
            $seen[$language->getKey()] = true;
            CustomerGroupTranslation::query()->create([
                'store_id' => $store->getKey(),
                'customer_group_id' => $group->getKey(),
                'language_id' => $language->getKey(),
                'name' => trim((string) $translation['name']),
                'lock_it' => (bool) ($translation['lock_it'] ?? false),
            ]);
            if ((int) $language->getKey() === $defaultLanguageId) {
                $sourceLocale = (string) $language->locale;
            }
        }
        if ($defaultLanguageId === null || $sourceLocale === null) {
            throw ValidationException::withMessages(['translations' => ['The default Store-language customer-group name is required.']]);
        }

        return $sourceLocale;
    }

    /** @param list<string> $categoryPublicIds */
    private function syncCategories(CustomerGroup $group, Store $store, array $categoryPublicIds): void
    {
        $isSpecific = $group->category_access_type === CustomerGroupCategoryAccess::Specific;
        if (! $isSpecific && $categoryPublicIds !== []) {
            throw ValidationException::withMessages(['category_ids' => ['Category IDs are allowed only when category_access_type is specific.']]);
        }

        $sync = [];
        foreach (array_values(array_unique($categoryPublicIds)) as $publicId) {
            $target = $this->catalogTargets->resolve($store, CustomerGroupDiscountTarget::Category, $publicId);
            $sync[$target->id] = ['store_id' => $store->getKey()];
        }
        $group->categories()->sync($sync);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function discountAttributes(
        CustomerGroup $group,
        Store $store,
        array $data,
        ?CustomerGroupDiscount $except = null,
    ): array {
        $type = CustomerGroupDiscountTarget::from((string) $data['target_type']);
        $target = $this->catalogTargets->resolve($store, $type, (string) $data['target_id']);
        $appliesTo = CustomerGroupDiscountAppliesTo::from((string) $data['applies_to']);
        if ($type === CustomerGroupDiscountTarget::Product
            && $appliesTo !== CustomerGroupDiscountAppliesTo::NotApplicable) {
            throw ValidationException::withMessages(['applies_to' => ['Product discounts require not_applicable.']]);
        }
        if ($type === CustomerGroupDiscountTarget::Category
            && $appliesTo === CustomerGroupDiscountAppliesTo::NotApplicable) {
            throw ValidationException::withMessages(['applies_to' => ['Category discounts must choose category_only or category_and_descendants.']]);
        }

        $column = $type === CustomerGroupDiscountTarget::Category ? 'category_id' : 'product_id';
        if (CustomerGroupDiscount::query()
            ->where('customer_group_id', $group->getKey())
            ->where($column, $target->id)
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->exists()) {
            throw ValidationException::withMessages(['target_id' => ['This customer group already has a discount for the selected target.']]);
        }

        return [
            'target_type' => $type,
            'category_id' => $type === CustomerGroupDiscountTarget::Category ? $target->id : null,
            'product_id' => $type === CustomerGroupDiscountTarget::Product ? $target->id : null,
            'discount_percentage' => $data['discount_percentage'],
            'applies_to' => $appliesTo,
            'discount_method' => trim((string) $data['discount_method']),
        ];
    }

    private function activeLanguage(Store $store, string $publicId): Language
    {
        $language = Language::query()->where('public_id', $publicId)->where('is_active', true)->firstOrFail();
        if (! DB::table('store_languages')
            ->where('store_id', $store->getKey())
            ->where('language_id', $language->getKey())
            ->where('is_active', true)
            ->exists()) {
            throw ValidationException::withMessages(['language_id' => ['The selected language is not active for this Store.']]);
        }

        return $language;
    }

    private function defaultLanguageId(Store $store): ?int
    {
        $id = DB::table('store_languages')
            ->join('languages', 'languages.id', '=', 'store_languages.language_id')
            ->where('store_languages.store_id', $store->getKey())
            ->where('store_languages.is_default', true)
            ->where('store_languages.is_active', true)
            ->where('languages.is_active', true)
            ->value('languages.id');

        return $id === null ? null : (int) $id;
    }

    private function clearDefault(Store $store, ?CustomerGroup $except = null): void
    {
        CustomerGroup::query()
            ->where('store_id', $store->getKey())
            ->where('is_default', true)
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->update(['is_default' => false, 'updated_at' => now()]);
    }

    private function ensureUniqueCode(Store $store, string $code, ?CustomerGroup $except = null): void
    {
        if (CustomerGroup::query()
            ->where('store_id', $store->getKey())
            ->whereRaw('LOWER(code) = ?', [Str::lower($code)])
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->exists()) {
            throw ValidationException::withMessages(['code' => ['This customer-group code is already used in the Store.']]);
        }
    }

    private function normalizeCode(string $code): string
    {
        return Str::upper(trim($code));
    }

    /** @return list<string> */
    private function relations(): array
    {
        return ['translations.language', 'categories', 'discounts.category', 'discounts.product'];
    }

    private function load(CustomerGroup $group): CustomerGroup
    {
        return $group->load($this->relations())->loadCount('customers');
    }

    private function ensureOwned(CustomerGroup $group, Store $store): void
    {
        if ((int) $group->store_id !== (int) $store->getKey()) {
            abort(404);
        }
    }

    private function ensureDiscountOwned(
        CustomerGroupDiscount $discount,
        CustomerGroup $group,
        Store $store,
    ): void {
        if ((int) $discount->store_id !== (int) $store->getKey()
            || (int) $discount->customer_group_id !== (int) $group->getKey()) {
            abort(404);
        }
    }

    private function store(User $user, bool $write): Store
    {
        $store = $this->context->require();
        $write ? $this->access->ensureCanManage($user, $store) : $this->access->ensureCanView($user, $store);

        return $store;
    }
}
