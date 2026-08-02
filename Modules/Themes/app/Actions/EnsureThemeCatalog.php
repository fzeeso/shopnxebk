<?php

declare(strict_types=1);

namespace Modules\Themes\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Authentication\Models\User;
use Modules\Themes\Enums\ThemeSourceType;
use Modules\Themes\Enums\ThemeStatus;
use Modules\Themes\Enums\ThemeVersionStatus;
use Modules\Themes\Models\Theme;
use Modules\Themes\Models\ThemePublisher;
use Modules\Themes\Models\ThemeVersion;

final class EnsureThemeCatalog
{
    public function ensureBundledTheme(User $actor, string $themeKey): Theme
    {
        return DB::transaction(function () use ($actor, $themeKey): Theme {
            $publisher = ThemePublisher::query()->firstOrCreate(
                ['slug' => 'shopnxe'],
                [
                    'publisher_type' => 'platform',
                    'display_name' => 'ShopNXE',
                    'status' => 'active',
                    'support_email' => 'support@shopnxe.com',
                    'default_commission_bps' => 0,
                    'verified_at' => now(),
                    'terms_accepted_at' => now(),
                ],
            );

            $theme = Theme::query()->firstOrCreate(
                ['slug' => $themeKey],
                [
                    'publisher_id' => $publisher->getKey(),
                    'created_by_user_id' => $actor->getKey(),
                    'name' => Str::headline($themeKey),
                    'summary' => 'A bundled ShopNXE storefront theme.',
                    'source_type' => ThemeSourceType::Platform,
                    'visibility' => 'public',
                    'commercial_type' => 'free',
                    'status' => ThemeStatus::Published,
                    'listing_metadata' => [
                        'responsive' => true,
                        'supported_locales' => ['en'],
                    ],
                    'published_at' => now(),
                ],
            );

            $version = ThemeVersion::query()->firstOrCreate(
                ['theme_id' => $theme->getKey(), 'version' => '1.0.0'],
                [
                    'status' => ThemeVersionStatus::Published,
                    'engine_version' => (string) config('themes.engine_version', 'shopnxe-theme-v1'),
                    'minimum_platform_version' => (string) config('themes.platform_version', '1.0.0'),
                    'source_archive_object_key' => "themes/platform/{$themeKey}/1.0.0/source.zip",
                    'compiled_artifact_object_key' => "themes/platform/{$themeKey}/1.0.0/compiled.zip",
                    'package_sha256' => hash('sha256', "shopnxe:{$themeKey}:1.0.0"),
                    'package_size_bytes' => 0,
                    'uncompressed_size_bytes' => 0,
                    'file_count' => 0,
                    'manifest' => [
                        'name' => Str::headline($themeKey),
                        'version' => '1.0.0',
                        'engine' => (string) config('themes.engine_version', 'shopnxe-theme-v1'),
                        'required_templates' => ['index', 'product', 'collection', 'page', 'cart', 'search'],
                        'default_template_data' => ['sections' => []],
                    ],
                    'settings_schema' => [],
                    'validation_report' => ['bundled_platform_theme' => true],
                    'uploaded_by_user_id' => $actor->getKey(),
                    'approved_by_user_id' => $actor->getKey(),
                    'approved_at' => now(),
                    'published_at' => now(),
                ],
            );

            if ($theme->current_version_id !== $version->getKey()) {
                $theme->forceFill([
                    'current_version_id' => $version->getKey(),
                    'status' => ThemeStatus::Published,
                    'published_at' => $theme->published_at ?? now(),
                ])->save();
            }

            return $theme->refresh()->load('currentVersion');
        });
    }
}
