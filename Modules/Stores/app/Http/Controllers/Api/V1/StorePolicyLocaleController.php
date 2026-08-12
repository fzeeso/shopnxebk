<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Controllers\Api\V1;

use App\Support\Translations\TranslationCoordinator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Models\User;
use Modules\Settings\Models\Language;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Http\Requests\UpsertStorePolicyTranslationRequest;
use Modules\Stores\Http\Resources\StorePolicyTranslationResource;
use Modules\Stores\Models\PolicyVersion;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StorePolicy;
use Modules\Stores\Models\StorePolicyTranslation;
use Modules\Stores\Services\StoreAccessService;

final class StorePolicyLocaleController extends Controller
{
    public function upsert(
        UpsertStorePolicyTranslationRequest $request,
        StorePolicy $storePolicy,
        Language $language,
        StorePolicyTranslationWorkflow $service,
    ): JsonResponse {
        return response()->json(['data' => new StorePolicyTranslationResource(
            $service->upsert($this->user($request), $storePolicy, $language, $request->validated()),
        )]);
    }

    public function destroy(
        Request $request,
        StorePolicy $storePolicy,
        Language $language,
        StorePolicyTranslationWorkflow $service,
    ): JsonResponse {
        $service->delete($this->user($request), $storePolicy, $language);

        return response()->json(null, 204);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}

final readonly class StorePolicyTranslationWorkflow
{
    public function __construct(
        private StoreContext $context,
        private StoreAccessService $access,
        private TranslationCoordinator $translationCoordinator,
    ) {}

    /** @param array<string, mixed> $data */
    public function upsert(User $user, StorePolicy $policy, Language $language, array $data): StorePolicyTranslation
    {
        $store = $this->managedStore($user);
        $this->ensureOwned($policy, $store);
        if (! $language->is_active) {
            throw ValidationException::withMessages(['language' => ['The selected language is inactive.']]);
        }
        if (! DB::table('store_languages')
            ->where('store_id', $store->getKey())
            ->where('language_id', $language->getKey())
            ->where('is_active', true)
            ->exists()) {
            throw ValidationException::withMessages([
                'language' => ['The selected language is not active for this Store.'],
            ]);
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

            $policy->forceFill(['updated_by' => $user->getKey()])->save();
            $translationRequest = $this->translationCoordinator->request(
                store: $store,
                contentType: 'store_policy',
                contentId: (int) $policy->getKey(),
                expectedSourceLocale: (string) $language->locale,
                requestedBy: (int) $user->getKey(),
            );

            return $translation->refresh()->load('language')
                ->setRelation('translationRequest', $translationRequest);
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
}
