<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE stores DROP CONSTRAINT IF EXISTS stores_status_check');
        DB::table('stores')->where('status', 'pending')->update(['status' => 'draft']);
        DB::table('stores')->where('status', 'cancelled')->update(['status' => 'closed']);
        DB::statement("ALTER TABLE stores ADD CONSTRAINT stores_status_check CHECK (status IN ('draft', 'trial', 'active', 'suspended', 'frozen', 'closed'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE stores DROP CONSTRAINT IF EXISTS stores_status_check');
        DB::table('stores')->whereIn('status', ['draft', 'trial'])->update(['status' => 'pending']);
        DB::table('stores')->where('status', 'frozen')->update(['status' => 'suspended']);
        DB::table('stores')->where('status', 'closed')->update(['status' => 'cancelled']);
        DB::statement("ALTER TABLE stores ADD CONSTRAINT stores_status_check CHECK (status IN ('pending', 'active', 'suspended', 'cancelled'))");
    }
};
