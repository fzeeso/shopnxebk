<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Settings\Models\Language;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Http\Requests\UpdateStoreLanguagesRequest;
use Modules\Stores\Http\Resources\StoreLanguageOptionResource;
use Modules\Stores\Models\Store;
use Modules\Stores\Services\StoreLanguageService;

final class StoreLanguageController extends Controller
{
    public function index(
        Request $request,
        StoreContext $context,
        StoreLanguageService $service,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $store = $context->require();
        $languages = $service->listForStore($user, $store);

        return response()->json(['data' => $this->payload($store, $languages)]);
    }

    public function update(
        UpdateStoreLanguagesRequest $request,
        StoreContext $context,
        StoreLanguageService $service,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $store = $context->require();
        $data = $request->validated();
        $languages = $service->updateForStore(
            $user,
            $store,
            $data['language_ids'],
            $data['default_language_id'],
        );

        return response()->json(['data' => $this->payload($store->refresh(), $languages)]);
    }

    private function payload(Store $store, mixed $languages): array
    {
        $defaultLanguage = $languages->first(
            fn (Language $language): bool => (bool) $language->getAttribute('store_is_default'),
        );

        return [
            'store_id' => $store->public_id,
            'default_language_id' => $defaultLanguage?->public_id,
            'languages' => StoreLanguageOptionResource::collection($languages),
        ];
    }
}
