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
        $directory = trim((string) $media->getAttribute('directory'), '/');
        if ($directory !== '') {
            return $directory;
        }

        $storeId = $media->getAttribute('store_id');
        $storePublicId = $storeId === null ? null : Store::query()->whereKey($storeId)->value('public_id');
        $mediaPublicId = (string) $media->getAttribute('public_id');

        if ($mediaPublicId === '') {
            throw new \LogicException('Media requires public_id before path generation.');
        }

        if ($storePublicId === null) {
            return 'platform/media/'.$mediaPublicId;
        }

        return 'stores/'.$storePublicId.'/media/'.$mediaPublicId;
    }
}
