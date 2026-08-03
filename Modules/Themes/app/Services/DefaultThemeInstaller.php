<?php

declare(strict_types=1);

namespace Modules\Themes\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Models\User;
use Modules\Stores\Models\Store;
use Modules\Themes\Actions\EnsureThemeCatalog;
use Modules\Themes\Contracts\ThemeInstaller;
use Modules\Themes\Enums\StoreThemeStatus;
use Modules\Themes\Enums\ThemeSourceType;
use Modules\Themes\Models\StoreTheme;
use Modules\Themes\Models\Theme;
use Modules\Themes\Models\ThemeLicense;

final readonly class DefaultThemeInstaller implements ThemeInstaller
{
    public function __construct(private EnsureThemeCatalog $catalog) {}

    public function installSelected(Store $store, User $actor, string $themeKey): StoreTheme
    {
        return DB::transaction(function () use ($actor, $store, $themeKey): StoreTheme {
            $theme = Theme::query()->where('slug', $themeKey)->first()
                ?? $this->catalog->ensureBundledTheme($actor, $themeKey);
            $theme->loadMissing('currentVersion');
            $version = $theme->currentVersion;

            if ($version === null || $version->statusValue() !== 'published') {
                throw ValidationException::withMessages([
                    'theme_template_key' => ['The selected theme has no published version.'],
                ]);
            }

            $license = ThemeLicense::query()
                ->where('store_id', $store->getKey())
                ->where('theme_id', $theme->getKey())
                ->whereIn('status', ['trial', 'active'])
                ->first();

            if ($license === null) {
                $licenseType = match (true) {
                    $theme->commercial_type === 'free' => 'free',
                    $theme->commercial_type === 'private'
                        && $theme->owner_store_id === $store->getKey() => 'custom_owner',
                    default => null,
                };
                if ($licenseType === null) {
                    throw ValidationException::withMessages([
                        'theme_template_key' => ['A current license is required for this theme.'],
                    ]);
                }

                $license = ThemeLicense::query()->create([
                    'theme_id' => $theme->getKey(),
                    'store_id' => $store->getKey(),
                    'license_type' => $licenseType,
                    'status' => 'active',
                    'purchased_by_user_id' => $actor->getKey(),
                    'issued_at' => now(),
                ]);
            }

            $defaults = collect($version->settings_schema)
                ->filter(fn (mixed $setting): bool => is_array($setting) && isset($setting['id']) && array_key_exists('default', $setting))
                ->mapWithKeys(fn (array $setting): array => [(string) $setting['id'] => $setting['default']])
                ->all();

            return StoreTheme::query()->create([
                'store_id' => $store->getKey(),
                'theme_id' => $theme->getKey(),
                'theme_version_id' => $version->getKey(),
                'theme_license_id' => $license->getKey(),
                'installed_by_user_id' => $actor->getKey(),
                'name' => $theme->name,
                'status' => StoreThemeStatus::Published,
                'installed_from' => $theme->source_type === ThemeSourceType::Platform ? 'platform' : 'marketplace',
                'settings_data' => $defaults,
                'template_data' => $version->manifest['default_template_data'] ?? [],
                'customization_revision' => 1,
                'installed_at' => now(),
                'published_at' => now(),
            ]);
        });
    }
}
