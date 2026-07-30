<?php

declare(strict_types=1);

namespace Modules\Settings\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Authentication\Models\User;
use Modules\Settings\Models\Language;

final readonly class LanguageCatalogService
{
    public function __construct(private PlatformSettingsAccessService $access) {}

    /** @return Collection<int, Language> */
    public function listPlatform(User $user): Collection
    {
        $this->access->ensureCanView($user);

        return Language::query()->orderBy('name')->get();
    }

    /** @param array<string, mixed> $data */
    public function createPlatform(User $user, array $data): Language
    {
        $this->access->ensureCanManage($user);

        return Language::query()->create([
            ...$data,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function updatePlatform(User $user, Language $language, array $data): Language
    {
        $this->access->ensureCanManage($user);

        $language->fill($data)->save();

        return $language->refresh();
    }
}
