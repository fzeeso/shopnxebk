<?php

declare(strict_types=1);

namespace App\Jobs\Media;

use App\Jobs\Media\Concerns\MarksMediaProcessingFailure;
use App\Models\Media;
use App\Services\Media\MediaProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ExtractMediaMetadata implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, MarksMediaProcessingFailure, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public readonly int $mediaId,
        public readonly int $storeId,
    ) {}

    public function handle(MediaProcessor $processor): void
    {
        $processor->extractMetadata($this->media());
    }

    /** @return list<string> */
    public function tags(): array
    {
        return ['media:'.$this->mediaId, 'store:'.$this->storeId, 'media-step:metadata'];
    }

    private function media(): Media
    {
        return Media::query()
            ->withoutGlobalScope('store')
            ->whereKey($this->mediaId)
            ->where('store_id', $this->storeId)
            ->firstOrFail();
    }
}
