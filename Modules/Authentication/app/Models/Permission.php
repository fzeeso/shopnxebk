<?php

declare(strict_types=1);

namespace Modules\Authentication\Models;

use App\Models\Concerns\HasPublicId;
use Modules\Authentication\Enums\AccessScope;
use Spatie\Permission\Models\Permission as SpatiePermission;

final class Permission extends SpatiePermission
{
    use HasPublicId;

    protected function casts(): array
    {
        return ['scope' => AccessScope::class];
    }
}
