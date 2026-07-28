<?php

declare(strict_types=1);

namespace Modules\Authentication\Support;

use Illuminate\Contracts\Cache\Repository;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;

final class TotpProvider implements TwoFactorAuthenticationProvider
{
    public function __construct(
        private readonly Google2FA $engine,
        private readonly Repository $cache,
    ) {}

    public function generateSecretKey(int $secretLength = 32): string
    {
        return $this->engine->generateSecretKey($secretLength);
    }

    public function qrCodeUrl($companyName, $companyEmail, $secret): string
    {
        return $this->engine->getQRCodeUrl((string) $companyName, (string) $companyEmail, (string) $secret);
    }

    public function verify($secret, $code): bool
    {
        $window = (int) config('authentication.mfa.totp_window', 1);
        $this->engine->setWindow(max(0, $window));

        $cacheKey = 'auth:mfa:totp:last-timestep:'.hash_hmac(
            'sha256',
            (string) $secret,
            (string) config('app.key'),
        );
        $lastTimestamp = $this->cache->get($cacheKey);
        $timestamp = $this->engine->verifyKeyNewer(
            (string) $secret,
            (string) $code,
            is_int($lastTimestamp) ? $lastTimestamp : null,
        );

        if ($timestamp === false) {
            return false;
        }

        $this->cache->put(
            $cacheKey,
            $timestamp === true ? $this->engine->getTimestamp() : $timestamp,
            now()->addSeconds(max(120, ($window * 2 + 2) * 30)),
        );

        return true;
    }
}
