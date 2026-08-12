<?php

declare(strict_types=1);

namespace App\Support\Translations;

use App\Enums\TranslationRequestStatus;
use App\Models\TranslationRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Models\Store;
use Throwable;

final readonly class TranslationProcessor
{
    public function __construct(
        private TranslationContentRegistry $registry,
        private TranslationProvider $provider,
        private StoreContext $context,
    ) {}

    public function process(int $requestId): void
    {
        $request = TranslationRequest::query()->withoutGlobalScopes()->findOrFail($requestId);
        if ($request->status->isTerminal()) {
            return;
        }

        $store = Store::query()->findOrFail($request->store_id);
        $previousStore = $this->context->current();

        $store->execute(function () use ($previousStore, $requestId, $store): void {
            $this->context->set($store);
            try {
                $this->processForStore($requestId, $store);
            } finally {
                $previousStore === null
                    ? $this->context->clear()
                    : $this->context->set($previousStore);
            }
        });
    }

    public function recordFailure(int $requestId, Throwable $exception, bool $terminal = false): void
    {
        $request = TranslationRequest::query()->withoutGlobalScopes()->find($requestId);
        if (! $request instanceof TranslationRequest || $request->status->isTerminal()) {
            return;
        }

        $terminal = $terminal || $request->attempts >= max(1, (int) config('translations.max_attempts', 3));
        $request->forceFill([
            'status' => $terminal ? TranslationRequestStatus::Failed : TranslationRequestStatus::Pending,
            'last_error' => Str::limit($exception->getMessage(), 2000, ''),
            'completed_at' => $terminal ? now() : null,
        ])->save();
    }

    private function processForStore(int $requestId, Store $store): void
    {
        $request = DB::transaction(function () use ($requestId): ?TranslationRequest {
            $request = TranslationRequest::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($requestId);
            if ($request->status !== TranslationRequestStatus::Pending) {
                return null;
            }

            $request->forceFill([
                'status' => TranslationRequestStatus::Processing,
                'attempts' => $request->attempts + 1,
                'last_error' => null,
                'started_at' => now(),
            ])->save();

            return $request;
        });

        if (! $request instanceof TranslationRequest) {
            return;
        }

        $handler = $this->registry->for($request->content_type);
        $selection = new TranslationSelection(
            expectedSourceLocale: $request->source_locale,
            targetLocales: $request->target_locales,
        );
        $snapshot = $handler->snapshot($store, $request->content_id, $selection);

        if ($snapshot === null) {
            $this->finish($request, TranslationRequestStatus::Cancelled, 'The source content no longer exists.');

            return;
        }
        if (! hash_equals($request->source_hash, $snapshot->sourceHash())) {
            $this->finish($request, TranslationRequestStatus::Superseded);

            return;
        }
        if ($snapshot->targetLocales === []) {
            $this->finish($request, TranslationRequestStatus::Completed);

            return;
        }

        $translations = $this->provider->translateFields(
            $snapshot->sourceFields,
            $snapshot->sourceLocale,
            $snapshot->targetLocales,
            $snapshot->contentDescription,
            $snapshot->requiredFields,
        );

        DB::transaction(function () use ($handler, $requestId, $selection, $store, $translations): void {
            $locked = TranslationRequest::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($requestId);
            if ($locked->status !== TranslationRequestStatus::Processing) {
                return;
            }

            $latest = $handler->snapshot($store, $locked->content_id, $selection);
            if ($latest === null) {
                $this->finish($locked, TranslationRequestStatus::Cancelled, 'The source content no longer exists.');

                return;
            }
            if (! hash_equals($locked->source_hash, $latest->sourceHash())) {
                $this->finish($locked, TranslationRequestStatus::Superseded);

                return;
            }

            $handler->apply($locked, $latest, $translations);
            $this->finish($locked, TranslationRequestStatus::Completed);
        });
    }

    private function finish(
        TranslationRequest $request,
        TranslationRequestStatus $status,
        ?string $error = null,
    ): void {
        $request->forceFill([
            'status' => $status,
            'last_error' => $error,
            'completed_at' => now(),
        ])->save();
    }
}
