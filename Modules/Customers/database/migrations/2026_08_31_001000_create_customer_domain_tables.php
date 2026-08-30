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
        Schema::create('customer_groups', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->string('code', 100);
            $table->decimal('default_discount_percentage', 7, 4)->default(0);
            $table->string('discount_method', 100);
            $table->boolean('is_default')->default(false);
            $table->string('category_access_type', 20)->default('all');
            $table->timestampsTz();

            $table->unique(['id', 'store_id'], 'customer_groups_id_store_unique');
            $table->unique(['store_id', 'legacy_id'], 'customer_groups_store_legacy_unique');
            $table->index(['store_id', 'category_access_type'], 'customer_groups_store_category_access_idx');
        });

        Schema::create('customer_group_translations', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('customer_group_id');
            $table->foreignId('language_id')->constrained('languages')->restrictOnDelete();
            $table->string('name', 255);
            $table->boolean('lock_it')->default(false);
            $table->timestampsTz();

            $table->unique(['customer_group_id', 'language_id'], 'customer_group_translations_group_language_unique');
            $table->foreign(['customer_group_id', 'store_id'], 'customer_group_translations_group_store_fk')
                ->references(['id', 'store_id'])->on('customer_groups')->cascadeOnDelete();
        });

        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->unsignedBigInteger('customer_group_id')->nullable();
            $table->string('email', 320);
            $table->string('company')->nullable();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('status', 20)->default('active');
            $table->string('password')->nullable();
            $table->string('legacy_password_hash', 100)->nullable();
            $table->string('legacy_password_salt', 64)->nullable();
            $table->string('legacy_import_password_hash', 100)->nullable();
            $table->string('registered_ip', 45)->nullable();
            $table->text('admin_notes')->nullable();
            $table->bigInteger('points_balance')->default(0);
            $table->bigInteger('redeemed_points')->default(0);
            $table->timestampTz('email_verified_at')->nullable();
            $table->timestampTz('joined_at')->useCurrent();
            $table->timestampTz('last_activity_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['id', 'store_id'], 'customers_id_store_unique');
            $table->unique(['store_id', 'legacy_id'], 'customers_store_legacy_unique');
            $table->index(['store_id', 'status', 'joined_at'], 'customers_store_status_joined_idx');
        });

        Schema::create('customer_credits', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->decimal('amount', 20, 4);
            $table->string('type', 20);
            $table->string('external_reference', 120)->nullable();
            $table->unsignedBigInteger('legacy_reference_id')->nullable();
            $table->unsignedBigInteger('legacy_user_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 200);
            $table->timestampTz('occurred_at');
            $table->timestampsTz();

            $table->unique(['id', 'store_id'], 'customer_credits_id_store_unique');
            $table->unique(['store_id', 'legacy_id'], 'customer_credits_store_legacy_unique');
            $table->index(['store_id', 'customer_id', 'occurred_at'], 'customer_credits_store_customer_occurred_idx');
            $table->foreign(['customer_id', 'store_id'], 'customer_credits_customer_store_fk')
                ->references(['id', 'store_id'])->on('customers')->cascadeOnDelete();
        });

        Schema::create('customer_group_categories', function (Blueprint $table): void {
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('customer_group_id');
            $table->unsignedBigInteger('category_id');
            $table->timestampsTz();

            $table->primary(['customer_group_id', 'category_id']);
            $table->foreign(['customer_group_id', 'store_id'], 'customer_group_categories_group_store_fk')
                ->references(['id', 'store_id'])->on('customer_groups')->cascadeOnDelete();
            $table->foreign(['category_id', 'store_id'], 'customer_group_categories_category_store_fk')
                ->references(['id', 'store_id'])->on('categories')->cascadeOnDelete();
        });

        Schema::create('customer_group_discounts', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('customer_group_id');
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->string('target_type', 20);
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->decimal('discount_percentage', 7, 4);
            $table->string('applies_to', 32);
            $table->string('discount_method', 100);
            $table->timestampsTz();

            $table->unique(['id', 'store_id'], 'customer_group_discounts_id_store_unique');
            $table->unique(['store_id', 'legacy_id'], 'customer_group_discounts_store_legacy_unique');
            $table->index(['store_id', 'customer_group_id', 'target_type'], 'customer_group_discounts_store_group_target_idx');
            $table->foreign(['customer_group_id', 'store_id'], 'customer_group_discounts_group_store_fk')
                ->references(['id', 'store_id'])->on('customer_groups')->cascadeOnDelete();
            $table->foreign(['category_id', 'store_id'], 'customer_group_discounts_category_store_fk')
                ->references(['id', 'store_id'])->on('categories')->cascadeOnDelete();
            $table->foreign(['product_id', 'store_id'], 'customer_group_discounts_product_store_fk')
                ->references(['id', 'store_id'])->on('products')->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE customer_groups ADD CONSTRAINT customer_groups_discount_check CHECK (default_discount_percentage BETWEEN 0 AND 100)');
        DB::statement("ALTER TABLE customer_groups ADD CONSTRAINT customer_groups_category_access_check CHECK (category_access_type IN ('none', 'all', 'specific'))");
        DB::statement("ALTER TABLE customer_groups ADD CONSTRAINT customer_groups_code_check CHECK (BTRIM(code) <> '')");
        DB::statement('CREATE UNIQUE INDEX customer_groups_store_code_unique ON customer_groups (store_id, LOWER(code))');
        DB::statement('CREATE UNIQUE INDEX customer_groups_one_default_per_store ON customer_groups (store_id) WHERE is_default = true');
        DB::statement("ALTER TABLE customer_group_translations ADD CONSTRAINT customer_group_translations_name_check CHECK (BTRIM(name) <> '')");
        DB::statement("ALTER TABLE customers ADD CONSTRAINT customers_status_check CHECK (status IN ('active', 'disabled'))");
        DB::statement('ALTER TABLE customers ADD CONSTRAINT customers_points_check CHECK (points_balance >= 0 AND redeemed_points >= 0)');
        DB::statement("ALTER TABLE customers ADD CONSTRAINT customers_email_check CHECK (email = LOWER(BTRIM(email)) AND email <> '')");
        DB::statement('CREATE UNIQUE INDEX customers_store_email_unique ON customers (store_id, LOWER(email)) WHERE deleted_at IS NULL');
        DB::statement("ALTER TABLE customer_credits ADD CONSTRAINT customer_credits_type_check CHECK (type IN ('return', 'gift', 'adjustment'))");
        DB::statement('ALTER TABLE customer_credits ADD CONSTRAINT customer_credits_amount_check CHECK (amount <> 0)');
        DB::statement('ALTER TABLE customer_group_discounts ADD CONSTRAINT customer_group_discounts_percentage_check CHECK (discount_percentage BETWEEN 0 AND 100)');
        DB::statement("ALTER TABLE customer_group_discounts ADD CONSTRAINT customer_group_discounts_target_check CHECK ((target_type = 'category' AND category_id IS NOT NULL AND product_id IS NULL AND applies_to IN ('category_only', 'category_and_descendants')) OR (target_type = 'product' AND category_id IS NULL AND product_id IS NOT NULL AND applies_to = 'not_applicable'))");
        DB::statement("CREATE UNIQUE INDEX customer_group_discounts_category_unique ON customer_group_discounts (customer_group_id, category_id) WHERE target_type = 'category'");
        DB::statement("CREATE UNIQUE INDEX customer_group_discounts_product_unique ON customer_group_discounts (customer_group_id, product_id) WHERE target_type = 'product'");
        DB::statement('ALTER TABLE customers ADD CONSTRAINT customers_group_store_fk FOREIGN KEY (customer_group_id, store_id) REFERENCES customer_groups (id, store_id) ON DELETE SET NULL (customer_group_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_group_discounts');
        Schema::dropIfExists('customer_group_categories');
        Schema::dropIfExists('customer_credits');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('customer_group_translations');
        Schema::dropIfExists('customer_groups');
    }
};
