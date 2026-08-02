<?php

declare(strict_types=1);

namespace Modules\Themes\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Models\User;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Models\Store;
use Modules\Themes\Enums\StoreThemeStatus;
use Modules\Themes\Enums\ThemeSourceType;
use Modules\Themes\Models\StoreTheme;
use Modules\Themes\Models\Theme;
use Modules\Themes\Models\ThemeLicense;

final readonly class StoreThemeService
{
    public function __construct(
        private StoreContext $context,
        private ThemeAccessService $access,
    ) {}

    /** @return LengthAwarePaginator<int, StoreTheme> */
    public function installed(User $user, int $perPage): LengthAwarePaginator
    {
        $store = $this->store($user);

        return StoreTheme::query()
            ->where('store_id', $store->getKey())
            ->with($this->installationRelations())
            ->orderByRaw("CASE status WHEN 'published' THEN 0 WHEN 'draft' THEN 1 ELSE 2 END")
            ->orderByDesc('updated_at')
            ->paginate($perPage);
    }

    /** @return LengthAwarePaginator<int, Theme> */
    public function marketplace(User $user, int $perPage, ?string $search = null): LengthAwarePaginator
    {
        $store = $this->store($user);
        $search = trim((string) $search);

        return Theme::query()
            ->where('status', 'published')
            ->whereNotNull('current_version_id')
            ->where(fn ($query) => $query->where('visibility', 'public')->orWhere('owner_store_id', $store->getKey()))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereRaw('name ILIKE ?', ["%{$search}%"])
                ->orWhereRaw('summary ILIKE ?', ["%{$search}%"])))
            ->with(['publisher.owner', 'ownerStore', 'creator', 'currentVersion.uploader', 'currentVersion.approver', 'categories'])
            ->withCount(['licenses', 'installations'])
            ->orderByDesc('is_featured')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function install(User $user, Theme $theme, ?string $name, bool $asTrial): StoreTheme
    {
        $store = $this->store($user);

        return DB::transaction(function () use ($asTrial, $name, $store, $theme, $user): StoreTheme {
            $theme->loadMissing('currentVersion');
            $version = $theme->currentVersion;
            if ($theme->statusValue() !== 'published' || $version === null || $version->statusValue() !== 'published') {
                throw ValidationException::withMessages(['theme_id' => ['Only a published theme version can be installed.']]);
            }
            if ($theme->visibility === 'private' && $theme->owner_store_id !== $store->getKey()) {
                throw ValidationException::withMessages(['theme_id' => ['This private theme belongs to another Store.']]);
            }

            $license = ThemeLicense::query()
                ->where('store_id', $store->getKey())
                ->where('theme_id', $theme->getKey())
                ->whereIn('status', ['trial', 'active'])
                ->first();
            if ($license === null) {
                [$licenseType, $licenseStatus] = match (true) {
                    $theme->commercial_type === 'free' => ['free', 'active'],
                    $theme->commercial_type === 'private' && $theme->owner_store_id === $store->getKey() => ['custom_owner', 'active'],
                    $asTrial => ['trial', 'trial'],
                    default => [null, null],
                };
                if ($licenseType === null) {
                    throw ValidationException::withMessages(['theme_id' => ['Purchase or assign a license before installing this paid theme.']]);
                }
                $license = ThemeLicense::query()->create([
                    'theme_id' => $theme->getKey(),
                    'store_id' => $store->getKey(),
                    'license_type' => $licenseType,
                    'status' => $licenseStatus,
                    'purchased_by_user_id' => $user->getKey(),
                    'issued_at' => now(),
                    'trial_expires_at' => $licenseType === 'trial' ? now()->addDays(7) : null,
                ]);
            }

            $settings = collect($version->settings_schema)
                ->filter(fn (mixed $item): bool => is_array($item) && isset($item['id']) && array_key_exists('default', $item))
                ->mapWithKeys(fn (array $item): array => [(string) $item['id'] => $item['default']])
                ->all();
            $installation = StoreTheme::query()->create([
                'store_id' => $store->getKey(),
                'theme_id' => $theme->getKey(),
                'theme_version_id' => $version->getKey(),
                'theme_license_id' => $license->getKey(),
                'installed_by_user_id' => $user->getKey(),
                'name' => $name ?: $theme->name,
                'status' => StoreThemeStatus::Draft,
                'installed_from' => $theme->source_type === ThemeSourceType::Platform ? 'platform' : 'marketplace',
                'settings_data' => $settings,
                'template_data' => $version->manifest['default_template_data'] ?? [],
                'customization_revision' => 1,
                'installed_at' => now(),
            ]);

            return $this->loadInstallation($installation);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, StoreTheme $installation, array $data): StoreTheme
    {
        $store = $this->store($user);
        $this->ensureOwned($installation, $store);
        if ($installation->statusValue() !== 'draft') {
            throw ValidationException::withMessages(['theme' => ['Only a draft copy can be customized. Duplicate the live theme first.']]);
        }
        if ((int) $data['customization_revision'] !== $installation->customization_revision) {
            throw ValidationException::withMessages(['customization_revision' => ['This theme changed after it was loaded. Refresh before saving again.']]);
        }

        $installation->fill([
            ...Arr::except($data, ['customization_revision']),
            'customization_revision' => $installation->customization_revision + 1,
        ])->save();

        return $this->loadInstallation($installation->refresh());
    }

    public function duplicate(User $user, StoreTheme $installation): StoreTheme
    {
        $store = $this->store($user);
        $this->ensureOwned($installation, $store);

        return DB::transaction(function () use ($installation, $store, $user): StoreTheme {
            $copy = StoreTheme::query()->create([
                'store_id' => $store->getKey(),
                'theme_id' => $installation->theme_id,
                'theme_version_id' => $installation->theme_version_id,
                'theme_license_id' => $installation->theme_license_id,
                'parent_store_theme_id' => $installation->getKey(),
                'installed_by_user_id' => $user->getKey(),
                'name' => $installation->name.' copy',
                'status' => StoreThemeStatus::Draft,
                'installed_from' => 'duplicate',
                'settings_data' => $installation->settings_data,
                'template_data' => $installation->template_data,
                'custom_css' => $installation->custom_css,
                'customization_revision' => 1,
                'installed_at' => now(),
            ]);

            return $this->loadInstallation($copy);
        });
    }

    public function publish(User $user, StoreTheme $installation): StoreTheme
    {
        $store = $this->store($user);
        $this->ensureOwned($installation, $store);
        $installation->loadMissing('license');
        if ($installation->license === null || $installation->license->status !== 'active' || $installation->license->license_type === 'trial') {
            throw ValidationException::withMessages(['license' => ['An active non-trial license is required before publishing.']]);
        }

        return DB::transaction(function () use ($installation, $store): StoreTheme {
            StoreTheme::query()
                ->where('store_id', $store->getKey())
                ->where('status', 'published')
                ->whereKeyNot($installation->getKey())
                ->update(['status' => 'archived']);
            $installation->forceFill(['status' => StoreThemeStatus::Published, 'published_at' => now()])->save();

            return $this->loadInstallation($installation->refresh());
        });
    }

    public function delete(User $user, StoreTheme $installation): void
    {
        $store = $this->store($user);
        $this->ensureOwned($installation, $store);
        if (! in_array($installation->statusValue(), ['draft', 'failed'], true)) {
            throw ValidationException::withMessages(['theme' => ['Only draft or failed theme copies can be deleted.']]);
        }
        $installation->delete();
    }

    private function store(User $user): Store
    {
        $store = $this->context->require();
        $this->access->ensureCanManageStoreThemes($user, $store);

        return $store;
    }

    private function ensureOwned(StoreTheme $installation, Store $store): void
    {
        if ($installation->store_id !== $store->getKey()) {
            abort(404);
        }
    }

    /** @return list<string> */
    private function installationRelations(): array
    {
        return ['store', 'theme.publisher.owner', 'theme.ownerStore', 'theme.creator', 'theme.currentVersion', 'theme.categories', 'themeVersion.uploader', 'themeVersion.approver', 'license.theme', 'license.store', 'license.purchaser', 'parent', 'installer'];
    }

    private function loadInstallation(StoreTheme $installation): StoreTheme
    {
        return $installation->load($this->installationRelations());
    }
}
