<?php

declare(strict_types=1);

namespace Modules\Authentication\Data;

use Modules\Authentication\Models\PersonalAccessToken;

final readonly class IssuedToken
{
    public function __construct(public string $plainTextToken, public PersonalAccessToken $accessToken) {}
}
