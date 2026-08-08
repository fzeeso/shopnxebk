<?php

declare(strict_types=1);

namespace App\Support\Media;

use App\Models\Brand;
use App\Models\BrandTranslation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** @extends JsonResource<Brand> */
final class BrandResource extends JsonResource
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
            'url' => $media->getUrl(),
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
        ];
    }
}
