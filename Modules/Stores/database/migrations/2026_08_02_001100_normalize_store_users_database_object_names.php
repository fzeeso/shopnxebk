<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'store_memberships_next_pkey' => 'store_users_pkey',
            'store_memberships_pkey' => 'store_users_pkey',
            'store_memberships_next_public_id_unique' => 'store_users_public_id_unique',
            'store_memberships_public_id_unique' => 'store_users_public_id_unique',
            'store_memberships_next_store_id_user_id_unique' => 'store_users_store_id_user_id_unique',
            'store_memberships_store_id_user_id_unique' => 'store_users_store_id_user_id_unique',
            'store_memberships_next_store_id_foreign' => 'store_users_store_id_foreign',
            'store_memberships_store_id_foreign' => 'store_users_store_id_foreign',
            'store_memberships_next_user_id_foreign' => 'store_users_user_id_foreign',
            'store_memberships_user_id_foreign' => 'store_users_user_id_foreign',
        ] as $from => $to) {
            $this->renameConstraint($from, $to);
        }

        foreach ([
            'store_memberships_next_status_index' => 'store_users_status_index',
            'store_memberships_status_index' => 'store_users_status_index',
            'store_memberships_next_user_id_status_index' => 'store_users_user_id_status_index',
            'store_memberships_user_id_status_index' => 'store_users_user_id_status_index',
        ] as $from => $to) {
            $this->renameRelation('INDEX', $from, $to);
        }

        foreach ([
            'store_memberships_next_id_seq' => 'store_users_id_seq',
            'store_memberships_id_seq' => 'store_users_id_seq',
        ] as $from => $to) {
            $this->renameRelation('SEQUENCE', $from, $to);
        }
    }

    public function down(): void
    {
        foreach ([
            'store_users_pkey' => 'store_memberships_pkey',
            'store_users_public_id_unique' => 'store_memberships_public_id_unique',
            'store_users_store_id_user_id_unique' => 'store_memberships_store_id_user_id_unique',
            'store_users_store_id_foreign' => 'store_memberships_store_id_foreign',
            'store_users_user_id_foreign' => 'store_memberships_user_id_foreign',
        ] as $from => $to) {
            $this->renameConstraint($from, $to);
        }

        foreach ([
            'store_users_status_index' => 'store_memberships_status_index',
            'store_users_user_id_status_index' => 'store_memberships_user_id_status_index',
        ] as $from => $to) {
            $this->renameRelation('INDEX', $from, $to);
        }

        foreach ([
            'store_users_id_seq' => 'store_memberships_id_seq',
        ] as $from => $to) {
            $this->renameRelation('SEQUENCE', $from, $to);
        }
    }

    private function renameConstraint(string $from, string $to): void
    {
        $sourceExists = DB::table('pg_constraint')
            ->where('conrelid', DB::raw("'store_users'::regclass"))
            ->where('conname', $from)
            ->exists();
        $targetExists = DB::table('pg_constraint')
            ->where('conrelid', DB::raw("'store_users'::regclass"))
            ->where('conname', $to)
            ->exists();

        if ($sourceExists && ! $targetExists) {
            DB::statement(sprintf('ALTER TABLE store_users RENAME CONSTRAINT "%s" TO "%s"', $from, $to));
        }
    }

    private function renameRelation(string $type, string $from, string $to): void
    {
        $sourceExists = DB::table('pg_class')->where('relname', $from)->exists();
        $targetExists = DB::table('pg_class')->where('relname', $to)->exists();

        if ($sourceExists && ! $targetExists) {
            DB::statement(sprintf('ALTER %s "%s" RENAME TO "%s"', $type, $from, $to));
        }
    }
};
