<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_item_modifier_selections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('cart_item_id');
            $table->unsignedBigInteger('product_modifier_assignment_id');
            $table->unsignedBigInteger('modifier_id');
            $table->unsignedBigInteger('modifier_value_id')->nullable();
            $table->jsonb('input_value')->nullable();
            $table->decimal('price_adjustment', 18, 4)->default(0);
            $table->char('currency_code', 3);
            $table->timestampsTz();
            $table->index('cart_item_id', 'cart_item_modifier_selections_cart_item_idx');
            $table->index(['store_id', 'cart_item_id'], 'cart_item_modifier_selections_store_cart_item_idx');
            $table->foreign('currency_code', 'cart_item_modifier_selections_currency_fk')->references('code')->on('currencies')->restrictOnDelete();
            $table->foreign(['product_modifier_assignment_id', 'modifier_id', 'store_id'], 'cart_item_modifier_selections_assignment_modifier_store_fk')
                ->references(['id', 'modifier_id', 'store_id'])->on('product_modifier_assignments')->cascadeOnDelete();
            $table->foreign(['modifier_value_id', 'modifier_id', 'store_id'], 'cart_item_modifier_selections_value_modifier_store_fk')
                ->references(['id', 'modifier_id', 'store_id'])->on('modifier_values')->cascadeOnDelete();
        });

        Schema::create('order_item_modifier_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('order_item_id');
            $table->unsignedBigInteger('modifier_id')->nullable();
            $table->unsignedBigInteger('modifier_value_id')->nullable();
            $table->char('modifier_public_id', 26)->nullable();
            $table->char('value_public_id', 26)->nullable();
            $table->string('modifier_code', 100);
            $table->string('modifier_name');
            $table->string('value_code', 100)->nullable();
            $table->string('value_name')->nullable();
            $table->jsonb('input_value')->nullable();
            $table->decimal('price_adjustment', 18, 4)->default(0);
            $table->char('currency_code', 3);
            $table->string('locale', 35);
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index('order_item_id', 'order_item_modifier_snapshots_order_item_idx');
            $table->index(['store_id', 'order_item_id'], 'order_item_modifier_snapshots_store_order_item_idx');
            $table->foreign('currency_code', 'order_item_modifier_snapshots_currency_fk')->references('code')->on('currencies')->restrictOnDelete();
            $table->foreign('modifier_id', 'order_item_modifier_snapshots_modifier_fk')->references('id')->on('modifier_definitions')->nullOnDelete();
            $table->foreign('modifier_value_id', 'order_item_modifier_snapshots_value_fk')->references('id')->on('modifier_values')->nullOnDelete();
        });

        if (Schema::hasTable('cart_items')) {
            Schema::table('cart_item_modifier_selections', function (Blueprint $table): void {
                $table->foreign('cart_item_id', 'cart_item_modifier_selections_cart_item_fk')
                    ->references('id')->on('cart_items')->cascadeOnDelete();
            });
        }
        if (Schema::hasTable('order_items')) {
            Schema::table('order_item_modifier_snapshots', function (Blueprint $table): void {
                $table->foreign('order_item_id', 'order_item_modifier_snapshots_order_item_fk')
                    ->references('id')->on('order_items')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_modifier_snapshots');
        Schema::dropIfExists('cart_item_modifier_selections');
    }
};
