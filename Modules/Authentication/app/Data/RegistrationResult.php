<?php

declare(strict_types=1);

namespace Modules\Authentication\Data;

use Modules\Authentication\Models\User;
use Modules\Stores\Models\Store;

final readonly class RegistrationResult
{
    public function __construct(public User $user, public Store $store) {}
}
