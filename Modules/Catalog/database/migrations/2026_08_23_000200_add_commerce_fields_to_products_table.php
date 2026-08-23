<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('sku', 250)->default('');
            $table->string('downloadfile', 250)->default('');
            $table->string('availability', 250)->default('');
            $table->decimal('price', 20, 4)->default(0);
            $table->decimal('costprice', 20, 4)->default(0);
            $table->decimal('retailprice', 20, 4)->default(0);
            $table->decimal('msrpprice', 20, 4)->default(0);
            $table->decimal('saleprice', 20, 4)->default(0);
            $table->decimal('calculatedprice', 20, 4)->default(0);
            $table->integer('sortorder')->default(0);
            $table->tinyInteger('is_featured')->default(0);
            $table->integer('currentinv')->default(0);
            $table->integer('lowinv')->default(0);
            $table->text('warranty')->nullable();
            $table->decimal('weight', 20, 4)->default(0);
            $table->decimal('width', 20, 4)->default(0);
            $table->decimal('height', 20, 4)->default(0);
            $table->decimal('proddepth', 20, 4)->default(0);
            $table->decimal('fixedshippingcost', 20, 4)->default(0);
            $table->tinyInteger('freeshipping')->default(0);
            $table->integer('ratingtotal')->default(0);
            $table->integer('numratings')->default(0);
            $table->integer('numsold')->default(0);
            $table->integer('numviews')->default(0);
            $table->integer('allowpurchases')->default(1);
            $table->integer('hideprice')->default(0);
            $table->integer('is_login_for_price')->default(0);
            $table->integer('is_global_search')->default(0);
            $table->enum('condition', ['New', 'Used', 'Refurbished'])->default('New');
            $table->unsignedTinyInteger('showcondition')->default(0);
            $table->unsignedTinyInteger('pre_order')->default(0);
            $table->timestampTz('releasedate')->nullable();
            $table->unsignedTinyInteger('releasedateremove')->default(0);
            $table->unsignedInteger('minqty')->default(0);
            $table->unsignedInteger('maxqty')->default(0);
            $table->unsignedInteger('tax_class_id')->default(0);
            $table->string('upc', 32)->nullable()->default('');
            $table->string('hs_code', 32)->nullable()->default('');
            $table->string('gtin', 32)->nullable()->default('');
            $table->string('mpn', 32)->nullable()->default('');
            $table->string('bpn', 32)->nullable()->default('');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn([
                'sku',
                'downloadfile',
                'availability',
                'price',
                'costprice',
                'retailprice',
                'msrpprice',
                'saleprice',
                'calculatedprice',
                'sortorder',
                'is_featured',
                'currentinv',
                'lowinv',
                'warranty',
                'weight',
                'width',
                'height',
                'proddepth',
                'fixedshippingcost',
                'freeshipping',
                'ratingtotal',
                'numratings',
                'numsold',
                'numviews',
                'allowpurchases',
                'hideprice',
                'is_login_for_price',
                'is_global_search',
                'condition',
                'showcondition',
                'pre_order',
                'releasedate',
                'releasedateremove',
                'minqty',
                'maxqty',
                'tax_class_id',
                'upc',
                'hs_code',
                'gtin',
                'mpn',
                'bpn',
            ]);
        });
    }
};
