<?php

declare(strict_types=1);

namespace Modules\Authentication\GraphQL\Queries;

use Illuminate\Auth\AuthenticationException;
use Modules\Authentication\Models\User;

final class Viewer
{
    public function __invoke(): User
    {
        $user = auth('sanctum')->user();
        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }
}
