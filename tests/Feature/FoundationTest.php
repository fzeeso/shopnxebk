<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Nwidart\Modules\Facades\Module;
use Tests\TestCase;

final class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_modules_and_api_only_routes_boot(): void
    {
        self::assertSame('shopnxebk', config('app.name'));
        self::assertTrue(Module::isEnabled('Authentication'));
        self::assertTrue(Module::isEnabled('Stores'));
        self::assertFalse(Route::has('welcome'));
        self::assertDirectoryDoesNotExist(resource_path('views'));

        $this->getJson('/')->assertNotFound()->assertJson(['message' => 'Not found.']);
    }

    public function test_health_endpoints_report_process_and_dependencies(): void
    {
        $this->getJson('/api/health/live')->assertOk()->assertExactJson(['status' => 'ok']);
        $this->getJson('/api/health/ready')->assertOk()->assertJsonPath('checks.database', 'ok')->assertJsonPath('checks.cache', 'ok');
    }

    public function test_graphql_api_version_is_public_and_viewer_is_protected(): void
    {
        $this->postJson('/graphql', ['query' => '{ apiVersion }'])->assertOk()->assertJsonPath('data.apiVersion', '1.0');
        $this->postJson('/graphql', ['query' => '{ viewer { id } }'])->assertOk()->assertJsonPath('data.viewer', null)->assertJsonStructure(['errors']);
    }

    public function test_graphql_depth_limit_and_production_error_redaction_are_enabled(): void
    {
        $deep = '{ viewer { tenants: id } }';
        config(['lighthouse.security.max_query_depth' => 0, 'lighthouse.debug' => 0]);
        $response = $this->postJson('/graphql', ['query' => $deep]);
        $response->assertOk()->assertJsonStructure(['errors']);
        self::assertArrayNotHasKey('trace', $response->json('errors.0.extensions', []));
    }
}
