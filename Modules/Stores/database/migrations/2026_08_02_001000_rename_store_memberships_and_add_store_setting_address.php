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
        Schema::rename('store_memberships', 'store_users');

        Schema::table('store_settings', function (Blueprint $table): void {
            $table->char('store_country_code', 2)->nullable();
            $table->string('store_state', 120)->nullable();
            $table->string('store_city', 120)->nullable();
            $table->string('store_zip', 32)->nullable();
            $table->string('store_address_1')->nullable();
            $table->string('store_address_2')->nullable();
        });

        $this->pointAuthorizationFunctionsAt('store_users');
    }

    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'store_country_code',
                'store_state',
                'store_city',
                'store_zip',
                'store_address_1',
                'store_address_2',
            ]);
        });

        Schema::rename('store_users', 'store_memberships');
        $this->pointAuthorizationFunctionsAt('store_memberships');
    }

    private function pointAuthorizationFunctionsAt(string $table): void
    {
        $sql = str_replace('__STORE_USERS_TABLE__', $table, <<<'SQL'
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
                        SELECT 1 FROM __STORE_USERS_TABLE__
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
                        SELECT 1 FROM __STORE_USERS_TABLE__
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

            CREATE OR REPLACE FUNCTION enforce_user_scope_transition() RETURNS trigger AS $$
            BEGIN
                IF NEW.scope = OLD.scope THEN
                    RETURN NEW;
                END IF;

                IF EXISTS (SELECT 1 FROM __STORE_USERS_TABLE__ WHERE user_id = NEW.id)
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
            SQL);

        DB::unprepared($sql);
    }
};
