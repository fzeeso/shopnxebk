<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Enums\MediaStatus;
use App\Enums\MediaVariantName;
use App\Models\Media;
use App\Models\MediaVariant;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;
use Spatie\ImageOptimizer\OptimizerChainFactory;

final class MediaProcessor
{
    public function extractMetadata(Media $media): void
    {
        $this->withLocalCopy($media, function (string $localPath) use ($media): void {
            $image = @getimagesize($localPath);
            if ($image === false) {
                throw new RuntimeException('Uploaded media is not a readable image.');
            }

            $actualMime = (new \finfo(FILEINFO_MIME_TYPE))->file($localPath);
            if (! is_string($actualMime) || $actualMime === '') {
                throw new RuntimeException('Unable to determine the uploaded media MIME type.');
            }
            if (! array_key_exists($actualMime, (array) config('media-management.allowed_mime_types'))) {
                throw new RuntimeException('The uploaded media MIME type is not allowed.');
            }

            $media->forceFill([
                'mime_type' => $actualMime,
                'width' => (int) $image[0],
                'height' => (int) $image[1],
            ])->save();
        });
    }

    public function optimize(Media $media): void
    {
        $this->withLocalCopy($media, function (string $localPath) use ($media): void {
            OptimizerChainFactory::create((array) config('media-library.image_optimizers'))
                ->optimize($localPath);

            $stream = fopen($localPath, 'rb');
            if (! is_resource($stream)) {
                throw new RuntimeException('Unable to read optimized media.');
            }

            try {
                Storage::disk($media->disk)->put($media->path, $stream, [
                    'visibility' => $media->visibility->value,
                ]);
            } finally {
                fclose($stream);
            }

            $media->forceFill(['size' => filesize($localPath) ?: $media->size])->save();
        });
    }

    public function generateVariants(Media $media): void
    {
        $width = (int) $media->width;
        $height = (int) $media->height;
        if ($width < 1 || $height < 1) {
            throw new RuntimeException('Media dimensions must be extracted before variants are generated.');
        }

        MediaVariant::query()->updateOrCreate(
            ['media_id' => $media->getKey(), 'variant' => MediaVariantName::Original->value],
            [
                'disk' => $media->disk,
                'path' => $media->path,
                'mime_type' => $media->mime_type,
                'size' => $media->size,
                'width' => $width,
                'height' => $height,
                'metadata' => ['generated' => false],
            ],
        );

        $this->withLocalCopy($media, function (string $sourcePath) use ($media): void {
            foreach ((array) config('media-management.variants') as $name => $targetWidth) {
                $variant = MediaVariantName::tryFrom((string) $name);
                if ($variant === null || $variant === MediaVariantName::Original) {
                    continue;
                }

                $targetPath = $this->temporaryPath((string) $media->extension);
                try {
                    Image::load($sourcePath)
                        ->fit(Fit::Max, (int) $targetWidth, (int) $targetWidth)
                        ->quality((int) config('media-management.quality', 85))
                        ->save($targetPath);

                    OptimizerChainFactory::create((array) config('media-library.image_optimizers'))
                        ->optimize($targetPath);

                    $dimensions = @getimagesize($targetPath);
                    if ($dimensions === false) {
                        throw new RuntimeException("Unable to read generated {$name} media variant.");
                    }

                    $variantPath = rtrim((string) $media->directory, '/')
                        .'/variants/'.$name.'.'.(string) $media->extension;
                    $stream = fopen($targetPath, 'rb');
                    if (! is_resource($stream)) {
                        throw new RuntimeException("Unable to open generated {$name} media variant.");
                    }

                    try {
                        Storage::disk($media->disk)->put($variantPath, $stream, [
                            'visibility' => $media->visibility->value,
                        ]);
                    } finally {
                        fclose($stream);
                    }

                    MediaVariant::query()->updateOrCreate(
                        ['media_id' => $media->getKey(), 'variant' => $variant->value],
                        [
                            'disk' => $media->disk,
                            'path' => $variantPath,
                            'mime_type' => $media->mime_type,
                            'size' => filesize($targetPath) ?: 0,
                            'width' => (int) $dimensions[0],
                            'height' => (int) $dimensions[1],
                            'metadata' => [
                                'generated' => true,
                                'quality' => (int) config('media-management.quality', 85),
                            ],
                        ],
                    );
                } finally {
                    @unlink($targetPath);
                }
            }
        });
    }

    public function markReady(Media $media): void
    {
        if ($media->status !== MediaStatus::Deleted) {
            $media->forceFill(['status' => MediaStatus::Ready])->save();
        }
    }

    private function withLocalCopy(Media $media, callable $callback): void
    {
        if (! Storage::disk($media->disk)->exists($media->path)) {
            throw new RuntimeException('The media object is missing from storage.');
        }

        $localPath = $this->temporaryPath((string) $media->extension);
        $source = Storage::disk($media->disk)->readStream($media->path);
        $target = fopen($localPath, 'wb');
        if (! is_resource($source) || ! is_resource($target)) {
            if (is_resource($source)) {
                fclose($source);
            }
            if (is_resource($target)) {
                fclose($target);
            }
            throw new RuntimeException('Unable to create a local media processing copy.');
        }

        try {
            stream_copy_to_stream($source, $target);
        } finally {
            fclose($source);
            fclose($target);
        }

        try {
            $callback($localPath);
        } finally {
            @unlink($localPath);
        }
    }

    private function temporaryPath(string $extension): string
    {
        $extension = preg_replace('/[^a-z0-9]+/i', '', $extension) ?: 'bin';

        return sys_get_temp_dir().DIRECTORY_SEPARATOR.'shopnxe-media-'.Str::uuid().'.'.$extension;
    }
}
