<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Authentication\Enums\AccessScope;
use Modules\Authentication\Models\User;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'scope')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('scope')->default(AccessScope::Store->value)->index();
            });
        }

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_scope_check CHECK (scope IN ('platform', 'store'))");
        DB::statement("ALTER TABLE roles ADD CONSTRAINT roles_scope_check CHECK (scope IN ('platform', 'store'))");
        DB::statement("ALTER TABLE roles ADD CONSTRAINT roles_platform_store_check CHECK (scope <> 'platform' OR store_id IS NULL)");
        DB::statement("ALTER TABLE permissions ADD CONSTRAINT permissions_scope_check CHECK (scope IN ('platform', 'store'))");

        $this->classifyExistingPlatformUsers();
        $this->assertExistingAssignmentsAreExclusive();
        $this->createEnforcementTriggers();
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS enforce_personal_access_token_user_scope ON personal_access_tokens;
            DROP FUNCTION IF EXISTS enforce_personal_access_token_user_scope();
            DROP TRIGGER IF EXISTS enforce_direct_permission_user_scope ON model_has_permissions;
            DROP FUNCTION IF EXISTS enforce_direct_permission_user_scope();
            DROP TRIGGER IF EXISTS enforce_role_assignment_user_scope ON model_has_roles;
            DROP FUNCTION IF EXISTS enforce_role_assignment_user_scope();
            DROP TRIGGER IF EXISTS enforce_store_membership_user_scope ON store_memberships;
            DROP FUNCTION IF EXISTS enforce_store_membership_user_scope();
            DROP TRIGGER IF EXISTS enforce_user_scope_transition ON users;
            DROP FUNCTION IF EXISTS enforce_user_scope_transition();
            SQL);

        DB::statement('ALTER TABLE permissions DROP CONSTRAINT IF EXISTS permissions_scope_check');
        DB::statement('ALTER TABLE roles DROP CONSTRAINT IF EXISTS roles_platform_store_check');
        DB::statement('ALTER TABLE roles DROP CONSTRAINT IF EXISTS roles_scope_check');
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_scope_check');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('scope');
        });
    }

    private function classifyExistingPlatformUsers(): void
    {
        $roleUserIds = DB::table('model_has_roles as assignments')
            ->join('roles', 'roles.id', '=', 'assignments.role_id')
            ->where('assignments.model_type', User::class)
            ->whereNull('assignments.store_id')
            ->where('roles.scope', AccessScope::Platform->value)
            ->pluck('assignments.model_id');

        $permissionUserIds = DB::table('model_has_permissions as assignments')
            ->join('permissions', 'permissions.id', '=', 'assignments.permission_id')
            ->where('assignments.model_type', User::class)
            ->whereNull('assignments.store_id')
            ->where('permissions.scope', AccessScope::Platform->value)
            ->pluck('assignments.model_id');

        $platformUserIds = $roleUserIds->merge($permissionUserIds)->unique()->values();
        if ($platformUserIds->isNotEmpty()) {
            DB::table('users')->whereIn('id', $platformUserIds)->update(['scope' => AccessScope::Platform->value]);
        }
    }

    private function assertExistingAssignmentsAreExclusive(): void
    {
        $platformUsersHaveStoreMemberships = DB::table('store_memberships')
            ->join('users', 'users.id', '=', 'store_memberships.user_id')
            ->where('users.scope', AccessScope::Platform->value)
            ->exists();

        $assignmentsHaveMismatchedScopes = DB::table('model_has_roles as assignments')
            ->join('users', function ($join): void {
                $join->on('users.id', '=', 'assignments.model_id')
                    ->where('assignments.model_type', User::class);
            })
            ->join('roles', 'roles.id', '=', 'assignments.role_id')
            ->where(function ($query): void {
                $query
                    ->whereColumn('users.scope', '<>', 'roles.scope')
                    ->orWhere(function ($query): void {
                        $query->where('users.scope', AccessScope::Platform->value)->whereNotNull('assignments.store_id');
                    })
                    ->orWhere(function ($query): void {
                        $query->where('users.scope', AccessScope::Store->value)->whereNull('assignments.store_id');
                    });
            })
            ->exists();

        $directPermissionsHaveMismatchedScopes = DB::table('model_has_permissions as assignments')
            ->join('users', function ($join): void {
                $join->on('users.id', '=', 'assignments.model_id')
                    ->where('assignments.model_type', User::class);
            })
            ->join('permissions', 'permissions.id', '=', 'assignments.permission_id')
            ->where(function ($query): void {
                $query
                    ->whereColumn('users.scope', '<>', 'permissions.scope')
                    ->orWhere(function ($query): void {
                        $query->where('users.scope', AccessScope::Platform->value)->whereNotNull('assignments.store_id');
                    })
                    ->orWhere(function ($query): void {
                        $query->where('users.scope', AccessScope::Store->value)->whereNull('assignments.store_id');
                    });
            })
            ->exists();

        $storeAssignmentsLackActiveMembership = DB::table('model_has_roles as assignments')
            ->join('users', function ($join): void {
                $join->on('users.id', '=', 'assignments.model_id')
                    ->where('assignments.model_type', User::class);
            })
            ->join('roles', 'roles.id', '=', 'assignments.role_id')
            ->where('users.scope', AccessScope::Store->value)
            ->where(function ($query): void {
                $query->whereNull('assignments.store_id')
                    ->orWhere(function ($query): void {
                        $query->whereNotNull('roles.store_id')->whereColumn('roles.store_id', '<>', 'assignments.store_id');
                    })
                    ->orWhereNotExists(function ($query): void {
                        $query->selectRaw('1')
                            ->from('store_memberships as membership')
                            ->whereColumn('membership.user_id', 'assignments.model_id')
                            ->whereColumn('membership.store_id', 'assignments.store_id')
                            ->where('membership.status', 'active');
                    });
            })
            ->exists();

        $storeDirectPermissionsLackActiveMembership = DB::table('model_has_permissions as assignments')
            ->join('users', function ($join): void {
                $join->on('users.id', '=', 'assignments.model_id')
                    ->where('assignments.model_type', User::class);
            })
            ->where('users.scope', AccessScope::Store->value)
            ->where(function ($query): void {
                $query->whereNull('assignments.store_id')
                    ->orWhereNotExists(function ($query): void {
                        $query->selectRaw('1')
                            ->from('store_memberships as membership')
                            ->whereColumn('membership.user_id', 'assignments.model_id')
                            ->whereColumn('membership.store_id', 'assignments.store_id')
                            ->where('membership.status', 'active');
                    });
            })
            ->exists();

        $platformUsersHaveStoreTokens = DB::table('personal_access_tokens as tokens')
            ->join('users', function ($join): void {
                $join->on('users.id', '=', 'tokens.tokenable_id')
                    ->where('tokens.tokenable_type', User::class);
            })
            ->where('users.scope', AccessScope::Platform->value)
            ->whereNotNull('tokens.store_id')
            ->exists();

        if ($platformUsersHaveStoreMemberships
            || $assignmentsHaveMismatchedScopes
            || $directPermissionsHaveMismatchedScopes
            || $storeAssignmentsLackActiveMembership
            || $storeDirectPermissionsLackActiveMembership
            || $platformUsersHaveStoreTokens) {
            throw new RuntimeException('Existing users mix Platform and Store access. Resolve those assignments before applying exclusive user scopes.');
        }
    }

    private function createEnforcementTriggers(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION enforce_store_membership_user_scope() RETURNS trigger AS $$
            DECLARE account_scope text;
            BEGIN
                SELECT scope INTO account_scope FROM users WHERE id = NEW.user_id;
                IF account_scope IS DISTINCT FROM 'store' THEN
                    RAISE EXCEPTION 'Only Store-scoped users may have Store memberships.';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER enforce_store_membership_user_scope
            BEFORE INSERT OR UPDATE OF user_id ON store_memberships
            FOR EACH ROW EXECUTE FUNCTION enforce_store_membership_user_scope();

            CREATE OR REPLACE FUNCTION enforce_role_assignment_user_scope() RETURNS trigger AS $$
            DECLARE
                account_scope text;
                assigned_role_scope text;
                assigned_role_store_id bigint;
            BEGIN
                IF NEW.model_type <> 'Modules\Authentication\Models\User' THEN
                    RETURN NEW;
                END IF;

                SELECT scope INTO account_scope FROM users WHERE id = NEW.model_id;
                SELECT scope, store_id INTO assigned_role_scope, assigned_role_store_id FROM roles WHERE id = NEW.role_id;

                IF account_scope IS NULL OR assigned_role_scope IS NULL OR account_scope <> assigned_role_scope THEN
                    RAISE EXCEPTION 'User scope and role scope must match.';
                END IF;

                IF account_scope = 'platform' THEN
                    IF NEW.store_id IS NOT NULL OR assigned_role_store_id IS NOT NULL THEN
                        RAISE EXCEPTION 'Platform role assignments cannot have a Store.';
                    END IF;
                ELSE
                    IF NEW.store_id IS NULL THEN
                        RAISE EXCEPTION 'Store role assignments require a Store.';
                    END IF;
                    IF assigned_role_store_id IS NOT NULL AND assigned_role_store_id <> NEW.store_id THEN
                        RAISE EXCEPTION 'Store-specific role belongs to another Store.';
                    END IF;
                    IF NOT EXISTS (
                        SELECT 1 FROM store_memberships
                        WHERE user_id = NEW.model_id
                          AND store_id = NEW.store_id
                          AND status = 'active'
                    ) THEN
                        RAISE EXCEPTION 'Active Store membership is required before assigning a Store role.';
                    END IF;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER enforce_role_assignment_user_scope
            BEFORE INSERT OR UPDATE OF role_id, model_type, model_id, store_id ON model_has_roles
            FOR EACH ROW EXECUTE FUNCTION enforce_role_assignment_user_scope();

            CREATE OR REPLACE FUNCTION enforce_direct_permission_user_scope() RETURNS trigger AS $$
            DECLARE
                account_scope text;
                assigned_permission_scope text;
            BEGIN
                IF NEW.model_type <> 'Modules\Authentication\Models\User' THEN
                    RETURN NEW;
                END IF;

                SELECT scope INTO account_scope FROM users WHERE id = NEW.model_id;
                SELECT scope INTO assigned_permission_scope FROM permissions WHERE id = NEW.permission_id;

                IF account_scope IS NULL OR assigned_permission_scope IS NULL OR account_scope <> assigned_permission_scope THEN
                    RAISE EXCEPTION 'User scope and permission scope must match.';
                END IF;

                IF account_scope = 'platform' AND NEW.store_id IS NOT NULL THEN
                    RAISE EXCEPTION 'Platform permission assignments cannot have a Store.';
                END IF;

                IF account_scope = 'store' THEN
                    IF NEW.store_id IS NULL OR NOT EXISTS (
                        SELECT 1 FROM store_memberships
                        WHERE user_id = NEW.model_id
                          AND store_id = NEW.store_id
                          AND status = 'active'
                    ) THEN
                        RAISE EXCEPTION 'Store permissions require an active membership in the same Store.';
                    END IF;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER enforce_direct_permission_user_scope
            BEFORE INSERT OR UPDATE OF permission_id, model_type, model_id, store_id ON model_has_permissions
            FOR EACH ROW EXECUTE FUNCTION enforce_direct_permission_user_scope();

            CREATE OR REPLACE FUNCTION enforce_user_scope_transition() RETURNS trigger AS $$
            BEGIN
                IF NEW.scope = OLD.scope THEN
                    RETURN NEW;
                END IF;

                IF EXISTS (SELECT 1 FROM store_memberships WHERE user_id = NEW.id)
                   OR EXISTS (
                       SELECT 1 FROM model_has_roles
                       WHERE model_type = 'Modules\Authentication\Models\User' AND model_id = NEW.id
                   )
                   OR EXISTS (
                       SELECT 1 FROM model_has_permissions
                       WHERE model_type = 'Modules\Authentication\Models\User' AND model_id = NEW.id
                   )
                   OR EXISTS (
                       SELECT 1 FROM personal_access_tokens
                       WHERE tokenable_type = 'Modules\Authentication\Models\User'
                         AND tokenable_id = NEW.id
                         AND store_id IS NOT NULL
                   ) THEN
                    RAISE EXCEPTION 'Remove memberships, roles, and direct permissions before changing user scope.';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER enforce_user_scope_transition
            BEFORE UPDATE OF scope ON users
            FOR EACH ROW EXECUTE FUNCTION enforce_user_scope_transition();

            CREATE OR REPLACE FUNCTION enforce_personal_access_token_user_scope() RETURNS trigger AS $$
            DECLARE account_scope text;
            BEGIN
                IF NEW.store_id IS NULL OR NEW.tokenable_type <> 'Modules\Authentication\Models\User' THEN
                    RETURN NEW;
                END IF;

                SELECT scope INTO account_scope FROM users WHERE id = NEW.tokenable_id;
                IF account_scope IS DISTINCT FROM 'store' THEN
                    RAISE EXCEPTION 'Only Store-scoped users may have Store-bound tokens.';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER enforce_personal_access_token_user_scope
            BEFORE INSERT OR UPDATE OF store_id, tokenable_type, tokenable_id ON personal_access_tokens
            FOR EACH ROW EXECUTE FUNCTION enforce_personal_access_token_user_scope();
            SQL);
    }
};
