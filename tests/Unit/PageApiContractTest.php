<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PageApiContractTest extends TestCase
{
    public function test_schema_is_additive_multilingual_and_store_safe(): void
    {
        $migration = $this->source('Modules/Stores/database/migrations/2026_08_31_000100_create_page_tables.php');

        self::assertStringContainsString("Schema::create('pages'", $migration);
        self::assertStringContainsString("Schema::create('page_translations'", $migration);
        self::assertStringContainsString('pages_parent_store_fk', $migration);
        self::assertStringContainsString('page_translations_page_store_fk', $migration);
        self::assertStringContainsString('page_translations_page_language_unique', $migration);
        self::assertStringContainsString('page_translations_store_language_slug_unique', $migration);
        self::assertStringContainsString('pages_one_homepage_per_store', $migration);
        self::assertStringNotContainsString('Schema::table(', $migration);
        self::assertStringNotContainsString('DB::table(', $migration);
    }

    public function test_admin_routes_cover_crud_lifecycle_and_translations(): void
    {
        $routes = $this->source('Modules/Stores/routes/api.php');

        foreach ([
            "Route::get('pages'",
            "Route::post('pages'",
            "Route::get('pages/{page}'",
            "Route::patch('pages/{page}'",
            "Route::delete('pages/{page}'",
            "Route::post('pages/{page}/publish'",
            "Route::post('pages/{page}/unpublish'",
            "Route::post('pages/{page}/enable'",
            "Route::post('pages/{page}/disable'",
            "Route::put('pages/{page}/translations/{language}'",
            "Route::delete('pages/{page}/translations/{language}'",
        ] as $endpoint) {
            self::assertStringContainsString($endpoint, $routes);
        }
        self::assertStringContainsString("'store.bindings'", $routes);
    }

    public function test_service_enforces_store_scope_language_scope_and_safe_lifecycle(): void
    {
        $service = $this->source('Modules/Stores/app/Services/PageManagementService.php');

        self::assertGreaterThanOrEqual(5, substr_count($service, "where('store_id', \$store->getKey())"));
        self::assertStringContainsString('ensureCanManagePolicies', $service);
        self::assertStringContainsString('ensureCanView', $service);
        self::assertStringContainsString("where('is_active', true)", $service);
        self::assertStringContainsString('A page cannot be its own parent', $service);
        self::assertStringContainsString('default-language translation is required', $service);
        self::assertStringContainsString('$this->disable($user, $page)', $service);
    }

    public function test_translation_handler_is_registered_and_honors_locking(): void
    {
        $provider = $this->source('app/Providers/AppServiceProvider.php');
        $handler = $this->source('Modules/Stores/app/Services/Translations/PageTranslationHandler.php');

        self::assertStringContainsString('PageTranslationHandler::class', $provider);
        self::assertStringContainsString("return 'page';", $handler);
        self::assertStringContainsString("'page_translations'", $handler);
        self::assertStringContainsString('AutomatedTranslationWriter', $handler);
        self::assertStringContainsString('lock_it', $handler);
    }

    private function source(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2).'/'.$path);
    }
}
