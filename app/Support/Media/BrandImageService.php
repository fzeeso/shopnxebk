<?php

declare(strict_types=1);

namespace App\Support\Media;

use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class BrandImageService
{
    public function replace(HasMedia $owner, string $collection, ?UploadedFile $image): ?Media
    {
        if ($image === null) {
            $owner->clearMediaCollection($collection);

            return null;
        }

        return $owner
            ->addMedia($image)
            ->usingName(pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME))
            ->toMediaCollection($collection);
    }
}
