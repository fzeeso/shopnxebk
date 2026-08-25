<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\CreateMediaUploadRequest;
use App\Http\Requests\ListMediaRequest;
use App\Http\Resources\MediaResource;
use App\Services\Media\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MediaController extends Controller
{
    public function index(ListMediaRequest $request, MediaService $service): JsonResponse
    {
        $validated = $request->validated();

        return MediaResource::collection($service->list(
            $this->user($request),
            array_intersect_key($validated, array_flip(['status', 'mime_type', 'source', 'search'])),
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? 25),
        ))->response();
    }

    public function store(CreateMediaUploadRequest $request, MediaService $service): JsonResponse
    {
        $validated = $request->validated();

        return response()->json([
            'data' => new MediaResource($service->createUpload(
                $this->user($request),
                $request->file('file'),
                array_diff_key($validated, ['file' => true]),
            )),
        ], 201);
    }

    public function show(Request $request, string $media, MediaService $service): JsonResponse
    {
        return response()->json([
            'data' => new MediaResource($service->get($this->user($request), $media)),
        ]);
    }

    public function complete(Request $request, string $media, MediaService $service): JsonResponse
    {
        return response()->json([
            'data' => new MediaResource($service->completeUpload($this->user($request), $media)),
        ], 202);
    }

    public function destroy(Request $request, string $media, MediaService $service): JsonResponse
    {
        $service->delete($this->user($request), $media);

        return response()->json(null, 204);
    }

    public function content(Request $request, string $media, MediaService $service): StreamedResponse
    {
        $request->validate(['variant' => ['sometimes', 'string', 'max:30']]);

        return $service->stream($this->user($request), $media, $request->query('variant'));
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
