<?php

declare(strict_types=1);

namespace App\Jobs\Translations;

use App\Support\Translations\TranslationProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class TranslateContentJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 90;

    public int $uniqueFor = 600;

    public function __construct(public readonly int $translationRequestId) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function uniqueId(): string
    {
        return (string) $this->translationRequestId;
    }

    public function handle(TranslationProcessor $processor): void
    {
        try {
            $processor->process($this->translationRequestId);
        } catch (Throwable $exception) {
            $processor->recordFailure(
                $this->translationRequestId,
                $exception,
                $this->attempts() >= $this->tries,
            );

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception !== null) {
            app(TranslationProcessor::class)->recordFailure(
                $this->translationRequestId,
                $exception,
                true,
            );
        }
    }

    /** @return list<string> */
    public function tags(): array
    {
        return ['translation-request:'.$this->translationRequestId];
    }
}
