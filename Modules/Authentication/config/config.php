<?php

declare(strict_types=1);

return [
    'name' => 'Authentication',
    'mfa' => [
        'challenge_ttl_seconds' => (int) env('AUTH_MFA_CHALLENGE_TTL_SECONDS', 300),
        'challenge_attempts' => (int) env('AUTH_MFA_CHALLENGE_ATTEMPTS', 5),
        'totp_window' => (int) env('AUTH_MFA_TOTP_WINDOW', 1),
    ],
];
