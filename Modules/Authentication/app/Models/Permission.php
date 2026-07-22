<?php

declare(strict_types=1);

namespace Modules\Authentication\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Permission\Models\Permission as SpatiePermission;

final class Permission extends SpatiePermission
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';
}
