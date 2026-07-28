<?php

declare(strict_types=1);

namespace Modules\Authentication\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;
use Laravel\Fortify\Fortify;
use Modules\Authentication\Models\User;

final class VerifyMfaCode
{
    public function __construct(
        private readonly TwoFactorAuthenticationProvider $provider,
    ) {}

    public function handle(User $user, ?string $code, ?string $recoveryCode): User
    {
        return DB::transaction(function () use ($user, $code, $recoveryCode): User {
            $lockedUser = User::query()->lockForUpdate()->find($user->getKey());

            if ($lockedUser === null || ! $lockedUser->hasEnabledTwoFactorAuthentication()) {
                throw ValidationException::withMessages([
                    'challenge_token' => ['The MFA challenge is invalid or has expired.'],
                ]);
            }

            if ($recoveryCode !== null) {
                $matchedCode = collect($lockedUser->recoveryCodes())
                    ->first(fn (string $storedCode): bool => hash_equals($storedCode, $recoveryCode));

                if (is_string($matchedCode)) {
                    $lockedUser->replaceRecoveryCode($matchedCode);
                    event(new ValidTwoFactorAuthenticationCodeProvided($lockedUser));

                    return $lockedUser;
                }

                event(new TwoFactorAuthenticationFailed($lockedUser));
                throw ValidationException::withMessages([
                    'recovery_code' => ['The provided recovery code was invalid.'],
                ]);
            }

            $secret = Fortify::currentEncrypter()->decrypt($lockedUser->two_factor_secret);
            if ($code === null || ! $this->provider->verify($secret, $code)) {
                event(new TwoFactorAuthenticationFailed($lockedUser));
                throw ValidationException::withMessages([
                    'code' => ['The provided authentication code was invalid or already used.'],
                ]);
            }

            event(new ValidTwoFactorAuthenticationCodeProvided($lockedUser));

            return $lockedUser;
        }, 3);
    }
}
