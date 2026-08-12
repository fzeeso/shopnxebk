<?php

declare(strict_types=1);

namespace App\Support\Translations;

use App\Enums\TranslationRequestStatus;
use App\Models\TranslationRequest;
use Illuminate\Support\Facades\DB;
use Modules\Stores\Models\Store;

final readonly class TranslationCoordinator
{
    public function __construct(
        private TranslationContentRegistry $registry,
        private TranslationRequestDispatcher $dispatcher,
    ) {}

    public function request(
        Store $store,
        string $contentType,
        int $contentId,
        ?string $expectedSourceLocale = null,
        bool $missingOnly = false,
        ?int $requestedBy = null,
    ): ?TranslationRequest {
        $snapshot = $this->registry->for($contentType)->snapshot(
            $store,
            $contentId,
            new TranslationSelection(
                expectedSourceLocale: $expectedSourceLocale,
                missingOnly: $missingOnly,
            ),
        );

        if ($snapshot === null || $snapshot->targetLocales === []) {
            return null;
        }

        $request = TranslationRequest::query()->withoutGlobalScopes()->firstOrCreate([
            'store_id' => $store->getKey(),
            'content_type' => $contentType,
            'content_id' => $contentId,
            'request_hash' => $snapshot->requestHash(),
        ], [
            'source_locale' => $snapshot->sourceLocale,
            'source_hash' => $snapshot->sourceHash(),
            'target_locales' => $snapshot->targetLocales,
            'status' => TranslationRequestStatus::Pending,
            'requested_by' => $requestedBy,
        ]);

        if (! $request->wasRecentlyCreated
            && ($request->status === TranslationRequestStatus::Failed
            || $request->status === TranslationRequestStatus::Cancelled)) {
            $request->forceFill([
                'status' => TranslationRequestStatus::Pending,
                'attempts' => 0,
                'last_error' => null,
                'requested_by' => $requestedBy,
                'queued_at' => null,
                'started_at' => null,
                'completed_at' => null,
            ])->save();
        }

        if ($request->status === TranslationRequestStatus::Pending) {
            $requestId = (int) $request->getKey();
            DB::afterCommit(fn (): bool => $this->dispatcher->dispatch($requestId));
        }

        return $request;
    }
}
