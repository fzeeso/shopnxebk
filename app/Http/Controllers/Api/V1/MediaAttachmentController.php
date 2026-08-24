<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\AttachMediaRequest;
use App\Services\Media\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;

final class MediaAttachmentController extends Controller
{
    public function attachProduct(
        AttachMediaRequest $request,
        string $product,
        MediaService $service,
    ): JsonResponse {
        $data = $request->validated();
        $attachment = $service->attachToProduct(
            $this->user($request),
            $product,
            $data['media_id'],
            (int) ($data['sort_order'] ?? 0),
            array_key_exists('is_primary', $data) ? (bool) $data['is_primary'] : null,
        );

        return response()->json(['data' => [
            'product_id' => $product,
            'media_id' => $data['media_id'],
            'sort_order' => $attachment->sort_order,
            'is_primary' => $attachment->is_primary,
        ]], 201);
    }

    public function detachProduct(
        Request $request,
        string $product,
        string $media,
        MediaService $service,
    ): JsonResponse {
        $service->detachFromProduct($this->user($request), $product, $media);

        return response()->json(null, 204);
    }

    public function setPrimary(
        Request $request,
        string $product,
        string $media,
        MediaService $service,
    ): JsonResponse {
        $attachment = $service->setPrimaryProductMedia($this->user($request), $product, $media);

        return response()->json(['data' => [
            'product_id' => $product,
            'media_id' => $media,
            'sort_order' => $attachment->sort_order,
            'is_primary' => true,
        ]]);
    }

    public function attachVariant(
        AttachMediaRequest $request,
        string $variant,
        MediaService $service,
    ): JsonResponse {
        $data = $request->validated();
        $attachment = $service->attachToProductVariant(
            $this->user($request),
            $variant,
            $data['media_id'],
            (int) ($data['sort_order'] ?? 0),
        );

        return response()->json(['data' => [
            'product_variant_id' => $variant,
            'media_id' => $data['media_id'],
            'sort_order' => $attachment->sort_order,
        ]], 201);
    }

    public function detachVariant(
        Request $request,
        string $variant,
        string $media,
        MediaService $service,
    ): JsonResponse {
        $service->detachFromProductVariant($this->user($request), $variant, $media);

        return response()->json(null, 204);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
