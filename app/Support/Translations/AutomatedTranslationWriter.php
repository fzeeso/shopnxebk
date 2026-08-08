<?php

declare(strict_types=1);

namespace App\Support\Translations;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use LogicException;

final class AutomatedTranslationWriter
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $uniqueBy
     * @param  list<string>  $updateColumns
     */
    public function upsert(string $table, array $rows, array $uniqueBy, array $updateColumns): int
    {
        $this->ensureTranslationTable($table);

        if ($rows === []) {
            return 0;
        }
        if ($uniqueBy === [] || $updateColumns === []) {
            throw new InvalidArgumentException('Automated translation upserts require identity and update columns.');
        }

        return DB::transaction(function () use ($table, $rows, $uniqueBy, $updateColumns): int {
            $written = 0;
            $safeUpdateColumns = array_values(array_diff($updateColumns, ['lock_it']));

            if ($safeUpdateColumns === []) {
                throw new InvalidArgumentException('Automated translation upserts may not update only lock_it.');
            }

            foreach ($rows as $row) {
                $identity = [];
                foreach ($uniqueBy as $column) {
                    if (! array_key_exists($column, $row)) {
                        throw new InvalidArgumentException("Missing translation identity column [{$column}].");
                    }
                    $identity[$column] = $row[$column];
                }

                $existing = DB::table($table)
                    ->where($identity)
                    ->lockForUpdate()
                    ->first(['lock_it']);

                if ($existing !== null && (bool) $existing->lock_it) {
                    continue;
                }

                unset($row['lock_it']);
                DB::table($table)->upsert([$row], $uniqueBy, $safeUpdateColumns);
                $written++;
            }

            return $written;
        });
    }

    private function ensureTranslationTable(string $table): void
    {
        if (preg_match('/^[a-z][a-z0-9_]*_translations$/', $table) !== 1) {
            throw new InvalidArgumentException('Automated translation writes require a *_translations table.');
        }
        if (! Schema::hasColumn($table, 'lock_it')) {
            throw new LogicException("Translation table [{$table}] must define lock_it.");
        }
    }
}
