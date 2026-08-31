<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\IdempotencyRecord;
use Illuminate\Console\Command;

final class PruneIdempotencyRecords extends Command
{
    /** @var string */
    protected $signature = 'idempotency:prune {--batch=} {--batches=}';

    /** @var string */
    protected $description = 'Delete expired HTTP idempotency response records in bounded batches.';

    public function handle(): int
    {
        $batchSize = max(1, (int) ($this->option('batch') ?: config('idempotency.pruning.batch_size', 1000)));
        $maximumBatches = max(1, (int) ($this->option('batches') ?: config('idempotency.pruning.maximum_batches', 10)));
        $deleted = 0;

        for ($batch = 0; $batch < $maximumBatches; $batch++) {
            $ids = IdempotencyRecord::query()
                ->where('expires_at', '<=', now())
                ->orderBy('id')
                ->limit($batchSize)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $count = IdempotencyRecord::query()->whereKey($ids)->delete();
            $deleted += $count;

            if ($count < $batchSize) {
                break;
            }
        }

        $this->info("Pruned {$deleted} expired idempotency records.");

        return self::SUCCESS;
    }
}
