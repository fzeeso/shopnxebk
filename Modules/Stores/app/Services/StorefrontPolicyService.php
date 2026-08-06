<?php

declare(strict_types=1);

namespace Modules\Stores\Services;

use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StorePolicy;
use Modules\Stores\Models\StorePolicyTranslation;

final readonly class StorefrontPolicyService
{
    public function __construct(private StoreContext $context) {}

    /** @return list<array<string, mixed>> */
    public function list(?string $locale = null): array
    {
        $store = $this->context->require();

        return StorePolicy::query()
            ->where('store_id', $store->getKey())
            ->where('status', 'published')
            ->with(['policyType', 'translations.language'])
            ->join('policy_types', 'policy_types.id', '=', 'store_policies.policy_type_id')
            ->select('store_policies.*')
            ->orderBy('policy_types.sort_order')
            ->get()
            ->map(fn (StorePolicy $policy): ?array => $this->payload($policy, $store, $locale))
            ->filter()
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function show(string $slug, ?string $locale = null): array
    {
        $store = $this->context->require();
        $policy = StorePolicy::query()
            ->where('store_id', $store->getKey())
            ->where('slug', $slug)
            ->where('status', 'published')
            ->with(['policyType', 'translations.language'])
            ->firstOrFail();

        return $this->payload($policy, $store, $locale) ?? abort(404);
    }

    /** @return array<string, mixed>|null */
    private function payload(StorePolicy $policy, Store $store, ?string $locale): ?array
    {
        $requested = strtolower(str_replace('-', '_', trim((string) $locale)));
        $fallback = strtolower(str_replace('-', '_', $store->language_code));
        $translation = $policy->translations->first(
            fn (StorePolicyTranslation $item): bool => $requested !== '' && strtolower($item->language->locale) === $requested,
        ) ?? $policy->translations->first(
            fn (StorePolicyTranslation $item): bool => strtolower($item->language->locale) === $fallback,
        ) ?? $policy->translations->first();

        if (! $translation instanceof StorePolicyTranslation) {
            return null;
        }

        return [
            'id' => $policy->public_id,
            'type' => [
                'code' => $policy->policyType->code,
                'name' => $policy->policyType->name,
            ],
            'title' => $translation->title,
            'slug' => $policy->slug,
            'locale' => $translation->language->locale,
            'content' => $translation->content,
            'seo_title' => $translation->seo_title,
            'seo_description' => $translation->seo_description,
            'published_at' => $policy->published_at?->toIso8601String(),
            'updated_at' => $translation->updated_at?->toIso8601String(),
        ];
    }
}
