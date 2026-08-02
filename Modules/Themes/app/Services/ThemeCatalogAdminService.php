<?php

declare(strict_types=1);

namespace Modules\Themes\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Models\User;
use Modules\Stores\Models\Store;
use Modules\Themes\Enums\ThemeSourceType;
use Modules\Themes\Enums\ThemeStatus;
use Modules\Themes\Models\Theme;
use Modules\Themes\Models\ThemeCategory;
use Modules\Themes\Models\ThemePublisher;

final readonly class ThemeCatalogAdminService
{
    public function __construct(private ThemeAccessService $access) {}

    /** @param array<string, mixed> $filters @return LengthAwarePaginator<int, Theme> */
    public function list(User $user, array $filters, int $perPage): LengthAwarePaginator
    {
        $this->access->ensureCanManageMarketplace($user);
        $search = trim((string) ($filters['search'] ?? ''));

        return Theme::query()
            ->with(['publisher.owner', 'ownerStore', 'creator', 'currentVersion.uploader', 'currentVersion.approver', 'categories'])
            ->withCount(['licenses', 'installations'])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereRaw('name ILIKE ?', ["%{$search}%"])
                ->orWhereRaw('slug ILIKE ?', ["%{$search}%"])
                ->orWhereRaw('summary ILIKE ?', ["%{$search}%"])))
            ->when($filters['source_type'] ?? null, fn ($query, $value) => $query->where('source_type', $value))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['visibility'] ?? null, fn ($query, $value) => $query->where('visibility', $value))
            ->orderByDesc('is_featured')
            ->orderByDesc('updated_at')
            ->paginate($perPage);
    }

    public function view(User $user, Theme $theme): Theme
    {
        $this->access->ensureCanManageMarketplace($user);

        return $this->loadTheme($theme);
    }

    /** @param array<string, mixed> $data */
    public function create(User $user, array $data): Theme
    {
        $this->access->ensureCanManageMarketplace($user);

        return DB::transaction(function () use ($data, $user): Theme {
            [$attributes, $categoryIds, $primaryCategoryId] = $this->attributes($data);
            $theme = Theme::query()->create([
                ...$attributes,
                'created_by_user_id' => $user->getKey(),
                'status' => $attributes['status'] ?? ThemeStatus::Draft,
                'listing_metadata' => $attributes['listing_metadata'] ?? [],
                'is_featured' => $attributes['is_featured'] ?? false,
            ]);
            $this->syncCategories($theme, $categoryIds, $primaryCategoryId);

            return $this->loadTheme($theme);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, Theme $theme, array $data): Theme
    {
        $this->access->ensureCanManageMarketplace($user);

        return DB::transaction(function () use ($data, $theme): Theme {
            [$attributes, $categoryIds, $primaryCategoryId] = $this->attributes($data, $theme);
            $theme->fill($attributes)->save();
            if (array_key_exists('category_ids', $data) || array_key_exists('primary_category_id', $data)) {
                $this->syncCategories($theme, $categoryIds, $primaryCategoryId);
            }

            return $this->loadTheme($theme->refresh());
        });
    }

    /** @return LengthAwarePaginator<int, ThemePublisher> */
    public function publishers(User $user, int $perPage): LengthAwarePaginator
    {
        $this->access->ensureCanManageMarketplace($user);

        return ThemePublisher::query()->with('owner')->orderBy('display_name')->paginate($perPage);
    }

    /** @param array<string, mixed> $data */
    public function savePublisher(User $user, array $data, ?ThemePublisher $publisher = null): ThemePublisher
    {
        $this->access->ensureCanManageMarketplace($user);
        $ownerId = isset($data['owner_user_id'])
            ? User::query()->where('public_id', $data['owner_user_id'])->value('id')
            : ($publisher?->owner_user_id);
        $attributes = [...Arr::except($data, ['owner_user_id']), 'owner_user_id' => $ownerId];

        return DB::transaction(function () use ($attributes, $publisher): ThemePublisher {
            $publisher ??= new ThemePublisher;
            $publisher->fill($attributes)->save();

            return $publisher->refresh()->load('owner');
        });
    }

    /** @return LengthAwarePaginator<int, ThemeCategory> */
    public function categories(User $user, int $perPage): LengthAwarePaginator
    {
        $this->access->ensureCanManageMarketplace($user);

        return ThemeCategory::query()->with('parent')->orderBy('category_type')->orderBy('sort_order')->orderBy('name')->paginate($perPage);
    }

    /** @param array<string, mixed> $data */
    public function saveCategory(User $user, array $data, ?ThemeCategory $category = null): ThemeCategory
    {
        $this->access->ensureCanManageMarketplace($user);
        $parentId = isset($data['parent_id'])
            ? ThemeCategory::query()->where('public_id', $data['parent_id'])->value('id')
            : ($category?->parent_id);
        if ($category !== null && $parentId === $category->getKey()) {
            throw ValidationException::withMessages(['parent_id' => ['A category cannot be its own parent.']]);
        }
        $attributes = [...Arr::except($data, ['parent_id']), 'parent_id' => $parentId];

        return DB::transaction(function () use ($attributes, $category): ThemeCategory {
            $category ??= new ThemeCategory;
            $category->fill($attributes)->save();

            return $category->refresh()->load('parent');
        });
    }

    /** @param array<string, mixed> $data @return array{array<string, mixed>, list<int>, int|null} */
    private function attributes(array $data, ?Theme $theme = null): array
    {
        $publisherId = array_key_exists('publisher_id', $data)
            ? ThemePublisher::query()->where('public_id', $data['publisher_id'])->value('id')
            : $theme?->publisher_id;
        $ownerStoreId = array_key_exists('owner_store_id', $data)
            ? Store::query()->where('public_id', $data['owner_store_id'])->value('id')
            : $theme?->owner_store_id;
        $sourceType = (string) ($data['source_type'] ?? $theme?->sourceTypeValue() ?? '');
        $visibility = (string) ($data['visibility'] ?? $theme?->visibility ?? '');
        $commercialType = (string) ($data['commercial_type'] ?? $theme?->commercial_type ?? '');

        if ($sourceType === ThemeSourceType::Custom->value) {
            if ($ownerStoreId === null || $publisherId !== null || $visibility !== 'private' || $commercialType !== 'private') {
                throw ValidationException::withMessages(['source_type' => ['Custom themes require one owner Store, no publisher, private visibility, and private commercial type.']]);
            }
        } elseif ($publisherId === null || $ownerStoreId !== null) {
            throw ValidationException::withMessages(['publisher_id' => ['Platform and third-party themes require a publisher and cannot have an owner Store.']]);
        }

        if ($commercialType === 'paid' && (! isset($data['price_amount_minor']) && $theme?->price_amount_minor === null)) {
            throw ValidationException::withMessages(['price_amount_minor' => ['Paid themes require a price and currency.']]);
        }

        $attributes = Arr::except($data, ['publisher_id', 'owner_store_id', 'category_ids', 'primary_category_id']);
        $attributes['publisher_id'] = $publisherId;
        $attributes['owner_store_id'] = $ownerStoreId;
        if ($commercialType !== 'paid') {
            $attributes['price_amount_minor'] = null;
            $attributes['price_currency'] = null;
        }

        $categoryPublicIds = array_values($data['category_ids'] ?? $theme?->categories->pluck('public_id')->all() ?? []);
        $categoryIds = ThemeCategory::query()->whereIn('public_id', $categoryPublicIds)->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $primaryPublicId = $data['primary_category_id'] ?? $theme?->categories->firstWhere('pivot.is_primary', true)?->public_id;
        $primaryId = $primaryPublicId === null ? null : (int) ThemeCategory::query()->where('public_id', $primaryPublicId)->value('id');

        return [$attributes, $categoryIds, $primaryId ?: null];
    }

    /** @param list<int> $categoryIds */
    private function syncCategories(Theme $theme, array $categoryIds, ?int $primaryCategoryId): void
    {
        if ($primaryCategoryId !== null && ! in_array($primaryCategoryId, $categoryIds, true)) {
            throw ValidationException::withMessages(['primary_category_id' => ['The primary category must be included in category_ids.']]);
        }
        $assignments = [];
        foreach ($categoryIds as $index => $categoryId) {
            $assignments[$categoryId] = ['is_primary' => $categoryId === $primaryCategoryId, 'sort_order' => ($index + 1) * 10];
        }
        $theme->categories()->sync($assignments);
    }

    private function loadTheme(Theme $theme): Theme
    {
        return $theme->load(['publisher.owner', 'ownerStore', 'creator', 'currentVersion.uploader', 'currentVersion.approver', 'versions.uploader', 'versions.approver', 'versions.submissions.submitter', 'versions.submissions.reviewer', 'categories'])
            ->loadCount(['licenses', 'installations']);
    }
}
