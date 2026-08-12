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
        Schema::create('translation_requests', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('content_type', 80);
            $table->unsignedBigInteger('content_id');
            $table->string('source_locale', 35);
            $table->char('source_hash', 64);
            $table->char('request_hash', 64);
            $table->jsonb('target_locales');
            $table->string('status', 30)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('queued_at')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->unique(
                ['store_id', 'content_type', 'content_id', 'request_hash'],
                'translation_requests_content_hash_unique',
            );
            $table->index(['status', 'queued_at', 'created_at'], 'translation_requests_dispatch_idx');
            $table->index(
                ['store_id', 'content_type', 'content_id', 'created_at'],
                'translation_requests_content_idx',
            );
        });

        DB::statement("ALTER TABLE translation_requests ADD CONSTRAINT translation_requests_status_check CHECK (status IN ('pending', 'processing', 'completed', 'failed', 'superseded', 'cancelled'))");
        DB::statement("ALTER TABLE translation_requests ADD CONSTRAINT translation_requests_content_type_check CHECK (content_type ~ '^[a-z][a-z0-9_-]{0,79}$')");
        DB::statement("ALTER TABLE translation_requests ADD CONSTRAINT translation_requests_hash_check CHECK (source_hash ~ '^[a-f0-9]{64}$' AND request_hash ~ '^[a-f0-9]{64}$')");
        DB::statement("ALTER TABLE translation_requests ADD CONSTRAINT translation_requests_targets_check CHECK (jsonb_typeof(target_locales) = 'array')");
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_requests');
    }
};
