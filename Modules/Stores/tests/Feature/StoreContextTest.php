<?php

declare(strict_types=1);

namespace Modules\Stores\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Authentication\Models\User;
use Modules\Stores\Cache\StoreCacheKey;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Enums\BusinessType;
use Modules\Stores\Enums\MembershipStatus;
use Modules\Stores\Enums\StoreStatus;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StoreMembership;
use Tests\TestCase;

final class StoreContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_member_can_select_store_and_context_is_cleared_between_requests(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create();
        StoreMembership::factory()->create(['user_id' => $user, 'store_id' => $store]);

        $token = $user->createToken('test', ['store:access'])->plainTextToken;
        $this->withToken($token)->withHeader('X-Store-ID', $store->public_id)
            ->postJson('/graphql', ['query' => '{ activeStore { id } }'])
            ->assertOk()->assertJsonPath('data.activeStore.id', $store->public_id);

        $this->withoutHeader('X-Store-ID')->withToken($token)->postJson('/graphql', ['query' => '{ activeStore { id } }'])
            ->assertOk()->assertJsonStructure(['errors']);
        self::assertNull(app(StoreContext::class)->current());
    }

    public function test_missing_unknown_and_suspended_store_contexts_are_rejected(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create();
        $token = $user->createToken('test', ['store:access'])->plainTextToken;

        $this->withToken($token)->postJson('/graphql', ['query' => '{ activeStore { id } }'])
            ->assertOk()->assertJsonStructure(['errors']);
        $this->withToken($token)->withHeader('X-Store-ID', (string) Str::ulid())
            ->postJson('/graphql', ['query' => '{ activeStore { id } }'])
            ->assertNotFound()->assertJsonPath('message', 'Not found.');

        StoreMembership::factory()->create(['user_id' => $user, 'store_id' => $store, 'status' => MembershipStatus::Suspended]);
        $this->withToken($token)->withHeader('X-Store-ID', $store->public_id)
            ->postJson('/graphql', ['query' => '{ activeStore { id } }'])
            ->assertForbidden()->assertJsonPath('message', 'Active store membership is required.');
    }

    public function test_platform_account_cannot_enter_store_context(): void
    {
        $platformUser = User::factory()->platform()->create();
        $store = Store::factory()->create();
        $token = $platformUser->createToken('platform', ['account:read'])->plainTextToken;

        $this->withToken($token)
            ->withHeader('X-Store-ID', $store->public_id)
            ->postJson('/graphql', ['query' => '{ activeStore { id } }'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Store-scoped account required.');
    }

    public function test_store_cache_keys_are_isolated(): void
    {
        $first = Store::factory()->create();
        $second = Store::factory()->create();
        $context = app(StoreContext::class);
        $first->makeCurrent();
        $context->set($first);
        $firstKey = app(StoreCacheKey::class)->for('settings');
        $context->clear();
        Store::forgetCurrent();
        $second->makeCurrent();
        $context->set($second);
        $secondKey = app(StoreCacheKey::class)->for('settings');
        self::assertNotSame($firstKey, $secondKey);
        $context->clear();
        Store::forgetCurrent();
    }

    public function test_store_profile_capabilities_and_lifecycle_are_typed_and_publicly_safe(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create([
            'legal_name' => 'Acme Commerce Limited',
            'description' => 'Wholesale and retail commerce.',
            'email' => 'merchant@example.test',
            'phone' => '+1-555-0100',
            'logo' => 'stores/acme/logo.png',
            'favicon' => 'stores/acme/favicon.ico',
            'cover_image' => 'stores/acme/cover.jpg',
            'industry' => 'consumer-goods',
            'business_type' => BusinessType::B2B,
            'plan_id' => 101,
            'subscription_id' => 202,
            'currency_code' => 'EUR',
            'language_code' => 'en-GB',
            'timezone' => 'Europe/London',
            'country_code' => 'GB',
            'status' => StoreStatus::Active,
            'is_verified' => true,
            'is_ai_enabled' => true,
            'is_pos_enabled' => false,
            'is_b2b_enabled' => true,
            'is_marketplace_enabled' => false,
            'launched_at' => '2026-07-01 09:30:00+00',
            'trial_ends_at' => '2026-07-31 09:30:00+00',
        ])->fresh();
        StoreMembership::factory()->create(['user_id' => $user, 'store_id' => $store]);

        self::assertSame(BusinessType::B2B, $store->business_type);
        self::assertSame(StoreStatus::Active, $store->status);
        self::assertTrue($store->is_verified);
        self::assertTrue($store->is_ai_enabled);
        self::assertTrue($store->is_b2b_enabled);
        self::assertNotNull($store->launched_at);
        self::assertNotNull($store->trial_ends_at);

        $response = $this->actingAs($user, 'web')
            ->getJson('/api/v1/auth/stores')
            ->assertOk()
            ->assertJsonPath('data.0.id', $store->public_id)
            ->assertJsonPath('data.0.legal_name', 'Acme Commerce Limited')
            ->assertJsonPath('data.0.business_type', 'b2b')
            ->assertJsonPath('data.0.currency_code', 'EUR')
            ->assertJsonPath('data.0.status', 'active')
            ->assertJsonPath('data.0.is_verified', true)
            ->assertJsonPath('data.0.is_ai_enabled', true)
            ->assertJsonPath('data.0.is_b2b_enabled', true);

        $publicStore = $response->json('data.0');
        self::assertArrayNotHasKey('plan_id', $publicStore);
        self::assertArrayNotHasKey('subscription_id', $publicStore);

        $graphql = $this->actingAs($user, 'web')
            ->withHeader('X-Store-ID', $store->public_id)
            ->postJson('/graphql', ['query' => '{ activeStore { id legalName businessType status isVerified isAiEnabled isB2bEnabled launchedAt trialEndsAt } }'])
            ->assertOk()
            ->assertJsonMissing(['errors'])
            ->assertJsonPath('data.activeStore.id', $store->public_id)
            ->assertJsonPath('data.activeStore.legalName', 'Acme Commerce Limited')
            ->assertJsonPath('data.activeStore.businessType', 'b2b')
            ->assertJsonPath('data.activeStore.status', 'active')
            ->assertJsonPath('data.activeStore.isVerified', true)
            ->assertJsonPath('data.activeStore.isAiEnabled', true)
            ->assertJsonPath('data.activeStore.isB2bEnabled', true);

        self::assertNotNull($graphql->json('data.activeStore.launchedAt'));
        self::assertNotNull($graphql->json('data.activeStore.trialEndsAt'));
    }
}
