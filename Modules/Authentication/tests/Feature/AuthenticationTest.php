<?php

declare(strict_types=1);

namespace Modules\Authentication\Tests\Feature;

use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Modules\Authentication\Models\PersonalAccessToken;
use Modules\Authentication\Models\User;
use Modules\Authentication\Notifications\QueuedResetPassword;
use Modules\Authentication\Notifications\QueuedVerifyEmail;
use Modules\Tenancy\Enums\MembershipStatus;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Models\TenantMembership;
use Tests\TestCase;

final class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_user_tenant_owner_membership_and_permissions(): void
    {
        Notification::fake();
        $response = $this->postJson('/api/v1/auth/register', $this->registrationPayload());

        $response->assertCreated()->assertJsonPath('user.email', 'owner@example.test')->assertJsonPath('tenant.slug', 'acme-shop');
        $user = User::query()->where('email', 'owner@example.test')->firstOrFail();
        $tenant = Tenant::query()->where('slug', 'acme-shop')->firstOrFail();
        self::assertTrue(TenantMembership::query()->whereBelongsTo($tenant)->whereBelongsTo($user)->where('status', 'active')->exists());
        setPermissionsTeamId($tenant->getKey());
        self::assertTrue($user->fresh()->hasRole('owner'));
        self::assertTrue($user->fresh()->can('tenant.manage'));
        Notification::assertSentTo($user, QueuedVerifyEmail::class);
    }

    public function test_registration_rejects_duplicate_email_and_slug(): void
    {
        Notification::fake();
        $this->postJson('/api/v1/auth/register', $this->registrationPayload())->assertCreated();
        $this->postJson('/api/v1/auth/register', $this->registrationPayload(['tenant_slug' => 'another-shop']))->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/api/v1/auth/register', $this->registrationPayload(['email' => 'second@example.test']))->assertUnprocessable()->assertJsonValidationErrors('tenant_slug');
    }

    public function test_session_login_failure_rate_limit_and_logout_are_json(): void
    {
        $user = User::factory()->create(['email' => 'login@example.test']);
        $headers = ['Origin' => 'http://localhost:3000'];
        $this->withHeaders($headers)->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])->assertOk()->assertJsonPath('user.id', $user->getKey());
        $this->postJson('/api/v1/auth/logout')->assertOk();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.8'])->postJson('/api/v1/auth/login', ['email' => 'missing@example.test', 'password' => 'wrong'])->assertUnprocessable();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.8'])->postJson('/api/v1/auth/login', ['email' => 'missing@example.test', 'password' => 'wrong'])->assertTooManyRequests();
    }

    public function test_tenant_token_is_issued_once_cannot_cross_tenants_and_can_be_revoked(): void
    {
        $user = User::factory()->create(['email' => 'token@example.test']);
        $first = Tenant::factory()->create();
        $second = Tenant::factory()->create();
        TenantMembership::factory()->create(['user_id' => $user, 'tenant_id' => $first]);
        TenantMembership::factory()->create(['user_id' => $user, 'tenant_id' => $second]);

        $issued = $this->postJson('/api/v1/auth/token', ['email' => $user->email, 'password' => 'password', 'device_name' => 'cli', 'tenant_id' => $first->getKey()])->assertOk();
        $plain = $issued->json('token');
        self::assertNotSame($plain, PersonalAccessToken::query()->firstOrFail()->token);

        $this->withToken($plain)->withHeader('X-Tenant-ID', $second->getKey())->postJson('/graphql', ['query' => '{ activeTenant { id } }'])->assertForbidden();
        $id = PersonalAccessToken::query()->firstOrFail()->getKey();
        $this->withToken($plain)->deleteJson('/api/v1/auth/tokens/'.$id)->assertNoContent();
        self::assertDatabaseMissing('personal_access_tokens', ['id' => $id]);
    }

    public function test_token_login_rejects_inactive_or_non_member_without_disclosing_credentials(): void
    {
        $user = User::factory()->create(['email' => 'blocked@example.test']);
        $tenant = Tenant::factory()->create();
        TenantMembership::factory()->create(['user_id' => $user, 'tenant_id' => $tenant, 'status' => MembershipStatus::Suspended]);

        $this->postJson('/api/v1/auth/token', ['email' => $user->email, 'password' => 'password', 'device_name' => 'cli', 'tenant_id' => $tenant->getKey()])->assertForbidden();
        $this->postJson('/api/v1/auth/token', ['email' => 'absent@example.test', 'password' => 'wrong', 'device_name' => 'cli', 'tenant_id' => $tenant->getKey()])->assertUnauthorized()->assertJsonMissing(['email' => 'absent@example.test']);
    }

    public function test_password_reset_and_email_verification_flows_are_json_and_queued(): void
    {
        Notification::fake();
        Event::fake([Verified::class]);
        $user = User::factory()->unverified()->create(['email' => 'reset@example.test']);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertAccepted();
        Notification::assertSentTo($user, QueuedResetPassword::class);

        $token = Password::createToken($user);
        $this->postJson('/api/v1/auth/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'NewPassword!123', 'password_confirmation' => 'NewPassword!123'])->assertOk();
        self::assertTrue(Hash::check('NewPassword!123', $user->fresh()->password));

        $url = URL::temporarySignedRoute('api.v1.auth.verification.verify', now()->addMinutes(30), ['id' => $user->getKey(), 'hash' => sha1($user->email)]);
        $this->getJson($url)->assertOk();
        self::assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_unauthenticated_account_route_returns_json_401(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
    }

    private function registrationPayload(array $overrides = []): array
    {
        return array_replace(['name' => 'Owner', 'email' => 'Owner@Example.Test', 'password' => 'StrongPassword!123', 'password_confirmation' => 'StrongPassword!123', 'tenant_name' => 'Acme Shop', 'tenant_slug' => 'acme-shop'], $overrides);
    }
}
