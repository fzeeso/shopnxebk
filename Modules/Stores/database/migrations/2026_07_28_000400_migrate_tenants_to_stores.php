<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Authentication\Actions\EnsureAuthorizationCatalog;
use Modules\Authentication\Models\User;

return new class extends Migration
{
    public $withinTransaction = true;

    /** @var array<string, string> */
    private const PERMISSION_NAMES = [
        'tenant.access' => 'access store',
        'tenant.manage' => 'manage store',
        'members.manage' => 'manage store members',
        'roles.manage' => 'manage store roles',
    ];

    /** @var array<string, string> */
    private const ROLE_NAMES = [
        'owner' => 'Owner',
        'admin' => 'Manager',
        'staff' => 'Sales',
    ];

    public function up(): void
    {
        if (Schema::hasTable('stores')) {
            return;
        }

        if (! Schema::hasTable('tenants')) {
            throw new RuntimeException('Neither the stores table nor the legacy tenants table exists.');
        }

        $this->assertSupportedPolymorphicRows();
        $legacyPlatformAdminIds = DB::table('users')->where('is_platform_admin', true)->pluck('id')->all();
        $this->createNextTables();

        $userIds = $this->copyUsers();
        $storeIds = $this->copyStores();
        $this->copyMemberships($userIds, $storeIds);
        [$permissionIds, $roleIds] = $this->copyAuthorizationCatalog($storeIds);
        $this->copyAuthorizationAssignments($userIds, $storeIds, $permissionIds, $roleIds);
        $this->copyTokens($userIds, $storeIds);
        $this->copySessions($userIds);
        $this->copyNotifications($userIds);
        $this->copyMedia($userIds, $storeIds);
        $this->copyQueueRows();

        $this->replaceLegacyTables();

        app(EnsureAuthorizationCatalog::class)->ensure();
        $superAdminRoleId = DB::table('roles')
            ->whereNull('store_id')
            ->where('name', 'Super Admin')
            ->where('guard_name', 'web')
            ->value('id');

        foreach ($legacyPlatformAdminIds as $legacyUserId) {
            if (! isset($userIds[(string) $legacyUserId])) {
                continue;
            }

            DB::table('model_has_roles')->insertOrIgnore([
                'role_id' => $superAdminRoleId,
                'model_type' => User::class,
                'model_id' => $userIds[(string) $legacyUserId],
                'store_id' => null,
            ]);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('The UUID-to-bigint Store migration is intentionally irreversible.');
    }

    private function assertSupportedPolymorphicRows(): void
    {
        $checks = [
            ['personal_access_tokens', 'tokenable_type'],
            ['notifications', 'notifiable_type'],
            ['model_has_roles', 'model_type'],
            ['model_has_permissions', 'model_type'],
            ['media', 'model_type'],
        ];

        foreach ($checks as [$table, $column]) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $unsupported = DB::table($table)
                ->whereNot($column, User::class)
                ->whereNotNull($column)
                ->exists();

            if ($unsupported) {
                throw new RuntimeException("Cannot safely convert non-user polymorphic identifiers in {$table}.");
            }
        }
    }

    private function createNextTables(): void
    {
        Schema::create('users_next', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestampTz('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestampTz('two_factor_confirmed_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('stores_next', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->index();
            $table->string('primary_domain')->nullable()->unique();
            $table->jsonb('settings')->default('{}');
            $table->jsonb('metadata')->default('{}');
            $table->timestampsTz();
        });

        Schema::create('store_memberships_next', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores_next')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users_next')->cascadeOnDelete();
            $table->string('status')->index();
            $table->timestampTz('invited_at')->nullable();
            $table->timestampTz('joined_at')->nullable();
            $table->timestampsTz();
            $table->unique(['store_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('permissions_next', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->string('scope')->index();
            $table->string('guard_name');
            $table->timestampsTz();
            $table->unique(['name', 'guard_name', 'scope']);
        });

        Schema::create('roles_next', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->nullable()->constrained('stores_next')->cascadeOnDelete();
            $table->string('name');
            $table->string('scope')->index();
            $table->string('guard_name');
            $table->timestampsTz();
        });
        DB::statement('CREATE UNIQUE INDEX roles_next_global_unique ON roles_next (name, guard_name, scope) WHERE store_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX roles_next_store_unique ON roles_next (store_id, name, guard_name, scope) WHERE store_id IS NOT NULL');

        Schema::create('model_has_permissions_next', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('permission_id')->constrained('permissions_next')->cascadeOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->foreignId('store_id')->nullable()->constrained('stores_next')->cascadeOnDelete();
            $table->index(['model_id', 'model_type']);
        });
        DB::statement('CREATE UNIQUE INDEX model_permissions_next_global_unique ON model_has_permissions_next (permission_id, model_id, model_type) WHERE store_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX model_permissions_next_store_unique ON model_has_permissions_next (store_id, permission_id, model_id, model_type) WHERE store_id IS NOT NULL');

        Schema::create('model_has_roles_next', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_id')->constrained('roles_next')->cascadeOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->foreignId('store_id')->nullable()->constrained('stores_next')->cascadeOnDelete();
            $table->index(['model_id', 'model_type']);
        });
        DB::statement('CREATE UNIQUE INDEX model_roles_next_global_unique ON model_has_roles_next (role_id, model_id, model_type) WHERE store_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX model_roles_next_store_unique ON model_has_roles_next (store_id, role_id, model_id, model_type) WHERE store_id IS NOT NULL');

        Schema::create('role_has_permissions_next', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('permission_id')->constrained('permissions_next')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles_next')->cascadeOnDelete();
            $table->unique(['permission_id', 'role_id']);
        });

        Schema::create('personal_access_tokens_next', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->uuid('legacy_id')->nullable()->unique();
            $table->string('tokenable_type');
            $table->unsignedBigInteger('tokenable_id');
            $table->foreignId('store_id')->nullable()->constrained('stores_next')->cascadeOnDelete();
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->jsonb('abilities')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->timestampsTz();
            $table->index(['tokenable_type', 'tokenable_id']);
            $table->index(['store_id', 'tokenable_id']);
        });

        Schema::create('sessions_next', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('notifications_next', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->unsignedBigInteger('notifiable_id');
            $table->string('notifiable_type');
            $table->text('data');
            $table->timestampTz('read_at')->nullable();
            $table->timestampsTz();
            $table->index(['notifiable_type', 'notifiable_id']);
        });

        Schema::create('media_next', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores_next')->cascadeOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->uuid('uuid')->unique();
            $table->string('collection_name');
            $table->string('name');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->string('disk');
            $table->string('conversions_disk')->nullable();
            $table->unsignedBigInteger('size');
            $table->jsonb('manipulations');
            $table->jsonb('custom_properties');
            $table->jsonb('generated_conversions');
            $table->jsonb('responsive_images');
            $table->unsignedInteger('order_column')->nullable()->index();
            $table->timestampsTz();
            $table->index(['store_id', 'model_type', 'model_id']);
        });

        Schema::create('jobs_next', function (Blueprint $table): void {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedSmallInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('failed_jobs_next', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('connection');
            $table->string('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestampTz('failed_at')->useCurrent();
            $table->index(['connection', 'queue', 'failed_at']);
        });
    }

    /** @return array<string, int> */
    private function copyUsers(): array
    {
        $ids = [];

        foreach (DB::table('users')->orderBy('created_at')->get() as $user) {
            $ids[(string) $user->id] = (int) DB::table('users_next')->insertGetId([
                'public_id' => (string) Str::ulid(),
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'password' => $user->password,
                'remember_token' => $user->remember_token,
                'two_factor_secret' => $user->two_factor_secret,
                'two_factor_recovery_codes' => $user->two_factor_recovery_codes,
                'two_factor_confirmed_at' => $user->two_factor_confirmed_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ], 'id');
        }

        return $ids;
    }

    /** @return array<string, int> */
    private function copyStores(): array
    {
        $ids = [];

        foreach (DB::table('tenants')->orderBy('created_at')->get() as $store) {
            $ids[(string) $store->id] = (int) DB::table('stores_next')->insertGetId([
                'public_id' => (string) Str::ulid(),
                'name' => $store->name,
                'slug' => $store->slug,
                'status' => $store->status,
                'primary_domain' => $store->primary_domain,
                'settings' => $store->settings,
                'metadata' => $store->metadata,
                'created_at' => $store->created_at,
                'updated_at' => $store->updated_at,
            ], 'id');
        }

        return $ids;
    }

    /** @param array<string, int> $userIds @param array<string, int> $storeIds */
    private function copyMemberships(array $userIds, array $storeIds): void
    {
        foreach (DB::table('tenant_memberships')->get() as $membership) {
            DB::table('store_memberships_next')->insert([
                'public_id' => (string) Str::ulid(),
                'store_id' => $storeIds[(string) $membership->tenant_id],
                'user_id' => $userIds[(string) $membership->user_id],
                'status' => $membership->status,
                'invited_at' => $membership->invited_at,
                'joined_at' => $membership->joined_at,
                'created_at' => $membership->created_at,
                'updated_at' => $membership->updated_at,
            ]);
        }
    }

    /**
     * @param  array<string, int>  $storeIds
     * @return array{array<string, int>, array<string, int>}
     */
    private function copyAuthorizationCatalog(array $storeIds): array
    {
        $permissionIds = [];
        foreach (DB::table('permissions')->get() as $permission) {
            $name = self::PERMISSION_NAMES[$permission->name] ?? $permission->name;
            $target = DB::table('permissions_next')
                ->where('name', $name)
                ->where('guard_name', $permission->guard_name)
                ->where('scope', 'store')
                ->first();

            $permissionIds[(string) $permission->id] = $target?->id ?? DB::table('permissions_next')->insertGetId([
                'public_id' => (string) Str::ulid(),
                'name' => $name,
                'scope' => 'store',
                'guard_name' => $permission->guard_name,
                'created_at' => $permission->created_at,
                'updated_at' => $permission->updated_at,
            ], 'id');
        }

        $roleIds = [];
        foreach (DB::table('roles')->get() as $role) {
            $canonicalName = self::ROLE_NAMES[Str::lower($role->name)] ?? null;
            $name = $canonicalName ?? $role->name;
            $storeId = $canonicalName === null ? $storeIds[(string) $role->tenant_id] : null;
            $query = DB::table('roles_next')
                ->where('name', $name)
                ->where('guard_name', $role->guard_name)
                ->where('scope', 'store');
            $storeId === null ? $query->whereNull('store_id') : $query->where('store_id', $storeId);
            $target = $query->first();

            $roleIds[(string) $role->id] = $target?->id ?? DB::table('roles_next')->insertGetId([
                'public_id' => (string) Str::ulid(),
                'store_id' => $storeId,
                'name' => $name,
                'scope' => 'store',
                'guard_name' => $role->guard_name,
                'created_at' => $role->created_at,
                'updated_at' => $role->updated_at,
            ], 'id');
        }

        foreach (DB::table('role_has_permissions')->get() as $assignment) {
            DB::table('role_has_permissions_next')->insertOrIgnore([
                'permission_id' => $permissionIds[(string) $assignment->permission_id],
                'role_id' => $roleIds[(string) $assignment->role_id],
            ]);
        }

        return [$permissionIds, $roleIds];
    }

    /**
     * @param  array<string, int>  $userIds
     * @param  array<string, int>  $storeIds
     * @param  array<string, int>  $permissionIds
     * @param  array<string, int>  $roleIds
     */
    private function copyAuthorizationAssignments(array $userIds, array $storeIds, array $permissionIds, array $roleIds): void
    {
        foreach (DB::table('model_has_roles')->get() as $assignment) {
            DB::table('model_has_roles_next')->insertOrIgnore([
                'role_id' => $roleIds[(string) $assignment->role_id],
                'model_type' => $assignment->model_type,
                'model_id' => $userIds[(string) $assignment->model_id],
                'store_id' => $storeIds[(string) $assignment->tenant_id],
            ]);
        }

        foreach (DB::table('model_has_permissions')->get() as $assignment) {
            DB::table('model_has_permissions_next')->insertOrIgnore([
                'permission_id' => $permissionIds[(string) $assignment->permission_id],
                'model_type' => $assignment->model_type,
                'model_id' => $userIds[(string) $assignment->model_id],
                'store_id' => $storeIds[(string) $assignment->tenant_id],
            ]);
        }
    }

    /** @param array<string, int> $userIds @param array<string, int> $storeIds */
    private function copyTokens(array $userIds, array $storeIds): void
    {
        foreach (DB::table('personal_access_tokens')->get() as $token) {
            $abilities = json_decode((string) $token->abilities, true);
            if (is_array($abilities)) {
                $abilities = array_map(
                    fn (mixed $ability): mixed => $ability === 'tenant:access' ? 'store:access' : $ability,
                    $abilities,
                );
            }

            DB::table('personal_access_tokens_next')->insert([
                'public_id' => (string) Str::ulid(),
                'legacy_id' => $token->id,
                'tokenable_type' => $token->tokenable_type,
                'tokenable_id' => $userIds[(string) $token->tokenable_id],
                'store_id' => $token->tenant_id === null ? null : $storeIds[(string) $token->tenant_id],
                'name' => $token->name,
                'token' => $token->token,
                'abilities' => $abilities === null ? null : json_encode($abilities, JSON_THROW_ON_ERROR),
                'metadata' => $token->metadata,
                'last_used_at' => $token->last_used_at,
                'expires_at' => $token->expires_at,
                'created_at' => $token->created_at,
                'updated_at' => $token->updated_at,
            ]);
        }
    }

    /** @param array<string, int> $userIds */
    private function copySessions(array $userIds): void
    {
        foreach (DB::table('sessions')->get() as $session) {
            DB::table('sessions_next')->insert([
                'id' => $session->id,
                'user_id' => $session->user_id === null ? null : $userIds[(string) $session->user_id],
                'ip_address' => $session->ip_address,
                'user_agent' => $session->user_agent,
                'payload' => $session->payload,
                'last_activity' => $session->last_activity,
            ]);
        }
    }

    /** @param array<string, int> $userIds */
    private function copyNotifications(array $userIds): void
    {
        foreach (DB::table('notifications')->get() as $notification) {
            DB::table('notifications_next')->insert([
                'id' => $notification->id,
                'type' => $notification->type,
                'notifiable_id' => $userIds[(string) $notification->notifiable_id],
                'notifiable_type' => $notification->notifiable_type,
                'data' => $notification->data,
                'read_at' => $notification->read_at,
                'created_at' => $notification->created_at,
                'updated_at' => $notification->updated_at,
            ]);
        }
    }

    /** @param array<string, int> $userIds @param array<string, int> $storeIds */
    private function copyMedia(array $userIds, array $storeIds): void
    {
        foreach (DB::table('media')->get() as $media) {
            DB::table('media_next')->insert([
                'public_id' => (string) Str::ulid(),
                'store_id' => $storeIds[(string) $media->tenant_id],
                'model_type' => $media->model_type,
                'model_id' => $userIds[(string) $media->model_id],
                'uuid' => $media->uuid,
                'collection_name' => $media->collection_name,
                'name' => $media->name,
                'file_name' => $media->file_name,
                'mime_type' => $media->mime_type,
                'disk' => $media->disk,
                'conversions_disk' => $media->conversions_disk,
                'size' => $media->size,
                'manipulations' => $media->manipulations,
                'custom_properties' => $media->custom_properties,
                'generated_conversions' => $media->generated_conversions,
                'responsive_images' => $media->responsive_images,
                'order_column' => $media->order_column,
                'created_at' => $media->created_at,
                'updated_at' => $media->updated_at,
            ]);
        }
    }

    private function copyQueueRows(): void
    {
        foreach (DB::table('jobs')->get() as $job) {
            DB::table('jobs_next')->insert([
                'queue' => $job->queue,
                'payload' => $job->payload,
                'attempts' => $job->attempts,
                'reserved_at' => $job->reserved_at,
                'available_at' => $job->available_at,
                'created_at' => $job->created_at,
            ]);
        }

        foreach (DB::table('failed_jobs')->get() as $job) {
            DB::table('failed_jobs_next')->insert([
                'uuid' => $job->uuid,
                'connection' => $job->connection,
                'queue' => $job->queue,
                'payload' => $job->payload,
                'exception' => $job->exception,
                'failed_at' => $job->failed_at,
            ]);
        }
    }

    private function replaceLegacyTables(): void
    {
        foreach ([
            'role_has_permissions',
            'model_has_roles',
            'model_has_permissions',
            'personal_access_tokens',
            'media',
            'notifications',
            'sessions',
            'tenant_memberships',
            'roles',
            'permissions',
            'tenants',
            'users',
            'jobs',
            'failed_jobs',
        ] as $table) {
            Schema::drop($table);
        }

        foreach ([
            'users_next' => 'users',
            'stores_next' => 'stores',
            'store_memberships_next' => 'store_memberships',
            'permissions_next' => 'permissions',
            'roles_next' => 'roles',
            'model_has_permissions_next' => 'model_has_permissions',
            'model_has_roles_next' => 'model_has_roles',
            'role_has_permissions_next' => 'role_has_permissions',
            'personal_access_tokens_next' => 'personal_access_tokens',
            'sessions_next' => 'sessions',
            'notifications_next' => 'notifications',
            'media_next' => 'media',
            'jobs_next' => 'jobs',
            'failed_jobs_next' => 'failed_jobs',
        ] as $from => $to) {
            Schema::rename($from, $to);
        }
    }
};
