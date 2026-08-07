<?php

declare(strict_types=1);

namespace Modules\Settings\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Authentication\Models\User;
use Modules\Authentication\Services\ScopedRoleAssignmentService;
use Modules\Settings\Actions\EnsureLanguageCatalog;
use Modules\Settings\Models\Language;
use Tests\TestCase;

final class LanguageIconFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_languages_reference_existing_flag_assets(): void
    {
        app(EnsureLanguageCatalog::class)->ensure();

        $this->assertDatabaseCount('languages', 24);

        Language::query()->each(function (Language $language): void {
            $langIcon = (string) $language->getAttribute('lang_icon');

            self::assertStringStartsWith('/assets/languages/flags/', $langIcon);
            self::assertFileExists(public_path(ltrim($langIcon, '/')));
        });
    }

    public function test_language_icon_uses_generic_asset_when_a_partial_query_omits_the_attribute(): void
    {
        $language = new Language;
        $language->setRawAttributes(['locale' => 'en']);

        self::assertSame(
            url('/assets/languages/flags/generic.svg'),
            $language->langIconUrl(),
        );
    }

    public function test_super_admin_can_create_and_update_language_icons(): void
    {
        $admin = User::factory()->platform()->create();
        app(ScopedRoleAssignmentService::class)->assignPlatformRole($admin, 'Super Admin');

        $languageId = (string) $this->actingAs($admin, 'web')
            ->postJson('/api/v1/platform/settings/languages', [
                'name' => 'Test Language',
                'native_name' => 'Test Language',
                'locale' => 'tl',
                'direction' => 'ltr',
            ])
            ->assertCreated()
            ->assertJsonPath('data.lang_icon', url('/assets/languages/flags/generic.svg'))
            ->json('data.id');

        $this->actingAs($admin, 'web')
            ->patchJson("/api/v1/platform/settings/languages/{$languageId}", [
                'lang_icon' => 'https://cdn.example.test/flags/tl.svg',
            ])
            ->assertOk()
            ->assertJsonPath('data.lang_icon', 'https://cdn.example.test/flags/tl.svg');

        $this->actingAs($admin, 'web')
            ->patchJson("/api/v1/platform/settings/languages/{$languageId}", [
                'lang_icon' => 'javascript:alert(1)',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lang_icon');
    }
}
