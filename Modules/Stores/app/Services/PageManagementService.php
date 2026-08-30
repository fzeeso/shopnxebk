<?php

declare(strict_types=1);

namespace Modules\Stores\Services;

use App\Support\Translations\TranslationCoordinator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Models\User;
use Modules\Settings\Models\Language;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Enums\PageStatus;
use Modules\Stores\Enums\PageType;
use Modules\Stores\Models\Page;
use Modules\Stores\Models\PageTranslation;
use Modules\Stores\Models\Store;

final readonly class PageManagementService
{
    public function __construct(
        private StoreContext $context,
        private StoreAccessService $access,
        private TranslationCoordinator $translationCoordinator,
    ) {}

    /** @param array<string, mixed> $filters @return LengthAwarePaginator<int, Page> */
    public function list(User $user, array $filters): LengthAwarePaginator
    {
        $store = $this->store($user, false);
        $query = Page::query()
            ->where('store_id', $store->getKey())
            ->with($this->relations())
            ->withCount('children');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['page_type'])) {
            $query->where('page_type', $filters['page_type']);
        }
        if ((bool) ($filters['root_only'] ?? false)) {
            $query->whereNull('parent_id');
        } elseif (array_key_exists('parent_id', $filters) && $filters['parent_id'] !== null) {
            $query->where('parent_id', $this->pageByPublicId($store, (string) $filters['parent_id'])->getKey());
        }

        $languageId = null;
        if (isset($filters['language_id'])) {
            $languageId = $this->activeLanguage($store, (string) $filters['language_id'])->getKey();
        }
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '' || $languageId !== null) {
            $query->whereHas('translations', function ($query) use ($languageId, $search): void {
                if ($languageId !== null) {
                    $query->where('language_id', $languageId);
                }
                if ($search !== '') {
                    $query->where(function ($query) use ($search): void {
                        $query->where('title', 'ILIKE', "%{$search}%")
                            ->orWhere('slug', 'ILIKE', "%{$search}%")
                            ->orWhere('search_keywords', 'ILIKE', "%{$search}%");
                    });
                }
            });
        }

        return $query
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(
                (int) ($filters['per_page'] ?? 25),
                ['*'],
                'page',
                (int) ($filters['page'] ?? 1),
            );
    }

    public function show(User $user, Page $page): Page
    {
        $store = $this->store($user, false);
        $this->ensureOwned($page, $store);

        return $this->load($page);
    }

    /** @param array<string, mixed> $data */
    public function create(User $user, array $data): Page
    {
        $store = $this->store($user, true);
        $this->validateTypeConfiguration($data, (string) $data['page_type']);

        return DB::transaction(function () use ($data, $store, $user): Page {
            $parent = ($data['parent_id'] ?? null) === null
                ? null
                : $this->pageByPublicId($store, (string) $data['parent_id']);
            if (($data['is_homepage'] ?? false) === true) {
                $this->clearHomepage($store);
            }

            $page = Page::query()->create([
                'store_id' => $store->getKey(),
                'parent_id' => $parent?->getKey(),
                'page_type' => $data['page_type'],
                'status' => PageStatus::Draft,
                'sort_order' => $data['sort_order'] ?? 0,
                'layout_key' => $data['layout_key'] ?? null,
                'is_homepage' => $data['is_homepage'] ?? false,
                'customers_only' => $data['customers_only'] ?? false,
                'seo_enabled' => $data['seo_enabled'] ?? false,
                'external_url' => $data['external_url'] ?? null,
                'feed_url' => $data['feed_url'] ?? null,
                'contact_email' => $data['contact_email'] ?? null,
                'contact_fields' => $data['contact_fields'] ?? [],
                'created_by' => $user->getKey(),
                'updated_by' => $user->getKey(),
            ]);

            foreach ($data['translations'] as $translation) {
                $language = $this->activeLanguage($store, (string) $translation['language_id']);
                $this->persistTranslation($page, $store, $language, $translation);
            }

            $request = $this->translationCoordinator->request(
                store: $store,
                contentType: 'page',
                contentId: (int) $page->getKey(),
                missingOnly: true,
                requestedBy: (int) $user->getKey(),
            );

            return $this->load($page->refresh())->setRelation('translationRequest', $request);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, Page $page, array $data): Page
    {
        $store = $this->store($user, true);
        $this->ensureOwned($page, $store);
        if ($data === []) {
            throw ValidationException::withMessages(['page' => ['At least one field must be supplied.']]);
        }

        $type = (string) ($data['page_type'] ?? $page->typeValue());
        $this->validateTypeConfiguration([
            'external_url' => $data['external_url'] ?? $page->external_url,
            'feed_url' => $data['feed_url'] ?? $page->feed_url,
        ], $type);

        return DB::transaction(function () use ($data, $page, $store, $type, $user): Page {
            $attributes = ['page_type' => $type, 'updated_by' => $user->getKey()];
            foreach ([
                'sort_order',
                'layout_key',
                'is_homepage',
                'customers_only',
                'seo_enabled',
                'external_url',
                'feed_url',
                'contact_email',
                'contact_fields',
            ] as $field) {
                if (array_key_exists($field, $data)) {
                    $attributes[$field] = $data[$field];
                }
            }
            if (array_key_exists('parent_id', $data)) {
                $parent = $data['parent_id'] === null
                    ? null
                    : $this->pageByPublicId($store, (string) $data['parent_id']);
                $this->ensureValidParent($page, $parent);
                $attributes['parent_id'] = $parent?->getKey();
            }
            if (($attributes['is_homepage'] ?? false) === true) {
                $this->clearHomepage($store, $page);
            }

            $page->fill($attributes)->save();

            return $this->load($page->refresh());
        });
    }

    public function publish(User $user, Page $page): Page
    {
        $store = $this->store($user, true);
        $this->ensureOwned($page, $store);
        if ($page->statusValue() === PageStatus::Disabled->value) {
            throw ValidationException::withMessages(['status' => ['Enable this page before publishing it.']]);
        }

        $this->validateTypeConfiguration([
            'external_url' => $page->external_url,
            'feed_url' => $page->feed_url,
        ], $page->typeValue());
        $translation = $this->publicationTranslation($store, $page);
        if ($page->typeValue() === PageType::Content->value && trim((string) $translation->content) === '') {
            throw ValidationException::withMessages([
                'translations' => ['The default-language content must be non-empty before publishing a content page.'],
            ]);
        }

        $page->forceFill([
            'status' => PageStatus::Published,
            'published_at' => $page->published_at ?? now(),
            'updated_by' => $user->getKey(),
        ])->save();

        return $this->load($page->refresh());
    }

    public function unpublish(User $user, Page $page): Page
    {
        $store = $this->store($user, true);
        $this->ensureOwned($page, $store);
        if ($page->statusValue() === PageStatus::Published->value) {
            $page->forceFill([
                'status' => PageStatus::Draft,
                'published_at' => null,
                'updated_by' => $user->getKey(),
            ])->save();
        }

        return $this->load($page->refresh());
    }

    public function enable(User $user, Page $page): Page
    {
        $store = $this->store($user, true);
        $this->ensureOwned($page, $store);
        if ($page->statusValue() === PageStatus::Disabled->value) {
            $page->forceFill([
                'status' => PageStatus::Draft,
                'published_at' => null,
                'updated_by' => $user->getKey(),
            ])->save();
        }

        return $this->load($page->refresh());
    }

    public function disable(User $user, Page $page): Page
    {
        $store = $this->store($user, true);
        $this->ensureOwned($page, $store);
        $page->forceFill([
            'status' => PageStatus::Disabled,
            'published_at' => null,
            'updated_by' => $user->getKey(),
        ])->save();

        return $this->load($page->refresh());
    }

    /** @param array<string, mixed> $data */
    public function upsertTranslation(
        User $user,
        Page $page,
        Language $language,
        array $data,
    ): PageTranslation {
        $store = $this->store($user, true);
        $this->ensureOwned($page, $store);
        $language = $this->activeLanguage($store, (string) $language->public_id);

        return DB::transaction(function () use ($data, $language, $page, $store, $user): PageTranslation {
            Page::query()->whereKey($page->getKey())->lockForUpdate()->firstOrFail();
            $translation = $this->persistTranslation($page, $store, $language, $data);
            $page->forceFill(['updated_by' => $user->getKey()])->save();
            $request = $this->translationCoordinator->request(
                store: $store,
                contentType: 'page',
                contentId: (int) $page->getKey(),
                expectedSourceLocale: (string) $language->locale,
                requestedBy: (int) $user->getKey(),
            );

            return $translation->refresh()->load('language')
                ->setRelation('translationRequest', $request);
        });
    }

    public function deleteTranslation(User $user, Page $page, Language $language): void
    {
        $store = $this->store($user, true);
        $this->ensureOwned($page, $store);
        $language = $this->activeLanguage($store, (string) $language->public_id);
        $translation = PageTranslation::query()
            ->where('page_id', $page->getKey())
            ->where('language_id', $language->getKey())
            ->firstOrFail();

        if ($page->statusValue() === PageStatus::Published->value) {
            $defaultLanguageId = $this->defaultLanguageId($store);
            if ($page->translations()->count() === 1 || $defaultLanguageId === $language->getKey()) {
                throw ValidationException::withMessages([
                    'translation' => ['A published page must retain its default-language translation.'],
                ]);
            }
        }

        $translation->delete();
        $page->forceFill(['updated_by' => $user->getKey()])->save();
    }

    public function delete(User $user, Page $page): void
    {
        $this->disable($user, $page);
    }

    /** @param array<string, mixed> $data */
    private function persistTranslation(
        Page $page,
        Store $store,
        Language $language,
        array $data,
    ): PageTranslation {
        $slug = $this->normalizeSlug((string) $data['slug']);
        $this->ensureUniqueSlug($store, $page, $language, $slug);
        $translation = PageTranslation::query()->firstOrNew([
            'page_id' => $page->getKey(),
            'language_id' => $language->getKey(),
        ]);
        $translation->fill([
            'store_id' => $store->getKey(),
            'title' => $data['title'],
            'slug' => $slug,
            'content' => $data['content'] ?? null,
            'summary' => $data['summary'] ?? null,
            'seo_title' => $data['seo_title'] ?? null,
            'seo_description' => $data['seo_description'] ?? null,
            'seo_keywords' => $data['seo_keywords'] ?? null,
            'search_keywords' => $data['search_keywords'] ?? null,
            ...array_key_exists('lock_it', $data) ? ['lock_it' => (bool) $data['lock_it']] : [],
        ])->save();

        return $translation;
    }

    private function publicationTranslation(Store $store, Page $page): PageTranslation
    {
        $defaultLanguageId = $this->defaultLanguageId($store);
        $translation = $defaultLanguageId === null
            ? $page->translations()->first()
            : $page->translations()->where('language_id', $defaultLanguageId)->first();
        if (! $translation instanceof PageTranslation) {
            throw ValidationException::withMessages([
                'translations' => ['A default-language translation is required before publishing.'],
            ]);
        }

        return $translation;
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

    private function activeLanguage(Store $store, string $publicId): Language
    {
        $language = Language::query()
            ->where('public_id', $publicId)
            ->where('is_active', true)
            ->firstOrFail();
        if (! DB::table('store_languages')
            ->where('store_id', $store->getKey())
            ->where('language_id', $language->getKey())
            ->where('is_active', true)
            ->exists()) {
            throw ValidationException::withMessages([
                'language_id' => ['The selected language is not active for this Store.'],
            ]);
        }

        return $language;
    }

    private function ensureUniqueSlug(Store $store, Page $page, Language $language, string $slug): void
    {
        if (PageTranslation::query()
            ->where('store_id', $store->getKey())
            ->where('language_id', $language->getKey())
            ->whereRaw('LOWER(slug) = ?', [Str::lower($slug)])
            ->where('page_id', '<>', $page->getKey())
            ->exists()) {
            throw ValidationException::withMessages([
                'slug' => ['This slug is already used by another page in the selected language.'],
            ]);
        }
    }

    private function normalizeSlug(string $slug): string
    {
        $slug = Str::lower(trim($slug));
        if (preg_match('/^[\pL\pN]+(?:-[\pL\pN]+)*$/u', $slug) !== 1) {
            throw ValidationException::withMessages([
                'slug' => ['The slug may contain Unicode letters, numbers, and single hyphens between segments.'],
            ]);
        }

        return $slug;
    }

    /** @param array<string, mixed> $data */
    private function validateTypeConfiguration(array $data, string $type): void
    {
        if ($type === PageType::ExternalLink->value && trim((string) ($data['external_url'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'external_url' => ['An external-link page requires external_url.'],
            ]);
        }
        if ($type === PageType::Rss->value && trim((string) ($data['feed_url'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'feed_url' => ['An RSS page requires feed_url.'],
            ]);
        }
    }

    private function ensureValidParent(Page $page, ?Page $parent): void
    {
        for ($candidate = $parent; $candidate instanceof Page; $candidate = $candidate->parent()->first()) {
            if ($candidate->is($page)) {
                throw ValidationException::withMessages([
                    'parent_id' => ['A page cannot be its own parent or a child of one of its descendants.'],
                ]);
            }
        }
    }

    private function clearHomepage(Store $store, ?Page $except = null): void
    {
        Page::query()
            ->where('store_id', $store->getKey())
            ->where('is_homepage', true)
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->update(['is_homepage' => false, 'updated_at' => now()]);
    }

    private function pageByPublicId(Store $store, string $publicId): Page
    {
        return Page::query()
            ->where('store_id', $store->getKey())
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    private function store(User $user, bool $manage): Store
    {
        $store = $this->context->require();
        $manage
            ? $this->access->ensureCanManagePolicies($user, $store)
            : $this->access->ensureCanView($user, $store);

        return $store;
    }

    private function ensureOwned(Page $page, Store $store): void
    {
        if ($page->store_id !== $store->getKey()) {
            abort(404);
        }
    }

    /** @return list<string> */
    private function relations(): array
    {
        return ['store', 'parent', 'translations.language', 'creator', 'updater'];
    }

    private function load(Page $page): Page
    {
        return $page->load($this->relations())->loadCount('children');
    }
}
