<?php

declare(strict_types=1);

namespace Modules\Settings\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Authentication\Models\User;
use Modules\Authentication\Services\ScopedRoleAssignmentService;
use Modules\Settings\Actions\EnsureLanguageCatalog;
use Tests\TestCase;

final class PlatformCatalogApiFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_language_catalog_includes_hindi_urdu_and_persian_with_correct_directions(): void
    {
        $catalog = app(EnsureLanguageCatalog::class);

        $catalog->ensure();
        $catalog->ensure();

        $this->assertDatabaseCount('languages', 24);
        $this->assertDatabaseHas('languages', [
            'name' => 'Hindi',
            'native_name' => 'हिन्दी',
            'locale' => 'hi',
            'direction' => 'ltr',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('languages', [
            'name' => 'Urdu',
            'native_name' => 'اردو',
            'locale' => 'ur',
            'direction' => 'rtl',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('languages', [
            'name' => 'Persian',
            'native_name' => 'فارسی',
            'locale' => 'fa',
            'direction' => 'rtl',
            'is_active' => true,
        ]);
    }

    public function test_super_admin_can_manage_currencies_and_languages_through_settings(): void
    {
        $admin = User::factory()->platform()->create();
        app(ScopedRoleAssignmentService::class)->assignPlatformRole($admin, 'Super Admin');

        $this->actingAs($admin, 'web')
            ->getJson('/api/v1/auth/interfaces')
            ->assertOk()
            ->assertJsonPath('data.platform_admin.navigation.1.key', 'platform_settings')
            ->assertJsonPath('data.platform_admin.navigation.1.path', '/admin/settings')
            ->assertJsonPath('data.platform_admin.navigation.1.permission', 'manage platform settings');

        $currencyId = (string) $this->actingAs($admin, 'web')
            ->postJson('/api/v1/platform/settings/currencies', [
                'name' => 'Test Dollar',
                'code' => 'TST',
                'symbol' => 'T$',
                'symbol_position' => 'before',
                'decimal_places' => 2,
                'usd_exchange_rate' => 2.5,
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'TST')
            ->json('data.id');

        $this->actingAs($admin, 'web')
            ->patchJson("/api/v1/platform/settings/currencies/{$currencyId}", [
                'is_active' => false,
                'usd_exchange_rate' => 2.75,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.usd_exchange_rate', '2.75000000');

        $languageId = (string) $this->actingAs($admin, 'web')
            ->postJson('/api/v1/platform/settings/languages', [
                'name' => 'Test Language',
                'native_name' => 'Test Language',
                'locale' => 'tl',
                'direction' => 'ltr',
            ])
            ->assertCreated()
            ->assertJsonPath('data.locale', 'tl')
            ->json('data.id');

        $this->actingAs($admin, 'web')
            ->patchJson("/api/v1/platform/settings/languages/{$languageId}", [
                'name' => 'Updated Test Language',
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        foreach (['currencies', 'languages'] as $catalog) {
            $this->actingAs($admin, 'web')
                ->getJson("/api/v1/platform/settings/{$catalog}?page=1&per_page=1")
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('meta.current_page', 1)
                ->assertJsonPath('meta.per_page', 1)
                ->assertJsonStructure([
                    'links' => ['first', 'last', 'prev', 'next'],
                    'meta' => ['current_page', 'from', 'last_page', 'path', 'per_page', 'to', 'total'],
                ]);
        }

        $this->actingAs($admin, 'web')
            ->patchJson("/api/v1/platform/settings/languages/{$languageId}", [
                'locale' => 'zz',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('locale');
    }

    public function test_platform_read_access_and_legacy_aliases_remain_available(): void
    {
        $support = User::factory()->platform()->create();
        app(ScopedRoleAssignmentService::class)->assignPlatformRole($support, 'Support');

        $this->actingAs($support, 'web')
            ->getJson('/api/v1/platform/settings/currencies')
            ->assertOk();

        $this->actingAs($support, 'web')
            ->getJson('/api/v1/platform/languages')
            ->assertOk();

        $this->actingAs($support, 'web')
            ->getJson('/api/v1/auth/interfaces')
            ->assertOk()
            ->assertJsonCount(1, 'data.platform_admin.navigation')
            ->assertJsonPath('data.platform_admin.navigation.0.key', 'merchants')
            ->assertJsonPath('data.platform_admin.navigation.0.permission', 'manage stores');

        $this->actingAs($support, 'web')
            ->postJson('/api/v1/platform/settings/languages', [
                'name' => 'Forbidden',
                'native_name' => 'Forbidden',
                'locale' => 'fb',
                'direction' => 'ltr',
            ])
            ->assertForbidden();

        $this->actingAs(User::factory()->create(), 'web')
            ->getJson('/api/v1/platform/settings/languages')
            ->assertForbidden()
            ->assertJsonPath('message', 'Platform-scoped account required.');
    }
}
