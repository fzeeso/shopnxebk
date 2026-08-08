<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Stores\Actions\EnsureStorePolicyCatalog;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE store_policies DROP CONSTRAINT store_policies_status_check');
        DB::statement('ALTER TABLE store_policies DROP CONSTRAINT store_policies_publication_check');
        DB::statement("ALTER TABLE store_policies ADD CONSTRAINT store_policies_status_check CHECK (status IN ('disabled', 'draft', 'published'))");
        DB::statement("ALTER TABLE store_policies ADD CONSTRAINT store_policies_publication_check CHECK ((status = 'published' AND published_at IS NOT NULL) OR (status IN ('disabled', 'draft') AND published_at IS NULL))");

        app(EnsureStorePolicyCatalog::class)->ensureForAllStores();
    }

    public function down(): void
    {
        DB::table('store_policies')->where('status', 'disabled')->update([
            'status' => 'draft',
            'published_at' => null,
        ]);

        DB::statement('ALTER TABLE store_policies DROP CONSTRAINT store_policies_status_check');
        DB::statement('ALTER TABLE store_policies DROP CONSTRAINT store_policies_publication_check');
        DB::statement("ALTER TABLE store_policies ADD CONSTRAINT store_policies_status_check CHECK (status IN ('draft', 'published'))");
        DB::statement("ALTER TABLE store_policies ADD CONSTRAINT store_policies_publication_check CHECK ((status = 'published' AND published_at IS NOT NULL) OR (status = 'draft' AND published_at IS NULL))");
    }
};
