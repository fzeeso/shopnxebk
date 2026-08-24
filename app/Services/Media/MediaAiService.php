<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Models\Media;
use App\Models\MediaAiResult;
use Modules\Authentication\Models\User;

/**
 * Persistence boundary for future AI providers. This service intentionally
 * performs no external provider calls.
 */
final readonly class MediaAiService
{
    public function __construct(private MediaAccessService $access) {}

    public function start(
        User $user,
        string $mediaPublicId,
        string $operation,
        ?string $provider = null,
        ?string $model = null,
    ): MediaAiResult {
        $store = $this->access->manage($user);
        $media = Media::query()
            ->where('store_id', $store->getKey())
            ->where('public_id', $mediaPublicId)
            ->where('status', '!=', 'deleted')
            ->firstOrFail();

        return $media->aiResults()->create([
            'provider' => $provider,
            'model' => $model,
            'operation' => $operation,
            'status' => 'processing',
        ]);
    }

    /** @param array<string, mixed> $result */
    public function complete(MediaAiResult $aiResult, array $result, ?float $confidence = null): MediaAiResult
    {
        $aiResult->forceFill([
            'status' => 'completed',
            'result' => $result,
            'confidence' => $confidence,
        ])->save();

        return $aiResult->refresh();
    }

    /** @param array<string, mixed> $result */
    public function fail(MediaAiResult $aiResult, array $result): MediaAiResult
    {
        $aiResult->forceFill(['status' => 'failed', 'result' => $result])->save();

        return $aiResult->refresh();
    }
}
