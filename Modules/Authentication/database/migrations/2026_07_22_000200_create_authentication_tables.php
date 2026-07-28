<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->uuid('legacy_id')->nullable()->unique();
            $table->string('tokenable_type');
            $table->unsignedBigInteger('tokenable_id');
            $table->foreignId('store_id')->nullable()->constrained('stores')->cascadeOnDelete();
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

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->string('scope')->index();
            $table->string('guard_name');
            $table->timestampsTz();
            $table->unique(['name', 'guard_name', 'scope']);
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->nullable()->constrained('stores')->cascadeOnDelete();
            $table->string('name');
            $table->string('scope')->index();
            $table->string('guard_name');
            $table->timestampsTz();
        });
        DB::statement('CREATE UNIQUE INDEX roles_global_name_guard_scope_unique ON roles (name, guard_name, scope) WHERE store_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX roles_store_name_guard_scope_unique ON roles (store_id, name, guard_name, scope) WHERE store_id IS NOT NULL');

        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->foreignId('store_id')->nullable()->constrained('stores')->cascadeOnDelete();
            $table->index(['model_id', 'model_type']);
        });
        DB::statement('CREATE UNIQUE INDEX model_permissions_global_unique ON model_has_permissions (permission_id, model_id, model_type) WHERE store_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX model_permissions_store_unique ON model_has_permissions (store_id, permission_id, model_id, model_type) WHERE store_id IS NOT NULL');

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->foreignId('store_id')->nullable()->constrained('stores')->cascadeOnDelete();
            $table->index(['model_id', 'model_type']);
        });
        DB::statement('CREATE UNIQUE INDEX model_roles_global_unique ON model_has_roles (role_id, model_id, model_type) WHERE store_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX model_roles_store_unique ON model_has_roles (store_id, role_id, model_id, model_type) WHERE store_id IS NOT NULL');

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->unique(['permission_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('personal_access_tokens');
    }
};
