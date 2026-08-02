<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const MARKER = '2026_08_02_theme_architecture_backfill';

    public function up(): void
    {
        $actorId = DB::table('store_users')->where('status', 'active')->orderBy('id')->value('user_id')
            ?? DB::table('users')->orderBy('id')->value('id');
        if ($actorId === null) {
            return;
        }

        $publisherId = DB::table('theme_publishers')->where('slug', 'shopnxe')->value('id');
        if ($publisherId === null) {
            $publisherId = DB::table('theme_publishers')->insertGetId([
                'public_id' => (string) Str::ulid(),
                'publisher_type' => 'platform',
                'display_name' => 'ShopNXE',
                'slug' => 'shopnxe',
                'status' => 'active',
                'support_email' => 'support@shopnxe.com',
                'default_commission_bps' => 0,
                'verified_at' => now(),
                'terms_accepted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $themeId = DB::table('themes')->where('slug', 'default')->value('id');
        if ($themeId === null) {
            $themeId = DB::table('themes')->insertGetId([
                'public_id' => (string) Str::ulid(),
                'publisher_id' => $publisherId,
                'created_by_user_id' => $actorId,
                'name' => 'Default',
                'slug' => 'default',
                'summary' => 'The bundled ShopNXE default storefront theme.',
                'source_type' => 'platform',
                'visibility' => 'public',
                'commercial_type' => 'free',
                'status' => 'published',
                'listing_metadata' => json_encode(['migration' => self::MARKER, 'responsive' => true], JSON_THROW_ON_ERROR),
                'is_featured' => false,
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $versionId = DB::table('theme_versions')->where('theme_id', $themeId)->where('version', '1.0.0')->value('id');
        if ($versionId === null) {
            $versionId = DB::table('theme_versions')->insertGetId([
                'public_id' => (string) Str::ulid(),
                'theme_id' => $themeId,
                'version' => '1.0.0',
                'status' => 'published',
                'engine_version' => 'shopnxe-theme-v1',
                'minimum_platform_version' => '1.0.0',
                'source_archive_object_key' => 'themes/platform/default/1.0.0/source.zip',
                'compiled_artifact_object_key' => 'themes/platform/default/1.0.0/compiled.zip',
                'package_sha256' => hash('sha256', 'shopnxe:default:1.0.0'),
                'package_size_bytes' => 0,
                'uncompressed_size_bytes' => 0,
                'file_count' => 0,
                'manifest' => json_encode(['name' => 'Default', 'version' => '1.0.0', 'engine' => 'shopnxe-theme-v1', 'required_templates' => ['index', 'product', 'collection', 'page', 'cart', 'search'], 'default_template_data' => ['sections' => []]], JSON_THROW_ON_ERROR),
                'settings_schema' => '[]',
                'validation_report' => json_encode(['bundled_platform_theme' => true], JSON_THROW_ON_ERROR),
                'uploaded_by_user_id' => $actorId,
                'approved_by_user_id' => $actorId,
                'approved_at' => now(),
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('themes')->where('id', $themeId)->update(['current_version_id' => $versionId]);
        }

        foreach (DB::table('stores')->orderBy('id')->pluck('id') as $storeId) {
            if (DB::table('store_themes')->where('store_id', $storeId)->where('status', 'published')->exists()) {
                continue;
            }
            $storeActorId = DB::table('store_users')->where('store_id', $storeId)->where('status', 'active')->orderBy('id')->value('user_id') ?? $actorId;
            $licenseId = DB::table('theme_licenses')->where('store_id', $storeId)->where('theme_id', $themeId)->whereIn('status', ['trial', 'active'])->value('id');
            if ($licenseId === null) {
                $licenseId = DB::table('theme_licenses')->insertGetId([
                    'public_id' => (string) Str::ulid(),
                    'theme_id' => $themeId,
                    'store_id' => $storeId,
                    'license_type' => 'free',
                    'status' => 'active',
                    'purchased_by_user_id' => $storeActorId,
                    'issued_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            DB::table('store_themes')->insert([
                'public_id' => (string) Str::ulid(),
                'store_id' => $storeId,
                'theme_id' => $themeId,
                'theme_version_id' => $versionId,
                'theme_license_id' => $licenseId,
                'installed_by_user_id' => $storeActorId,
                'name' => 'Default',
                'status' => 'published',
                'installed_from' => 'platform',
                'settings_data' => '{}',
                'template_data' => json_encode(['sections' => []], JSON_THROW_ON_ERROR),
                'customization_object_key' => 'migration://'.self::MARKER,
                'customization_revision' => 1,
                'installed_at' => now(),
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $installationIds = DB::table('store_themes')->where('customization_object_key', 'migration://'.self::MARKER)->pluck('id');
        $licenseIds = DB::table('store_themes')->whereIn('id', $installationIds)->pluck('theme_license_id');
        DB::table('store_themes')->whereIn('id', $installationIds)->delete();
        DB::table('theme_licenses')
            ->whereIn('id', $licenseIds)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('store_themes')->whereColumn('store_themes.theme_license_id', 'theme_licenses.id'))
            ->delete();

        $themeId = DB::table('themes')->whereRaw("listing_metadata ->> 'migration' = ?", [self::MARKER])->value('id');
        if ($themeId !== null && ! DB::table('store_themes')->where('theme_id', $themeId)->exists() && ! DB::table('theme_licenses')->where('theme_id', $themeId)->exists()) {
            $publisherId = DB::table('themes')->where('id', $themeId)->value('publisher_id');
            DB::table('themes')->where('id', $themeId)->update(['current_version_id' => null]);
            DB::table('themes')->where('id', $themeId)->delete();
            if ($publisherId !== null && ! DB::table('themes')->where('publisher_id', $publisherId)->exists()) {
                DB::table('theme_publishers')->where('id', $publisherId)->where('slug', 'shopnxe')->delete();
            }
        }
    }
};
