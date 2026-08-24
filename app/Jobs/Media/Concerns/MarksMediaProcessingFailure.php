<?php

declare(strict_types=1);

namespace App\Jobs\Media\Concerns;

use App\Enums\MediaStatus;
use App\Models\Media;
use Throwable;

trait MarksMediaProcessingFailure
{
    public function failed(?Throwable $exception): void
    {
        $media = Media::query()
            ->withoutGlobalScope('store')
            ->whereKey($this->mediaId)
            ->where('store_id', $this->storeId)
            ->where('status', '!=', MediaStatus::Deleted->value)
            ->first();
        if ($media === null) {
            return;
        }

        $metadata = $media?->metadata ?? [];
        $metadata['processing_failure'] = [
            'job' => static::class,
            'message' => $exception?->getMessage() ?? 'Media processing failed.',
            'failed_at' => now()->toIso8601String(),
        ];

        $media->forceFill([
            'status' => MediaStatus::Failed,
            'metadata' => $metadata,
        ])->save();
    }
}
