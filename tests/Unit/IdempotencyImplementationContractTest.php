<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class IdempotencyImplementationContractTest extends TestCase
{
    public function test_migration_is_additive_and_does_not_store_raw_keys_or_requests(): void
    {
        $migration = $this->source('database/migrations/2026_08_31_010000_create_idempotency_records_table.php');

        self::assertStringContainsString("Schema::create('idempotency_records'", $migration);
        self::assertStringContainsString("unique(['scope_hash', 'key_hash']", $migration);
        self::assertStringContainsString("text('response_body_ciphertext')", $migration);
        self::assertStringNotContainsString('Schema::table(', $migration);
        self::assertStringNotContainsString("string('idempotency_key'", $migration);
        self::assertStringNotContainsString("text('request_body'", $migration);
    }

    public function test_core_uses_postgresql_atomic_locking_encryption_and_integrity_checks(): void
    {
        $executor = $this->source('app/Support/Idempotency/IdempotencyExecutor.php');

        self::assertStringContainsString('pg_try_advisory_xact_lock', $executor);
        self::assertStringContainsString('DB::transaction(', $executor);
        self::assertStringContainsString('Crypt::encryptString($body)', $executor);
        self::assertStringContainsString('Crypt::decryptString(', $executor);
        self::assertStringContainsString('hash_equals((string) $record->request_fingerprint', $executor);
        self::assertStringContainsString("'Idempotency-Replayed'", $executor);
        self::assertStringNotContainsString('Cache::lock(', $executor);
    }

    public function test_global_switch_is_off_and_tier_a_starts_supported(): void
    {
        $config = $this->source('config/idempotency.php');

        self::assertStringContainsString("env('IDEMPOTENCY_ENABLED', false)", $config);
        self::assertStringContainsString("env('IDEMPOTENCY_TIER_A_MODE', 'supported')", $config);
        self::assertStringContainsString("env('IDEMPOTENCY_HMAC_KEY')", $config);
        self::assertStringNotContainsString("env('IDEMPOTENCY_HMAC_KEY', env('APP_KEY'))", $config);
        self::assertStringContainsString("'api.v1.platform.merchants.store'", $config);
        self::assertStringContainsString("'api.v1.store.users.store'", $config);
    }

    public function test_protected_controllers_authorize_before_the_executor_can_replay(): void
    {
        foreach ([
            'Modules/Stores/app/Http/Controllers/Api/V1/StoreController.php',
            'Modules/Stores/app/Http/Controllers/Api/V1/PlatformStoreController.php',
            'Modules/Stores/app/Http/Controllers/Api/V1/PlatformMerchantController.php',
            'Modules/Stores/app/Http/Controllers/Api/V1/StoreUserController.php',
        ] as $path) {
            $controller = $this->source($path);

            self::assertStringContainsString('IdempotencyExecutor', $controller, $path);
            self::assertStringContainsString('preflight:', $controller, $path);
            self::assertStringContainsString('authorizeCreation(', $controller, $path);
        }
    }

    public function test_browser_contract_allows_and_exposes_idempotency_headers(): void
    {
        $cors = $this->source('config/cors.php');

        self::assertStringContainsString("'Idempotency-Key'", $cors);
        self::assertStringContainsString("'Idempotency-Replayed'", $cors);
        self::assertStringContainsString("'Idempotency-Original-Request-ID'", $cors);
    }

    private function source(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2).'/'.$path);
    }
}
