<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$output = $root.'/docs/generated/system-inventory.md';
$graphqlOutput = $root.'/docs/generated/graphql-operations.md';
$checkOnly = in_array('--check', $argv, true);

/** @return array<string, mixed> */
function jsonFile(string $path): array
{
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException("Unable to read {$path}.");
    }

    $value = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    if (! is_array($value)) {
        throw new RuntimeException("Expected a JSON object in {$path}.");
    }

    return $value;
}

function cell(string $value): string
{
    return str_replace(['|', "\r", "\n"], ['\|', '', '<br>'], trim($value));
}

function relativeTo(string $root, string $path): string
{
    $root = str_replace('\\', '/', rtrim($root, '/\\')).'/';
    $path = str_replace('\\', '/', $path);

    return str_starts_with($path, $root) ? substr($path, strlen($root)) : $path;
}

/** @return list<array<string, mixed>> */
function routes(string $root): array
{
    $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/artisan').' route:list --json --no-ansi';
    $lines = [];
    $exitCode = 0;
    exec($command.' 2>&1', $lines, $exitCode);

    if ($exitCode !== 0) {
        throw new RuntimeException("Unable to inspect routes:\n".implode("\n", $lines));
    }

    $routes = json_decode(implode("\n", $lines), true, 512, JSON_THROW_ON_ERROR);
    $routes = array_values(array_filter(
        is_array($routes) ? $routes : [],
        static fn (mixed $route): bool => is_array($route)
            && is_string($route['uri'] ?? null)
            && ($route['uri'] === 'graphql'
                || $route['uri'] === 'sanctum/csrf-cookie'
                || str_starts_with($route['uri'], 'api/')),
    ));
    usort($routes, static fn (array $a, array $b): int => [$a['uri'], $a['method']] <=> [$b['uri'], $b['method']]);

    return $routes;
}

function middlewareName(string $middleware): string
{
    return str_replace(
        [
            'Illuminate\\Auth\\Middleware\\Authenticate:sanctum',
            'Illuminate\\Routing\\Middleware\\ThrottleRequests:',
            'Illuminate\\Routing\\Middleware\\ValidateSignature',
            'Modules\\Stores\\Http\\Middleware\\ResolveOptionalStore',
            'Modules\\Stores\\Http\\Middleware\\ResolveStore',
            'Modules\\Stores\\Http\\Middleware\\EnsureStoreMembership',
            'Nuwave\\Lighthouse\\Http\\Middleware\\AcceptJson',
            'Nuwave\\Lighthouse\\Http\\Middleware\\AttemptAuthentication',
        ],
        [
            'auth:sanctum',
            'throttle:',
            'signed',
            'store.optional',
            'store',
            'store.member',
            'lighthouse.accept-json',
            'lighthouse.authenticate',
        ],
        $middleware,
    );
}

/** @return list<array{type: string, field: string, signature: string, schema: string, protection: string}> */
function graphQlOperations(string $root): array
{
    $files = array_merge(
        glob($root.'/graphql/*.graphql') ?: [],
        glob($root.'/Modules/*/graphql/*.graphql') ?: [],
    );
    sort($files);
    $operations = [];

    foreach ($files as $file) {
        $schema = file_get_contents($file);
        if ($schema === false) {
            throw new RuntimeException("Unable to read {$file}.");
        }

        preg_match_all('/(?:extend\s+)?type\s+(Query|Mutation)\s*\{([^}]*)}/s', $schema, $blocks, PREG_SET_ORDER);
        foreach ($blocks as $block) {
            foreach (preg_split('/\R/', trim($block[2])) ?: [] as $line) {
                if (preg_match('/^\s*([_A-Za-z][_0-9A-Za-z]*)\s*(?:\([^)]*\))?\s*:/', $line, $field)) {
                    $signature = trim(explode('@', trim($line), 2)[0]);
                    $operations[] = [
                        'type' => $block[1],
                        'field' => $field[1],
                        'signature' => $signature,
                        'schema' => relativeTo($root, $file),
                        'protection' => str_contains($line, '@guard') ? 'Sanctum guard' : 'Public',
                    ];
                }
            }
        }
    }

    usort($operations, static fn (array $a, array $b): int => [$a['type'], $a['field']] <=> [$b['type'], $b['field']]);

    return $operations;
}

/** @return array<string, list<string>> */
function environmentGroups(string $root): array
{
    $contents = file_get_contents($root.'/.env.example');
    if ($contents === false) {
        throw new RuntimeException('Unable to read .env.example.');
    }

    $groups = [
        'Application and frontend' => [],
        'PostgreSQL' => [],
        'Redis, cache, session, and queue' => [],
        'Search' => [],
        'Files and media' => [],
        'Reverb and Octane' => [],
        'Observability and GraphQL' => [],
        'Logging, mail, and local administration' => [],
    ];

    foreach (preg_split('/\R/', $contents) ?: [] as $line) {
        if (! preg_match('/^([A-Z][A-Z0-9_]*)=/', trim($line), $match)) {
            continue;
        }

        $key = $match[1];
        $group = match (true) {
            str_starts_with($key, 'DB_') => 'PostgreSQL',
            preg_match('/^(REDIS|CACHE|SESSION|QUEUE)_/', $key) === 1 => 'Redis, cache, session, and queue',
            preg_match('/^(SCOUT|MEILISEARCH)_/', $key) === 1 => 'Search',
            preg_match('/^(FILESYSTEM|MEDIA|AWS)_/', $key) === 1 => 'Files and media',
            preg_match('/^(REVERB|BROADCAST|OCTANE)_/', $key) === 1 => 'Reverb and Octane',
            preg_match('/^(PULSE|TELESCOPE|INTERNAL|GRAPHQL|LIGHTHOUSE|HORIZON)_/', $key) === 1 => 'Observability and GraphQL',
            preg_match('/^(LOG|MAIL|PLATFORM)_/', $key) === 1 => 'Logging, mail, and local administration',
            default => 'Application and frontend',
        };
        $groups[$group][] = $key;
    }

    return $groups;
}

$composer = jsonFile($root.'/composer.json');
$lock = jsonFile($root.'/composer.lock');
$statuses = jsonFile($root.'/modules_statuses.json');
$graphqlOperations = graphQlOperations($root);
$locked = [];

foreach (array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []) as $package) {
    if (is_array($package) && isset($package['name'], $package['version'])) {
        $locked[(string) $package['name']] = (string) $package['version'];
    }
}

$purposes = [
    'laravel/framework' => 'Core framework, HTTP, Eloquent, validation, events, notifications, and queues.',
    'laravel/fortify' => 'TOTP generation, QR provisioning, encrypted recovery codes, and MFA events.',
    'laravel/horizon' => 'Redis queue supervision.',
    'laravel/octane' => 'FrankenPHP long-running workers.',
    'laravel/pulse' => 'Performance monitoring.',
    'laravel/reverb' => 'WebSocket broadcasting.',
    'laravel/sanctum' => 'Cookie and bearer-token authentication.',
    'laravel/scout' => 'Search indexing abstraction.',
    'league/flysystem-aws-s3-v3' => 'S3-compatible private storage.',
    'meilisearch/meilisearch-php' => 'Meilisearch client.',
    'nuwave/lighthouse' => 'GraphQL schema and execution.',
    'nwidart/laravel-modules' => 'Modular-monolith lifecycle.',
    'predis/predis' => 'Redis client fallback.',
    'spatie/laravel-medialibrary' => 'Media and conversion foundation.',
    'spatie/laravel-multitenancy' => 'Store context lifecycle and store-aware jobs.',
    'spatie/laravel-permission' => 'Store-team roles and permissions.',
    'larastan/larastan' => 'Laravel-aware static analysis.',
    'laravel/pint' => 'PHP formatting.',
    'laravel/telescope' => 'Local-only diagnostics.',
    'phpunit/phpunit' => 'PostgreSQL-backed tests.',
];

$lines = [
    '# Generated system inventory',
    '',
    '> Generated by `php scripts/update-developer-guide.php`. Do not edit manually.',
    '> Run `composer docs:update` after structural changes and `composer docs:check` before committing.',
    '',
    'This is the factual companion to the [developer guide](../developer-guide.md). It contains no timestamp so stale checks are deterministic.',
    '',
    '## Runtime and development packages',
    '',
    '| Package | Scope | Constraint | Locked version | Role |',
    '| --- | --- | --- | --- | --- |',
];

foreach (['require' => 'runtime', 'require-dev' => 'development'] as $section => $scope) {
    $requirements = $composer[$section] ?? [];
    ksort($requirements);
    foreach ($requirements as $package => $constraint) {
        $version = $package === 'php' ? 'runtime 8.4.x' : ($locked[$package] ?? 'not locked');
        $purpose = $package === 'php' ? 'Required language runtime.' : ($purposes[$package] ?? 'Supporting package; see its integration configuration.');
        $lines[] = sprintf('| `%s` | %s | `%s` | `%s` | %s |', cell((string) $package), $scope, cell((string) $constraint), cell($version), cell($purpose));
    }
}

$lines = array_merge($lines, [
    '',
    '## Enabled modules',
    '',
    '| Module | Enabled | Priority | Description | Providers |',
    '| --- | --- | ---: | --- | --- |',
]);

$moduleFiles = glob($root.'/Modules/*/module.json') ?: [];
sort($moduleFiles);
foreach ($moduleFiles as $file) {
    $module = jsonFile($file);
    $name = (string) ($module['name'] ?? basename(dirname($file)));
    $providers = is_array($module['providers'] ?? null) ? implode('<br>', $module['providers']) : '';
    $lines[] = sprintf('| %s | %s | %d | %s | `%s` |', cell($name), ($statuses[$name] ?? false) ? 'yes' : 'no', (int) ($module['priority'] ?? 0), cell((string) ($module['description'] ?? '')), cell($providers));
}

$lines = array_merge($lines, [
    '',
    '## HTTP routes',
    '',
    '| Method | URI | Name | Middleware |',
    '| --- | --- | --- | --- |',
]);

foreach (routes($root) as $route) {
    $middleware = array_map(
        static fn (mixed $value): string => middlewareName((string) $value),
        is_array($route['middleware'] ?? null) ? $route['middleware'] : [],
    );
    $lines[] = sprintf('| `%s` | `/%s` | `%s` | %s |', cell((string) $route['method']), cell((string) $route['uri']), cell((string) ($route['name'] ?? '')), cell(implode(', ', $middleware)));
}

$lines = array_merge($lines, [
    '',
    '## GraphQL operations',
    '',
    '| Type | Field | Protection | Schema owner |',
    '| --- | --- | --- | --- |',
]);

foreach ($graphqlOperations as $operation) {
    $lines[] = sprintf('| %s | `%s` | %s | `%s` |', cell($operation['type']), cell($operation['field']), cell($operation['protection']), cell($operation['schema']));
}

$lines = array_merge($lines, [
    '',
    '## Migrations',
    '',
    '| Owner | Migration |',
    '| --- | --- |',
]);

$migrations = array_merge(
    glob($root.'/database/migrations/*.php') ?: [],
    glob($root.'/Modules/*/database/migrations/*.php') ?: [],
);
sort($migrations);
foreach ($migrations as $migration) {
    $relative = relativeTo($root, $migration);
    $owner = str_starts_with($relative, 'Modules/') ? explode('/', $relative)[1] : 'Application foundation';
    $lines[] = sprintf('| %s | `%s` |', cell($owner), cell($relative));
}

$lines = array_merge($lines, [
    '',
    '## Composer commands',
    '',
    '| Command | Execution |',
    '| --- | --- |',
]);

$scripts = $composer['scripts'] ?? [];
ksort($scripts);
foreach ($scripts as $name => $commands) {
    $commands = is_array($commands) ? $commands : [$commands];
    $lines[] = sprintf('| `composer %s` | %s |', cell((string) $name), cell(implode(' -> ', array_map('strval', $commands))));
}

$lines = array_merge($lines, [
    '',
    '## Environment-variable contract',
    '',
    'Safe placeholders live in `.env.example`; secrets belong only in an untracked `.env` or a deployment secret store.',
    '',
    '| Area | Variables |',
    '| --- | --- |',
]);

foreach (environmentGroups($root) as $group => $keys) {
    $formatted = implode(', ', array_map(static fn (string $key): string => "`{$key}`", $keys));
    $lines[] = sprintf('| %s | %s |', cell($group), $formatted);
}

$rendered = implode("\n", $lines)."\n";
$graphqlLines = [
    '# Generated GraphQL operation reference',
    '',
    '> Generated by `php scripts/update-developer-guide.php`. Do not edit manually.',
    '> The imported Lighthouse SDL files remain the executable source of truth.',
    '',
    'This reference is the operation index for the [API manual](../api-manual.md). Run `composer docs:update` after schema changes and `composer docs:check` before committing.',
    '',
    '| Type | Field | Signature | Protection | Schema owner |',
    '| --- | --- | --- | --- | --- |',
];
foreach ($graphqlOperations as $operation) {
    $graphqlLines[] = sprintf(
        '| %s | `%s` | `%s` | %s | `%s` |',
        cell($operation['type']),
        cell($operation['field']),
        cell($operation['signature']),
        cell($operation['protection']),
        cell($operation['schema']),
    );
}
$graphqlRendered = implode("\n", $graphqlLines)."\n";

if ($checkOnly) {
    if (! is_file($output)
        || file_get_contents($output) !== $rendered
        || ! is_file($graphqlOutput)
        || file_get_contents($graphqlOutput) !== $graphqlRendered) {
        fwrite(STDERR, "Generated developer documentation is stale. Run `composer docs:update`.\n");
        exit(1);
    }

    fwrite(STDOUT, "Generated developer documentation is current.\n");
    exit(0);
}

if (! is_dir(dirname($output)) && ! mkdir(dirname($output), 0777, true) && ! is_dir(dirname($output))) {
    throw new RuntimeException('Unable to create docs/generated.');
}
if (file_put_contents($output, $rendered) === false) {
    throw new RuntimeException("Unable to write {$output}.");
}
if (file_put_contents($graphqlOutput, $graphqlRendered) === false) {
    throw new RuntimeException("Unable to write {$graphqlOutput}.");
}

fwrite(STDOUT, "Updated generated system inventory and GraphQL operation reference.\n");
