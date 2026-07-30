<?php

declare(strict_types=1);

namespace Modules\Stores\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Authentication\Models\User;
use Modules\Settings\Models\Language;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StoreLanguage;

final readonly class StoreLanguageService
{
    public function __construct(private StoreAccessService $storeAccess) {}

    /** @return Collection<int, Language> */
    public function listForStore(User $user, Store $store): Collection
    {
        $this->storeAccess->ensureCanView($user, $store);

        return $this->catalogForStore($store);
    }

    /**
     * @param  list<string>  $languagePublicIds
     * @return Collection<int, Language>
     */
    public function updateForStore(
        User $user,
        Store $store,
        array $languagePublicIds,
        string $defaultLanguagePublicId,
    ): Collection {
        $this->storeAccess->ensureCanManage($user, $store);

        $languages = Language::query()
            ->where('is_active', true)
            ->whereIn('public_id', $languagePublicIds)
            ->get()
            ->keyBy('public_id');
        $defaultLanguage = $languages->get($defaultLanguagePublicId);

        if (! $defaultLanguage instanceof Language || $languages->count() !== count(array_unique($languagePublicIds))) {
            abort(422, 'The selected languages are invalid.');
        }

        DB::transaction(function () use ($defaultLanguage, $languages, $store): void {
            StoreLanguage::query()
                ->where('store_id', $store->getKey())
                ->update(['is_default' => false]);

            StoreLanguage::query()
                ->where('store_id', $store->getKey())
                ->whereNotIn('language_id', $languages->modelKeys())
                ->delete();

            foreach ($languages as $language) {
                StoreLanguage::query()->updateOrCreate(
                    [
                        'store_id' => $store->getKey(),
                        'language_id' => $language->getKey(),
                    ],
                    [
                        'is_active' => true,
                        'is_default' => $language->is($defaultLanguage),
                    ],
                );
            }

            $store->forceFill(['language_code' => $defaultLanguage->locale])->save();
        });

        return $this->catalogForStore($store);
    }

    /** @return Collection<int, Language> */
    private function catalogForStore(Store $store): Collection
    {
        $selections = StoreLanguage::query()
            ->where('store_id', $store->getKey())
            ->get()
            ->keyBy('language_id');

        return Language::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->each(function (Language $language) use ($selections): void {
                $selection = $selections->get($language->getKey());
                $language->setAttribute(
                    'store_is_selected',
                    $selection instanceof StoreLanguage && $selection->is_active,
                );
                $language->setAttribute(
                    'store_is_default',
                    $selection instanceof StoreLanguage && $selection->is_default,
                );
            });
    }
}
