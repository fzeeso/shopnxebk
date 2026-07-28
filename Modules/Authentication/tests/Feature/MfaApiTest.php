<?php

declare(strict_types=1);

namespace Modules\Authentication\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\RecoveryCode;
use Modules\Authentication\Models\PersonalAccessToken;
use Modules\Authentication\Models\User;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StoreMembership;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

final class MfaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mfa_can_be_set_up_confirmed_and_recovery_codes_are_encrypted(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $setup = $this->postJson('/api/v1/auth/mfa/setup', [
            'current_password' => 'password',
        ])->assertOk()
            ->assertJsonPath('enabled', false)
            ->assertJsonPath('pending_confirmation', true)
            ->assertHeader('Cache-Control');

        $secret = $setup->json('secret');
        self::assertIsString($secret);
        self::assertStringStartsWith('otpauth://totp/', (string) $setup->json('otpauth_uri'));
        self::assertStringContainsString('<svg', (string) $setup->json('qr_code_svg'));
        self::assertNotSame($secret, $user->fresh()->two_factor_secret);

        $code = app(Google2FA::class)->getCurrentOtp($secret);
        $this->actingAs($user->fresh(), 'web');
        $confirmed = $this->postJson('/api/v1/auth/mfa/confirm', [
            'current_password' => 'password',
            'code' => $code,
        ])->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonCount(8, 'recovery_codes');

        $recoveryCodes = $confirmed->json('recovery_codes');
        $user->refresh();
        self::assertTrue($user->hasEnabledTwoFactorAuthentication());
        self::assertNotNull($user->two_factor_confirmed_at);
        self::assertNotSame(json_encode($recoveryCodes), $user->two_factor_recovery_codes);

        $this->actingAs($user, 'web');
        $this->getJson('/api/v1/auth/mfa')
            ->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonPath('pending_confirmation', false);
    }

    public function test_session_login_requires_mfa_and_consumes_a_recovery_code_once(): void
    {
        $user = User::factory()->create(['email' => 'mfa-session@example.test']);
        [, $recoveryCodes] = $this->enableMfa($user);
        $headers = ['Origin' => 'http://localhost:3000'];

        $login = $this->withHeaders($headers)->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertAccepted()
            ->assertJsonPath('mfa_required', true)
            ->assertJsonPath('authentication_type', 'session')
            ->assertJsonMissingPath('user');

        $this->assertGuest('web');
        $challengeToken = $login->json('challenge_token');

        $this->withHeaders($headers)->postJson('/api/v1/auth/mfa/challenge', [
            'challenge_token' => $challengeToken,
            'recovery_code' => $recoveryCodes[0],
        ])->assertOk()
            ->assertJsonPath('mfa_required', false)
            ->assertJsonPath('user.id', $user->public_id);

        $this->assertAuthenticatedAs($user, 'web');
        self::assertNotContains($recoveryCodes[0], $user->fresh()->recoveryCodes());

        $this->withHeaders($headers)->postJson('/api/v1/auth/mfa/challenge', [
            'challenge_token' => $challengeToken,
            'recovery_code' => $recoveryCodes[0],
        ])->assertUnprocessable()->assertJsonValidationErrors('challenge_token');
    }

    public function test_token_login_issues_nothing_until_mfa_and_rejects_totp_replay(): void
    {
        $user = User::factory()->create(['email' => 'mfa-token@example.test']);
        $store = Store::factory()->create();
        StoreMembership::factory()->create(['user_id' => $user, 'store_id' => $store]);
        [$secret] = $this->enableMfa($user);
        $credentials = [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'local-test',
            'store_id' => $store->public_id,
        ];

        $login = $this->postJson('/api/v1/auth/token', $credentials)
            ->assertAccepted()
            ->assertJsonPath('mfa_required', true)
            ->assertJsonPath('authentication_type', 'token')
            ->assertJsonMissingPath('token');
        self::assertDatabaseCount('personal_access_tokens', 0);

        $code = app(Google2FA::class)->getCurrentOtp($secret);
        $completed = $this->postJson('/api/v1/auth/mfa/challenge', [
            'challenge_token' => $login->json('challenge_token'),
            'code' => $code,
        ])->assertOk()
            ->assertJsonPath('mfa_required', false)
            ->assertJsonPath('token_type', 'Bearer');

        self::assertNotEmpty($completed->json('token'));
        self::assertTrue((bool) PersonalAccessToken::query()->firstOrFail()->metadata['mfa_verified']);

        $replayChallenge = $this->postJson('/api/v1/auth/token', $credentials)->assertAccepted();
        $this->postJson('/api/v1/auth/mfa/challenge', [
            'challenge_token' => $replayChallenge->json('challenge_token'),
            'code' => $code,
        ])->assertUnprocessable()->assertJsonValidationErrors('code');
        self::assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_mfa_recovery_codes_can_be_rotated_and_mfa_can_be_disabled_with_password(): void
    {
        $user = User::factory()->create();
        [, $originalCodes] = $this->enableMfa($user);
        $this->actingAs($user, 'web');

        $this->postJson('/api/v1/auth/mfa/recovery-codes', [
            'current_password' => 'wrong',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->actingAs($user->fresh(), 'web');
        $rotated = $this->postJson('/api/v1/auth/mfa/recovery-codes', [
            'current_password' => 'password',
        ])->assertOk()->assertJsonCount(8, 'recovery_codes');
        self::assertNotSame($originalCodes, $rotated->json('recovery_codes'));

        $this->actingAs($user->fresh(), 'web');
        $this->deleteJson('/api/v1/auth/mfa', [
            'current_password' => 'password',
        ])->assertOk()->assertJsonPath('enabled', false);

        $user->refresh();
        self::assertNull($user->two_factor_secret);
        self::assertNull($user->two_factor_recovery_codes);
        self::assertNull($user->two_factor_confirmed_at);
    }

    /** @return array{string, list<string>} */
    private function enableMfa(User $user): array
    {
        $secret = app(TwoFactorAuthenticationProvider::class)->generateSecretKey(32);
        $recoveryCodes = collect(range(1, 8))
            ->map(fn (): string => RecoveryCode::generate())
            ->all();

        $user->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode($recoveryCodes, JSON_THROW_ON_ERROR)),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return [$secret, $recoveryCodes];
    }
}
