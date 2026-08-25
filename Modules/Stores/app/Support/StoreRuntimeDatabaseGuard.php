<?php

declare(strict_types=1);

namespace Modules\Stores\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class StoreRuntimeDatabaseGuard
{
    /** @var list<string> */
    private const BLOCKED_SCHEMA_COMMANDS = [
        'ALTER',
        'CALL',
        'COMMENT',
        'CREATE',
        'DO',
        'DROP',
        'GRANT',
        'REINDEX',
        'REVOKE',
        'TRUNCATE',
    ];

    public function assertAllowed(Request $request, string $query): void
    {
        if (! $this->protects($request)) {
            return;
        }

        $command = $this->command($query);
        if (in_array($command, self::BLOCKED_SCHEMA_COMMANDS, true)) {
            throw new AccessDeniedHttpException(
                'Database schema commands are not allowed from Store or GraphQL requests.',
            );
        }
    }

    private function protects(Request $request): bool
    {
        return $request->is('api/v1/store', 'api/v1/store/*', 'graphql');
    }

    private function command(string $query): string
    {
        $query = preg_replace('/\A\s*(?:(?:--[^\r\n]*(?:\r?\n|\z))|(?:\/\*.*?\*\/\s*))*/s', '', $query) ?? $query;
        if (preg_match('/\A([a-z]+)/i', ltrim($query), $matches) !== 1) {
            return '';
        }

        return strtoupper($matches[1]);
    }
}
