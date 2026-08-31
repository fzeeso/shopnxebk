<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_records', function (Blueprint $table): void {
            $table->id();
            $table->char('scope_hash', 64);
            $table->char('key_hash', 64);
            $table->unsignedSmallInteger('fingerprint_version');
            $table->char('request_fingerprint', 64);
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->cascadeOnDelete();
            $table->string('route_name', 190);
            $table->string('http_method', 8);
            $table->unsignedSmallInteger('response_status');
            $table->jsonb('response_headers');
            $table->text('response_body_ciphertext');
            $table->char('response_body_sha256', 64);
            $table->string('original_request_id', 128);
            $table->timestampTz('completed_at');
            $table->timestampTz('expires_at')->index();
            $table->timestampsTz();

            $table->unique(['scope_hash', 'key_hash'], 'idempotency_records_scope_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_records');
    }
};
