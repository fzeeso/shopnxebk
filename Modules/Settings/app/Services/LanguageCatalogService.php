<?php

declare(strict_types=1);

namespace Modules\Settings\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Authentication\Models\User;
use Modules\Settings\Models\Language;

final readonly class LanguageCatalogService
{
    private const DEFAULT_LANG_ICON = '/assets/languages/flags/generic.svg';

    private const DEFAULT_LANG_IMAGE = '/assets/languages/flags/generic.svg';

    public function __construct(private PlatformSettingsAccessService $access) {}

    /** @return LengthAwarePaginator<int, Language> */
    public function listPlatform(User $user, int $perPage = 25): LengthAwarePaginator
    {
        $this->access->ensureCanView($user);

        return Language::query()->orderBy('name')->paginate($perPage);
    }

    /** @param array<string, mixed> $data */
    public function createPlatform(User $user, array $data): Language
    {
        $this->access->ensureCanManage($user);

        return Language::query()->create([
            ...$data,
            'lang_icon' => $data['lang_icon'] ?? self::DEFAULT_LANG_ICON,
            'lang_image' => $data['lang_image'] ?? self::DEFAULT_LANG_IMAGE,
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
