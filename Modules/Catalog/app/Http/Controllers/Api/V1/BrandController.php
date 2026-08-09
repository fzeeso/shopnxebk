<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers\Api\V1;

use App\Http\Requests\BrandWriteRequest;
use App\Http\Requests\PaginatedIndexRequest;
use App\Models\Brand;
use App\Models\BrandTranslation;
use App\Support\Media\BrandManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Modules\Authentication\Models\User;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class BrandController extends Controller
{
    public function index(PaginatedIndexRequest $request, BrandManagementService $service): JsonResponse
    {
        return BrandResponseResource::collection($service->list($this->user($request), $request->perPage()))->response();
    }

    public function store(BrandWriteRequest $request, BrandManagementService $service): JsonResponse
    {
        return response()->json(['data' => new BrandResponseResource(
            $service->create($this->user($request), $request->validated()),
        )], 201);
    }

    public function show(Request $request, Brand $brand, BrandManagementService $service): JsonResponse
    {
        return response()->json(['data' => new BrandResponseResource(
            $service->show($this->user($request), $brand),
        )]);
    }

    public function update(BrandWriteRequest $request, Brand $brand, BrandManagementService $service): JsonResponse
    {
        return response()->json(['data' => new BrandResponseResource(
            $service->update($this->user($request), $brand, $request->validated()),
        )]);
    }

    public function destroy(Request $request, Brand $brand, BrandManagementService $service): JsonResponse
    {
        $service->delete($this->user($request), $brand);

        return response()->json(null, 204);
    }

    public function media(string $brand, string $collection): StreamedResponse
    {
        if (! in_array($collection, [Brand::MEDIA_IMAGE, Brand::MEDIA_BANNER], true)) {
            abort(404);
        }

        $model = Brand::query()
            ->withoutGlobalScopes()
            ->where('public_id', $brand)
            ->firstOrFail();
        $media = $model->getFirstMedia($collection);

        if ($media === null) {
            abort(404);
        }

        return Storage::disk($media->disk)->response(
            $media->getPathRelativeToRoot(),
            $media->file_name,
            [
                'Cache-Control' => 'private, max-age=300',
                'Content-Type' => $media->mime_type,
            ],
        );
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}

/** @extends JsonResource<Brand> */
final class BrandResponseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'store_id' => $this->whenLoaded('store', fn () => $this->store?->public_id),
            'image' => $this->mediaAsset(Brand::MEDIA_IMAGE),
            'banner' => $this->mediaAsset(Brand::MEDIA_BANNER),
            'logo_url' => $this->logo_url,
            'website_url' => $this->website_url,
            'origin' => $this->origin,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'translations' => $this->whenLoaded('translations', fn () => $this->translations
                ->map(static fn (BrandTranslation $translation): array => [
                    'locale' => $translation->locale,
                    'name' => $translation->name,
                    'slug' => $translation->slug,
                    'description' => $translation->description,
                    'seo_title' => $translation->seo_title,
                    'seo_description' => $translation->seo_description,
                    'lock_it' => $translation->lock_it,
                    'created_at' => $translation->created_at?->toIso8601String(),
                    'updated_at' => $translation->updated_at?->toIso8601String(),
                ])
                ->values()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function mediaAsset(string $collection): ?array
    {
        $media = $this->resource->getFirstMedia($collection);
        if (! $media instanceof Media) {
            return null;
        }

        return [
            'id' => $media->getAttribute('public_id'),
            'url' => URL::temporarySignedRoute(
                'api.v1.store.brands.media',
                now()->addMinutes(15),
                [
                    'brand' => $this->public_id,
                    'collection' => $collection,
                ],
                absolute: false,
            ),
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
        ];
    }
}
