<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\MediaAiOperation;
use App\Exceptions\OpenAiMediaException;
use App\Http\Requests\GenerateMediaAiImageRequest;
use App\Http\Requests\RunMediaAiOperationRequest;
use App\Http\Resources\MediaAiResultResource;
use App\Http\Resources\MediaResource;
use App\Services\Media\MediaAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;

final class MediaAiController extends Controller
{
    public function generate(
        GenerateMediaAiImageRequest $request,
        MediaAiService $service,
    ): JsonResponse {
        try {
            $mediaItems = $service->generate($this->user($request), $request->validated());
        } catch (OpenAiMediaException $exception) {
            return $this->providerFailure($exception);
        }

        return response()->json([
            'data' => MediaResource::collection(collect($mediaItems)),
        ], 201);
    }

    public function run(
        RunMediaAiOperationRequest $request,
        string $media,
        MediaAiService $service,
    ): JsonResponse {
        $validated = $request->validated();

        try {
            $result = $service->run(
                $this->user($request),
                $media,
                MediaAiOperation::from((string) $validated['operation']),
                is_string($validated['quality'] ?? null) ? $validated['quality'] : null,
            );
        } catch (OpenAiMediaException $exception) {
            return $this->providerFailure($exception);
        }

        return response()->json([
            'data' => new MediaAiResultResource($result['ai_result']),
            'media' => new MediaResource($result['media']),
            'generated_media' => $result['generated_media'] === null
                ? null
                : new MediaResource($result['generated_media']),
        ], $result['generated_media'] === null ? 200 : 201);
    }

    public function history(
        Request $request,
        string $media,
        MediaAiService $service,
    ): JsonResponse {
        return MediaAiResultResource::collection(
            $service->history($this->user($request), $media),
        )->response();
    }

    private function providerFailure(OpenAiMediaException $exception): JsonResponse
    {
        return response()->json(['message' => $exception->getMessage()], $exception->httpStatus());
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
