<?php

declare(strict_types=1);

namespace Modules\Catalog\GraphQL;

use Illuminate\Auth\AuthenticationException;
use Modules\Authentication\Models\User;

final class CatalogGraphqlContext
{
    public function user(): User
    {
        $user = auth('sanctum')->user();
        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }
}
