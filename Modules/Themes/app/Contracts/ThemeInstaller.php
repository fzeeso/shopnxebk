<?php

declare(strict_types=1);

namespace Modules\Themes\Contracts;

use Modules\Authentication\Models\User;
use Modules\Stores\Models\Store;
use Modules\Themes\Models\StoreTheme;

interface ThemeInstaller
{
    public function installSelected(Store $store, User $actor, string $themeKey): StoreTheme;
}
