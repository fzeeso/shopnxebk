<?php

declare(strict_types=1);

namespace App\Support\Media;

use Modules\Stores\Models\Store;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

final class StorePathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return $this->base($media).'/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->base($media).'/conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->base($media).'/responsive-images/';
    }

    private function base(Media $media): string
    {
        $storeId = $media->getAttribute('store_id');
        $storePublicId = $storeId === null ? null : Store::query()->whereKey($storeId)->value('public_id');
        $mediaPublicId = (string) $media->getAttribute('public_id');

        if ($storePublicId === null || $mediaPublicId === '') {
            throw new \LogicException('Media requires store_id and public_id before path generation.');
        }

        return 'stores/'.$storePublicId.'/media/'.$mediaPublicId;
    }
}
