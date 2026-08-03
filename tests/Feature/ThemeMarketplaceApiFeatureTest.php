<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Authentication\Models\User;
use Modules\Authentication\Services\ScopedRoleAssignmentService;
use Modules\Stores\Contracts\StoreProvisioner;
use Modules\Stores\Models\Store;
use Modules\Themes\Models\StoreTheme;
use Modules\Themes\Models\Theme;
use Tests\TestCase;

final class ThemeMarketplaceApiFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_provisioning_creates_the_complete_theme_architecture(): void
    {
        config(['stores.platform_domain' => 'stores.example.test']);
        $owner = User::factory()->create(['email' => 'theme-owner@example.test']);

        $store = app(StoreProvisioner::class)->provision(
            $owner,
            'Theme Provisioned Store',
            'theme-provisioned-store',
            ['theme_template_key' => 'default'],
        );

        $installation = StoreTheme::query()
            ->withoutGlobalScopes()
            ->where('store_id', $store->getKey())
            ->firstOrFail();

        self::assertSame('draft', $store->status->value);
        self::assertSame('published', $installation->statusValue());
        self::assertSame('active', $installation->license->status);
        self::assertSame('free', $installation->license->license_type);
        self::assertSame('published', $installation->themeVersion->statusValue());
        self::assertSame($installation->getKey(), $store->refresh()->activeTheme?->getKey());

        $this->assertDatabaseHas('theme_publishers', [
            'slug' => 'shopnxe',
            'publisher_type' => 'platform',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('themes', [
            'slug' => 'default',
            'source_type' => 'platform',
            'commercial_type' => 'free',
            'status' => 'published',
        ]);
        $this->assertDatabaseHas('theme_licenses', [
            'store_id' => $store->getKey(),
            'theme_id' => $installation->theme_id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('store_themes', [
            'store_id' => $store->getKey(),
            'theme_version_id' => $installation->theme_version_id,
            'theme_license_id' => $installation->theme_license_id,
            'status' => 'published',
        ]);
    }

    public function test_platform_admin_can_manage_catalog_release_review_and_license_workflow(): void
    {
        $admin = User::factory()->platform()->create();
        app(ScopedRoleAssignmentService::class)->assignPlatformRole($admin, 'Super Admin');

        $publisherId = (string) $this->actingAs($admin, 'web')
            ->postJson('/api/v1/platform/theme-publishers', [
                'publisher_type' => 'platform',
                'display_name' => 'ShopNXE Test Themes',
                'slug' => 'shopnxe-test-themes',
                'status' => 'active',
                'support_email' => 'themes@example.test',
                'default_commission_bps' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('data.publisher_type', 'platform')
            ->json('data.id');

        $categoryId = (string) $this->actingAs($admin, 'web')
            ->postJson('/api/v1/platform/theme-categories', [
                'name' => 'Fashion',
                'slug' => 'fashion',
                'category_type' => 'industry',
                'sort_order' => 10,
                'is_active' => true,
            ])
            ->assertCreated()
            ->json('data.id');

        $themeId = (string) $this->actingAs($admin, 'web')
            ->postJson('/api/v1/platform/themes', [
                'publisher_id' => $publisherId,
                'owner_store_id' => null,
                'name' => 'Editorial Commerce',
                'slug' => 'editorial-commerce',
                'summary' => 'A release-workflow test Theme.',
                'description' => 'Immutable release coverage.',
                'source_type' => 'platform',
                'visibility' => 'public',
                'commercial_type' => 'free',
                'status' => 'draft',
                'category_ids' => [$categoryId],
                'primary_category_id' => $categoryId,
            ])
            ->assertCreated()
            ->assertJsonPath('data.categories.0.id', $categoryId)
            ->assertJsonPath('data.categories.0.is_primary', true)
            ->json('data.id');

        $versionId = (string) $this->actingAs($admin, 'web')
            ->postJson("/api/v1/platform/themes/{$themeId}/versions", [
                'version' => '1.0.0',
                'source_archive_object_key' => 'themes/quarantine/editorial-commerce/1.0.0.zip',
                'package_sha256' => str_repeat('a', 64),
                'package_size_bytes' => 1024,
                'uncompressed_size_bytes' => 4096,
                'file_count' => 12,
                'manifest' => [
                    'engine' => 'shopnxe-theme-v1',
                    'required_templates' => ['index', 'product', 'collection', 'page', 'cart', 'search'],
                ],
                'settings_schema' => [
                    ['id' => 'brand_color', 'type' => 'color', 'default' => '#08714a'],
                ],
                'validation_report' => ['manifest_valid' => true, 'malware_scan' => 'passed'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'uploaded')
            ->json('data.id');

        $submissionId = (string) $this->actingAs($admin, 'web')
            ->postJson("/api/v1/platform/theme-versions/{$versionId}/submit")
            ->assertCreated()
            ->assertJsonPath('data.submission_number', 1)
            ->assertJsonPath('data.status', 'submitted')
            ->json('data.id');

        $this->actingAs($admin, 'web')
            ->patchJson("/api/v1/platform/theme-submissions/{$submissionId}/review", [
                'decision' => 'approved',
                'review_notes' => 'Validated for release.',
                'rejection_codes' => [],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->actingAs($admin, 'web')
            ->postJson("/api/v1/platform/theme-versions/{$versionId}/publish")
            ->assertOk()
            ->assertJsonPath('data.id', $themeId)
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.current_version.id', $versionId);

        $this->actingAs($admin, 'web')
            ->getJson('/api/v1/platform/themes?status=published&page=1&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $themeId)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonStructure(['links', 'meta']);

        $store = Store::factory()->create();
        $licenseId = (string) $this->actingAs($admin, 'web')
            ->postJson("/api/v1/platform/themes/{$themeId}/licenses", [
                'store_id' => (string) $store->public_id,
                'license_type' => 'complimentary',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->json('data.id');

        $this->actingAs($admin, 'web')
            ->patchJson("/api/v1/platform/theme-licenses/{$licenseId}", ['status' => 'revoked'])
            ->assertOk()
            ->assertJsonPath('data.status', 'revoked');

        $this->assertDatabaseHas('theme_versions', [
            'theme_id' => Theme::query()->where('public_id', $themeId)->value('id'),
            'version' => '1.0.0',
            'status' => 'published',
        ]);
    }

    public function test_store_admin_can_install_customize_duplicate_publish_and_delete_a_draft(): void
    {
        config(['stores.platform_domain' => 'stores.example.test']);
        $owner = User::factory()->create();
        $store = app(StoreProvisioner::class)->provision(
            $owner,
            'Theme Workflow Store',
            'theme-workflow-store',
        );
        $theme = Theme::query()->where('slug', 'default')->firstOrFail();
        $headers = ['X-Store-ID' => (string) $store->public_id];

        $this->actingAs($owner, 'web')
            ->withHeaders($headers)
            ->getJson('/api/v1/store/theme-marketplace?per_page=1')
            ->assertOk()
            ->assertJsonPath('data.0.id', (string) $theme->public_id)
            ->assertJsonPath('meta.per_page', 1);

        $draftId = (string) $this->actingAs($owner, 'web')
            ->withHeaders($headers)
            ->postJson('/api/v1/store/themes', [
                'theme_id' => (string) $theme->public_id,
                'name' => 'Summer storefront',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.customization_revision', 1)
            ->json('data.id');

        $this->actingAs($owner, 'web')
            ->withHeaders($headers)
            ->patchJson("/api/v1/store/themes/{$draftId}", [
                'settings_data' => ['brand_color' => '#112233'],
                'custom_css' => '.hero { min-height: 30rem; }',
                'customization_revision' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.customization_revision', 2)
            ->assertJsonPath('data.settings_data.brand_color', '#112233');

        $this->actingAs($owner, 'web')
            ->withHeaders($headers)
            ->patchJson("/api/v1/store/themes/{$draftId}", [
                'name' => 'Stale edit',
                'customization_revision' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('customization_revision');

        $duplicateId = (string) $this->actingAs($owner, 'web')
            ->withHeaders($headers)
            ->postJson("/api/v1/store/themes/{$draftId}/duplicate")
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.parent_store_theme_id', $draftId)
            ->json('data.id');

        $this->actingAs($owner, 'web')
            ->withHeaders($headers)
            ->postJson("/api/v1/store/themes/{$draftId}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->actingAs($owner, 'web')
            ->withHeaders($headers)
            ->deleteJson("/api/v1/store/themes/{$duplicateId}")
            ->assertNoContent();

        self::assertSame(1, StoreTheme::query()->where('store_id', $store->getKey())->where('status', 'published')->count());
        self::assertSame(1, StoreTheme::query()->where('store_id', $store->getKey())->where('status', 'archived')->count());
        $this->assertSoftDeleted('store_themes', ['public_id' => $duplicateId]);
    }
}
