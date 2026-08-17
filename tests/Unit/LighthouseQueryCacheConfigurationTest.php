<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

final class LighthouseQueryCacheConfigurationTest extends TestCase
{
    public function test_query_cache_uses_process_safe_opcache_mode(): void
    {
        self::assertTrue(config('lighthouse.query_cache.enable'));
        self::assertSame('opcache', config('lighthouse.query_cache.mode'));
        self::assertSame(base_path('bootstrap/cache'), config('lighthouse.query_cache.opcache_path'));
    }
}
