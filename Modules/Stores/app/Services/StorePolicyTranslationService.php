<?php

declare(strict_types=1);

namespace Modules\Stores\Services;

use App\Support\Translations\AutomatedTranslationWriter;
use App\Support\Translations\OpenAiTranslationException;
use App\Support\Translations\TranslationProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Models\User;
use Modules\Settings\Models\Language;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Models\PolicyVersion;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StorePolicy;
use Modules\Stores\Models\StorePolicyTranslation;

final readonly class StorePolicyTranslationService
{
    public function __construct(
        private StoreContext $context,
        private StoreAccessService $access,
        private AutomatedTranslationWriter $translations,
        private TranslationProvider $machineTranslations,
    ) {}

    /** @param array<string, mixed> $data */
    public function upsert(User $user, StorePolicy $policy, Language $language, array $data): StorePolicyTranslation
    {
        $store = $this->managedStore($user);
        $this->ensureOwned($policy, $store);
        if (! $language->is_active) {
            throw ValidationException::withMessages(['language' => ['The selected language is inactive.']]);
        }

        return DB::transaction(function () use ($data, $language, $policy, $store, $user): StorePolicyTranslation {
            StorePolicy::query()->whereKey($policy->getKey())->lockForUpdate()->firstOrFail();
            $translation = StorePolicyTranslation::query()->firstOrNew([
                'store_policy_id' => $policy->getKey(),
                'language_id' => $language->getKey(),
            ]);
            $contentChanged = ! $translation->exists || $translation->content !== $data['content'];
            $translation->fill($data)->save();

            if ($contentChanged) {
                $version = ((int) PolicyVersion::query()
                    ->where('store_policy_id', $policy->getKey())
                    ->where('language_id', $language->getKey())
                    ->max('version')) + 1;
                PolicyVersion::query()->create([
                    'store_policy_id' => $policy->getKey(),
                    'language_id' => $language->getKey(),
                    'version' => $version,
                    'content' => $data['content'],
                    'created_by' => $user->getKey(),
                ]);
            }

            $this->refreshAutomaticTranslations($policy, $store, $language, $translation, $user);
            $policy->forceFill(['updated_by' => $user->getKey()])->save();

            return $translation->refresh()->load('language');
        });
    }

    public function delete(User $user, StorePolicy $policy, Language $language): void
    {
        $store = $this->managedStore($user);
        $this->ensureOwned($policy, $store);
        $translation = StorePolicyTranslation::query()
            ->where('store_policy_id', $policy->getKey())
            ->where('language_id', $language->getKey())
            ->firstOrFail();

        if ($policy->statusValue() === 'published' && $policy->translations()->count() === 1) {
            throw ValidationException::withMessages([
                'translation' => ['A published policy must retain at least one translation.'],
            ]);
        }

        $translation->delete();
        $policy->forceFill(['updated_by' => $user->getKey()])->save();
    }

    private function managedStore(User $user): Store
    {
        $store = $this->context->require();
        $this->access->ensureCanManagePolicies($user, $store);

        return $store;
    }

    private function ensureOwned(StorePolicy $policy, Store $store): void
    {
        if ($policy->store_id !== $store->getKey()) {
            abort(404);
        }
    }

    private function refreshAutomaticTranslations(
        StorePolicy $policy,
        Store $store,
        Language $sourceLanguage,
        StorePolicyTranslation $source,
        User $user,
    ): void {
        $languages = DB::table('store_languages')
            ->join('languages', 'languages.id', '=', 'store_languages.language_id')
            ->where('store_languages.store_id', $store->getKey())
            ->where('store_languages.is_active', true)
            ->where('languages.is_active', true)
            ->orderByDesc('store_languages.is_default')
            ->orderBy('languages.locale')
            ->get(['languages.id', 'languages.locale', 'store_languages.is_default']);

        if ($languages->isEmpty()) {
            return;
        }

        $sourceLanguageRow = $languages->first(
            static fn (object $row): bool => (int) $row->id === (int) $sourceLanguage->getKey(),
        );
        if (! is_object($sourceLanguageRow)) {
            throw ValidationException::withMessages([
                'language' => ['The selected language is not active for this Store.'],
            ]);
        }

        $defaultLanguage = $languages->first(
            static fn (object $row): bool => (bool) $row->is_default,
        ) ?? $languages->first();
        if (! is_object($defaultLanguage) || (int) $defaultLanguage->id !== (int) $sourceLanguage->getKey()) {
            return;
        }

        $lockedLanguageIds = StorePolicyTranslation::query()
            ->where('store_policy_id', $policy->getKey())
            ->where('lock_it', true)
            ->pluck('language_id')
            ->mapWithKeys(static fn (mixed $languageId): array => [(int) $languageId => true]);
        $targetLanguages = $languages
            ->reject(static fn (object $row): bool => (int) $row->id === (int) $sourceLanguage->getKey()
                || $lockedLanguageIds->has((int) $row->id))
            ->values();

        if ($targetLanguages->isEmpty()) {
            return;
        }

        try {
            $translated = $this->machineTranslations->translateFields([
                'title' => (string) $source->title,
                'content' => (string) $source->content,
                'seo_title' => $source->seo_title !== null ? (string) $source->seo_title : null,
                'seo_description' => $source->seo_description !== null ? (string) $source->seo_description : null,
            ], (string) $sourceLanguageRow->locale, $targetLanguages->pluck('locale')->all(), 'ecommerce Store policy text', ['title', 'content']);
        } catch (OpenAiTranslationException) {
            throw ValidationException::withMessages([
                'translation' => ['Automatic policy translation failed. Please try again.'],
            ]);
        }

        $existing = StorePolicyTranslation::query()
            ->where('store_policy_id', $policy->getKey())
            ->whereIn('language_id', $targetLanguages->pluck('id'))
            ->get()
            ->keyBy(static fn (StorePolicyTranslation $row): int => (int) $row->language_id);
        $now = now();
        $rows = [];

        foreach ($targetLanguages as $targetLanguage) {
            $localeKey = $this->localeKey((string) $targetLanguage->locale);
            $fields = $translated[$localeKey] ?? null;
            if (! is_array($fields)) {
                throw ValidationException::withMessages([
                    'translation' => ["Automatic policy translation omitted locale [{$targetLanguage->locale}]."],
                ]);
            }

            $rows[] = [
                'public_id' => (string) ($existing->get((int) $targetLanguage->id)?->public_id ?? Str::ulid()),
                'store_policy_id' => $policy->getKey(),
                'language_id' => (int) $targetLanguage->id,
                'title' => $fields['title'],
                'content' => $fields['content'],
                'seo_title' => $fields['seo_title'],
                'seo_description' => $fields['seo_description'],
                'created_at' => $existing->get((int) $targetLanguage->id)?->created_at ?? $now,
                'updated_at' => $now,
            ];
        }

        $this->translations->upsert(
            'store_policy_translations',
            $rows,
            ['store_policy_id', 'language_id'],
            ['title', 'content', 'seo_title', 'seo_description', 'updated_at'],
        );

        foreach ($rows as $row) {
            $previous = $existing->get((int) $row['language_id']);
            if ($previous !== null && $previous->content === $row['content']) {
                continue;
            }

            $version = ((int) PolicyVersion::query()
                ->where('store_policy_id', $policy->getKey())
                ->where('language_id', $row['language_id'])
                ->max('version')) + 1;
            PolicyVersion::query()->create([
                'store_policy_id' => $policy->getKey(),
                'language_id' => $row['language_id'],
                'version' => $version,
                'content' => $row['content'],
                'created_by' => $user->getKey(),
            ]);
        }
    }

    private function localeKey(string $locale): string
    {
        return Str::lower(str_replace('-', '_', trim($locale)));
    }
}
