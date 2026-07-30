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
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Modules\Authentication\Actions\EnsureAuthorizationCatalog;
use Modules\Authentication\Enums\AccessScope;
use Modules\Authentication\Models\PersonalAccessToken;
use Modules\Authentication\Models\User;
use Modules\Authentication\Notifications\QueuedResetPassword;
use Modules\Authentication\Notifications\QueuedVerifyEmail;
use Modules\Authentication\Services\ScopedRoleAssignmentService;
use Modules\Stores\Enums\MembershipStatus;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StoreMembership;
use Tests\TestCase;

final class AccountApiFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_user_store_owner_membership_and_permissions(): void
    {
        Notification::fake();
        $response = $this->postJson('/api/v1/auth/register', $this->registrationPayload());

        $response->assertCreated()
            ->assertJsonPath('user.email', 'owner@example.test')
            ->assertJsonPath('user.scope', 'store')
            ->assertJsonPath('store.slug', 'acme-shop');
        $user = User::query()->where('email', 'owner@example.test')->firstOrFail();
        $store = Store::query()->where('slug', 'acme-shop')->firstOrFail();
        self::assertIsInt($user->getKey());
        self::assertTrue(Str::isUlid($user->public_id));
        self::assertSame(AccessScope::Store, $user->scope);
        self::assertIsInt($store->getKey());
        self::assertTrue(Str::isUlid($store->public_id));
        self::assertSame('Acme Shop', $store->legal_name);
        self::assertSame('USD', $store->currency_code);
        self::assertSame('en', $store->language_code);
        self::assertSame('UTC', $store->timezone);
        self::assertTrue(StoreMembership::query()->whereBelongsTo($store)->whereBelongsTo($user)->where('status', 'active')->exists());
        setPermissionsTeamId($store->getKey());
        self::assertTrue($user->fresh()->hasRole('Owner'));
        self::assertTrue($user->fresh()->can('manage store'));
        Notification::assertSentTo($user, QueuedVerifyEmail::class);
    }

    public function test_registration_rejects_duplicate_email_and_slug(): void
    {
        Notification::fake();
        $this->postJson('/api/v1/auth/register', $this->registrationPayload())->assertCreated();
        $this->postJson('/api/v1/auth/register', $this->registrationPayload(['store_slug' => 'another-shop']))->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/api/v1/auth/register', $this->registrationPayload(['email' => 'second@example.test']))->assertUnprocessable()->assertJsonValidationErrors('store_slug');
    }

    public function test_session_login_failure_rate_limit_and_logout_are_json(): void
    {
        $user = User::factory()->create(['email' => 'login@example.test']);
        $headers = ['Origin' => 'http://localhost:3000'];
        $this->withHeaders($headers)->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.id', $user->public_id)
            ->assertJsonPath('user.scope', 'store');
        $this->postJson('/api/v1/auth/logout')->assertOk();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.8'])->postJson('/api/v1/auth/login', ['email' => 'missing@example.test', 'password' => 'wrong'])->assertUnprocessable();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.8'])->postJson('/api/v1/auth/login', ['email' => 'missing@example.test', 'password' => 'wrong'])->assertTooManyRequests();
    }

    public function test_store_token_is_issued_once_cannot_cross_stores_and_can_be_revoked(): void
    {
        $user = User::factory()->create(['email' => 'token@example.test']);
        $first = Store::factory()->create();
        $second = Store::factory()->create();
        StoreMembership::factory()->create(['user_id' => $user, 'store_id' => $first]);
        StoreMembership::factory()->create(['user_id' => $user, 'store_id' => $second]);

        $issued = $this->postJson('/api/v1/auth/token', ['email' => $user->email, 'password' => 'password', 'device_name' => 'cli', 'store_id' => $first->public_id])->assertOk();
        $plain = $issued->json('token');
        $storedToken = PersonalAccessToken::query()->firstOrFail();
        self::assertNotSame($plain, $storedToken->token);
        self::assertSame($first->getKey(), $storedToken->store_id);
        self::assertNotNull($storedToken->expires_at);
        self::assertTrue($storedToken->expires_at->between(
            now()->addMinutes(43199),
            now()->addMinutes(43201),
        ));

        $this->withToken($plain)->withHeader('X-Store-ID', $second->public_id)->postJson('/graphql', ['query' => '{ activeStore { id } }'])->assertForbidden();
        $publicId = $storedToken->public_id;
        $this->withToken($plain)->deleteJson('/api/v1/auth/tokens/'.$publicId)->assertNoContent();
        self::assertDatabaseMissing('personal_access_tokens', ['public_id' => $publicId]);
    }

    public function test_token_login_rejects_inactive_or_non_member_without_disclosing_credentials(): void
    {
        $user = User::factory()->create(['email' => 'blocked@example.test']);
        $store = Store::factory()->create();
        StoreMembership::factory()->create(['user_id' => $user, 'store_id' => $store, 'status' => MembershipStatus::Suspended]);

        $this->postJson('/api/v1/auth/token', ['email' => $user->email, 'password' => 'password', 'device_name' => 'cli', 'store_id' => $store->public_id])->assertForbidden();
        $this->postJson('/api/v1/auth/token', ['email' => 'absent@example.test', 'password' => 'wrong', 'device_name' => 'cli', 'store_id' => $store->public_id])->assertUnauthorized()->assertJsonMissing(['email' => 'absent@example.test']);
    }

    public function test_store_routes_reject_unbound_or_underprivileged_bearer_tokens(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create();
        StoreMembership::factory()->create(['user_id' => $user, 'store_id' => $store]);

        $accountToken = $user->createToken('account-only', ['account:read'])->plainTextToken;
        $this->withToken($accountToken)
            ->withHeader('X-Store-ID', $store->public_id)
            ->postJson('/graphql', ['query' => '{ activeStore { id } }'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Token does not have Store access.');

        $this->actingAs($user, 'web')
            ->postJson('/api/v1/auth/tokens', [
                'device_name' => 'invalid-unbound-store-token',
                'abilities' => ['store:access'],
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Store access tokens must be bound to a Store.');
    }

    public function test_a_legacy_uuid_prefixed_bearer_token_remains_usable_during_transition(): void
    {
        $user = User::factory()->create();
        $issued = $user->createToken('legacy-compatible', ['account:read']);
        [, $plainTextSecret] = explode('|', $issued->plainTextToken, 2);
        $legacyId = (string) Str::uuid();
        $issued->accessToken->forceFill(['legacy_id' => $legacyId])->save();

        self::assertSame(PersonalAccessToken::class, Sanctum::personalAccessTokenModel());
        self::assertNotNull(PersonalAccessToken::findToken($legacyId.'|'.$plainTextSecret));
        $this->withToken($legacyId.'|'.$plainTextSecret)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->public_id)
            ->assertJsonPath('user.scope', 'store');
    }

    public function test_password_reset_and_email_verification_flows_are_json_and_queued(): void
    {
        Notification::fake();
        Event::fake([Verified::class]);
        $user = User::factory()->unverified()->create(['email' => 'reset@example.test']);
        $user->createToken('revoke-on-reset', ['account:read']);
        self::assertDatabaseCount('personal_access_tokens', 1);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertAccepted();
        Notification::assertSentTo($user, QueuedResetPassword::class);

        $token = Password::createToken($user);
        $this->postJson('/api/v1/auth/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'NewPassword!123', 'password_confirmation' => 'NewPassword!123'])->assertOk();
        self::assertTrue(Hash::check('NewPassword!123', $user->fresh()->password));
        self::assertDatabaseCount('personal_access_tokens', 0);

        $url = URL::temporarySignedRoute('api.v1.auth.verification.verify', now()->addMinutes(30), ['id' => $user->public_id, 'hash' => sha1($user->email)]);
        $this->getJson($url)->assertOk();
        self::assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_unauthenticated_account_route_returns_json_401(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_platform_and_store_authorization_catalog_is_scoped_and_extendable(): void
    {
        app(EnsureAuthorizationCatalog::class)->ensure();
        $roleAssignments = app(ScopedRoleAssignmentService::class);

        self::assertDatabaseHas('roles', ['name' => 'Super Admin', 'scope' => AccessScope::Platform->value, 'store_id' => null]);
        self::assertDatabaseHas('roles', ['name' => 'Owner', 'scope' => AccessScope::Store->value, 'store_id' => null]);
        self::assertDatabaseHas('permissions', ['name' => 'manage marketplace', 'scope' => AccessScope::Platform->value]);
        self::assertDatabaseHas('permissions', ['name' => 'manage products', 'scope' => AccessScope::Store->value]);

        $platformUser = User::factory()->platform()->create();
        $roleAssignments->assignPlatformRole($platformUser, 'Super Admin');
        self::assertTrue($platformUser->isPlatformSuperAdmin());
        self::assertTrue($platformUser->can('manage marketplace'));

        $storeOwner = User::factory()->create();
        $store = Store::factory()->create();
        StoreMembership::factory()->create(['user_id' => $storeOwner, 'store_id' => $store]);
        $roleAssignments->assignStoreRole($storeOwner, $store, 'Owner');
        setPermissionsTeamId($store->getKey());
        self::assertFalse($storeOwner->isPlatformSuperAdmin());
        self::assertTrue($storeOwner->fresh()->can('manage products'));
    }

    private function registrationPayload(array $overrides = []): array
    {
        return array_replace(['name' => 'Owner', 'email' => 'Owner@Example.Test', 'password' => 'StrongPassword!123', 'password_confirmation' => 'StrongPassword!123', 'store_name' => 'Acme Shop', 'store_slug' => 'acme-shop'], $overrides);
    }
}
