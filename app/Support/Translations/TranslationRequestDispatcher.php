<?php

declare(strict_types=1);

namespace App\Support\Translations;

use App\Enums\TranslationRequestStatus;
use App\Jobs\Translations\TranslateContentJob;
use App\Models\TranslationRequest;
use Illuminate\Support\Facades\Log;
use Modules\Stores\Models\Store;
use Throwable;

final class TranslationRequestDispatcher
{
    public function dispatch(int $requestId): bool
    {
        $request = TranslationRequest::query()->withoutGlobalScopes()->find($requestId);
        if (! $request instanceof TranslationRequest || $request->status !== TranslationRequestStatus::Pending) {
            return false;
        }

        $store = Store::query()->find($request->store_id);
        if (! $store instanceof Store) {
            $request->forceFill([
                'status' => TranslationRequestStatus::Cancelled,
                'last_error' => 'The Store no longer exists.',
                'completed_at' => now(),
            ])->save();

            return false;
        }

        try {
            $store->execute(function () use ($requestId): void {
                TranslateContentJob::dispatch($requestId)
                    ->onConnection((string) config('translations.queue_connection', 'redis'))
                    ->onQueue((string) config('translations.queue', 'translations'));
            });

            $request->forceFill(['queued_at' => now()])->saveQuietly();

            return true;
        } catch (Throwable $exception) {
            Log::warning('Automatic translation dispatch failed; recovery will retry it.', [
                'translation_request_id' => $requestId,
                'store_id' => $request->store_id,
                'exception' => $exception::class,
            ]);

            return false;
        }
    }

    public function dispatchPending(): int
    {
        $batchSize = max(1, (int) config('translations.recovery_batch_size', 100));
        $recoveryBefore = now()->subMinutes(max(1, (int) config('translations.recovery_after_minutes', 10)));
        $maxAttempts = max(1, (int) config('translations.max_attempts', 3));
        TranslationRequest::query()
            ->withoutGlobalScopes()
            ->where('status', TranslationRequestStatus::Processing->value)
            ->where('started_at', '<=', $recoveryBefore)
            ->where('attempts', '>=', $maxAttempts)
            ->update([
                'status' => TranslationRequestStatus::Failed->value,
                'last_error' => 'The translation worker stopped before completing the request.',
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
        TranslationRequest::query()
            ->withoutGlobalScopes()
            ->where('status', TranslationRequestStatus::Processing->value)
            ->where('started_at', '<=', $recoveryBefore)
            ->where('attempts', '<', $maxAttempts)
            ->update([
                'status' => TranslationRequestStatus::Pending->value,
                'queued_at' => null,
                'completed_at' => null,
                'updated_at' => now(),
            ]);
        $requests = TranslationRequest::query()
            ->withoutGlobalScopes()
            ->where('status', TranslationRequestStatus::Pending->value)
            ->where(function ($query) use ($recoveryBefore): void {
                $query->whereNull('queued_at')->orWhere('queued_at', '<=', $recoveryBefore);
            })
            ->orderBy('id')
            ->limit($batchSize)
            ->pluck('id');

        $dispatched = 0;
        foreach ($requests as $requestId) {
            if ($this->dispatch((int) $requestId)) {
                $dispatched++;
            }
        }

        return $dispatched;
    }
}
