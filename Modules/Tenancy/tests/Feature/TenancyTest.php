<?php

declare(strict_types=1);

namespace Modules\Tenancy\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Authentication\Models\User;
use Modules\Tenancy\Cache\TenantCacheKey;
use Modules\Tenancy\Contracts\TenantContext;
use Modules\Tenancy\Enums\MembershipStatus;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Models\TenantMembership;
use Tests\TestCase;

final class TenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_member_can_select_tenant_and_context_is_cleared_between_requests(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        TenantMembership::factory()->create(['user_id' => $user, 'tenant_id' => $tenant]);

        $token = $user->createToken('test', ['tenant:access'])->plainTextToken;
        $this->withToken($token)->withHeader('X-Tenant-ID', $tenant->getKey())
            ->postJson('/graphql', ['query' => '{ activeTenant { id } }'])
            ->assertOk()->assertJsonPath('data.activeTenant.id', $tenant->getKey());

        $this->withoutHeader('X-Tenant-ID')->withToken($token)->postJson('/graphql', ['query' => '{ activeTenant { id } }'])
            ->assertOk()->assertJsonStructure(['errors']);
        self::assertNull(app(TenantContext::class)->current());
    }

    public function test_missing_unknown_and_suspended_tenant_contexts_are_rejected(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $token = $user->createToken('test', ['tenant:access'])->plainTextToken;

        $this->withToken($token)->postJson('/graphql', ['query' => '{ activeTenant { id } }'])
            ->assertOk()->assertJsonStructure(['errors']);
        $this->withToken($token)->withHeader('X-Tenant-ID', (string) fake()->uuid())
            ->postJson('/graphql', ['query' => '{ activeTenant { id } }'])
            ->assertNotFound()->assertJsonPath('message', 'Not found.');

        TenantMembership::factory()->create(['user_id' => $user, 'tenant_id' => $tenant, 'status' => MembershipStatus::Suspended]);
        $this->withToken($token)->withHeader('X-Tenant-ID', $tenant->getKey())
            ->postJson('/graphql', ['query' => '{ activeTenant { id } }'])
            ->assertForbidden()->assertJsonPath('message', 'Active tenant membership is required.');
    }

    public function test_tenant_cache_keys_are_isolated(): void
    {
        $first = Tenant::factory()->create();
        $second = Tenant::factory()->create();
        $context = app(TenantContext::class);
        $first->makeCurrent();
        $context->set($first);
        $firstKey = app(TenantCacheKey::class)->for('settings');
        $context->clear();
        Tenant::forgetCurrent();
        $second->makeCurrent();
        $context->set($second);
        $secondKey = app(TenantCacheKey::class)->for('settings');
        self::assertNotSame($firstKey, $secondKey);
        $context->clear();
        Tenant::forgetCurrent();
    }
}
