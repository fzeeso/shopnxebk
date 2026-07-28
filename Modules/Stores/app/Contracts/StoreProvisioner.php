<?php

declare(strict_types=1);

namespace Modules\Stores\Contracts;

use Modules\Authentication\Models\User;
use Modules\Stores\Models\Store;

interface StoreProvisioner
{
    public function provision(User $owner, string $name, string $slug): Store;
}
