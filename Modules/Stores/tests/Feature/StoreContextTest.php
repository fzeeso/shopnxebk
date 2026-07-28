<?php

declare(strict_types=1);

namespace Modules\Stores\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Authentication\Models\User;
use Modules\Stores\Cache\StoreCacheKey;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Enums\MembershipStatus;
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
}
