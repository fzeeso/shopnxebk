<?php

declare(strict_types=1);

namespace Modules\Authentication\Support;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Models\User;
use Throwable;

final class MfaChallengeStore
{
    public const SESSION = 'session';

    public const TOKEN = 'token';

    /**
     * @param  array<string, mixed>  $context
     * @return array{challenge_token: string, expires_at: string}
     */
    public function create(User $user, string $purpose, array $context = []): array
    {
        if (! in_array($purpose, [self::SESSION, self::TOKEN], true)) {
            throw new \InvalidArgumentException('Unsupported MFA challenge purpose.');
        }

        $token = Str::random(80);
        $expiresAt = CarbonImmutable::now()->addSeconds(
            max(60, (int) config('authentication.mfa.challenge_ttl_seconds', 300)),
        );

        Cache::put($this->key($token), [
            'user_id' => (string) $user->getKey(),
            'purpose' => $purpose,
            'context' => $context,
            'password_version' => hash('sha256', $user->password),
            'mfa_version' => hash('sha256', (string) $user->two_factor_secret),
            'attempts_remaining' => max(1, (int) config('authentication.mfa.challenge_attempts', 5)),
            'expires_at' => $expiresAt->toISOString(),
        ], $expiresAt);

        return [
            'challenge_token' => $token,
            'expires_at' => $expiresAt->toISOString(),
        ];
    }

    /**
     * @template TResult
     *
     * @param  Closure(array<string, mixed>): TResult  $callback
     * @return TResult
     */
    public function consume(string $token, Closure $callback): mixed
    {
        $key = $this->key($token);

        try {
            return Cache::lock($key.':lock', 10)->block(3, function () use ($key, $callback) {
                $payload = Cache::get($key);

                if (! $this->isValidPayload($payload)) {
                    Cache::forget($key);
                    $this->invalidChallenge();
                }

                $expiresAt = CarbonImmutable::parse($payload['expires_at']);
                if ($expiresAt->isPast()) {
                    Cache::forget($key);
                    $this->invalidChallenge();
                }

                try {
                    $result = $callback($payload);
                } catch (ValidationException $exception) {
                    $payload['attempts_remaining']--;

                    if ($payload['attempts_remaining'] <= 0) {
                        Cache::forget($key);
                    } else {
                        Cache::put($key, $payload, $expiresAt);
                    }

                    throw $exception;
                }

                Cache::forget($key);

                return $result;
            });
        } catch (LockTimeoutException) {
            $this->invalidChallenge();
        } catch (Throwable $exception) {
            throw $exception;
        }
    }

    /** @param array<string, mixed> $payload */
    public function assertMatchesUser(array $payload, User $user): void
    {
        if (! hash_equals((string) $payload['user_id'], (string) $user->getKey())
            || ! hash_equals((string) $payload['password_version'], hash('sha256', $user->password))
            || ! hash_equals((string) $payload['mfa_version'], hash('sha256', (string) $user->two_factor_secret))) {
            $this->invalidChallenge();
        }
    }

    private function key(string $token): string
    {
        return 'auth:mfa:challenge:'.hash('sha256', $token);
    }

    private function isValidPayload(mixed $payload): bool
    {
        return is_array($payload)
            && is_string($payload['user_id'] ?? null)
            && in_array($payload['purpose'] ?? null, [self::SESSION, self::TOKEN], true)
            && is_array($payload['context'] ?? null)
            && is_string($payload['password_version'] ?? null)
            && is_string($payload['mfa_version'] ?? null)
            && is_int($payload['attempts_remaining'] ?? null)
            && is_string($payload['expires_at'] ?? null);
    }

    private function invalidChallenge(): never
    {
        throw ValidationException::withMessages([
            'challenge_token' => ['The MFA challenge is invalid or has expired.'],
        ]);
    }
}
