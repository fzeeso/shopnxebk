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
        Schema::create('currencies', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name', 100);
            $table->char('code', 3)->unique();
            $table->string('symbol', 16);
            $table->enum('symbol_position', ['before', 'after'])->default('before');
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->decimal('usd_exchange_rate', 20, 8)->nullable();
            $table->boolean('is_base')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestampTz('exchange_rate_updated_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement(
            'CREATE UNIQUE INDEX currencies_one_base_currency '
            .'ON currencies (is_base) WHERE is_base = true',
        );
        DB::statement(
            'ALTER TABLE currencies ADD CONSTRAINT currencies_positive_usd_rate '
            .'CHECK (usd_exchange_rate IS NULL OR usd_exchange_rate > 0)',
        );
        DB::statement(
            'ALTER TABLE currencies ADD CONSTRAINT currencies_base_is_usd '
            ."CHECK (is_base = false OR (code = 'USD' AND usd_exchange_rate = 1 AND is_active = true))",
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
