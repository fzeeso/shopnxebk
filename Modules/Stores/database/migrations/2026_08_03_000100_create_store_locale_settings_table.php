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
        Schema::create('store_locale_settings', function (Blueprint $table): void {
            $table->foreignId('store_id')->primary()->constrained('stores')->cascadeOnDelete();
            $table->string('date_format', 16)->default('Y-m-d');
            $table->string('time_format', 8)->default('24h');
            $table->string('week_starts_on', 16)->default('monday');
            $table->string('weight_unit', 8)->default('kg');
            $table->string('dimension_unit', 8)->default('cm');
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->string('decimal_separator', 8)->default('dot');
            $table->string('thousands_separator', 8)->default('comma');
            $table->timestampsTz();
        });

        $now = now();
        DB::table('stores')
            ->select(['id', 'settings'])
            ->orderBy('id')
            ->chunkById(500, function ($stores) use ($now): void {
                $storeIds = $stores->pluck('id');
                $weightUnits = Schema::hasTable('store_settings')
                    ? DB::table('store_settings')->whereIn('store_id', $storeIds)->pluck('weight_unit', 'store_id')
                    : collect();

                $rows = $stores->map(function (object $store) use ($now, $weightUnits): array {
                    $preferences = is_array($store->settings)
                        ? $store->settings
                        : json_decode((string) ($store->settings ?? '{}'), true);
                    $preferences = is_array($preferences) ? $preferences : [];

                    return [
                        'store_id' => $store->id,
                        'date_format' => in_array($preferences['date_format'] ?? null, ['Y-m-d', 'd/m/Y', 'm/d/Y'], true)
                            ? $preferences['date_format']
                            : 'Y-m-d',
                        'time_format' => in_array($preferences['time_format'] ?? null, ['12h', '24h'], true)
                            ? $preferences['time_format']
                            : '24h',
                        'week_starts_on' => 'monday',
                        'weight_unit' => in_array($weightUnits[$store->id] ?? $preferences['weight_unit'] ?? null, ['kg', 'lb'], true)
                            ? ($weightUnits[$store->id] ?? $preferences['weight_unit'])
                            : 'kg',
                        'dimension_unit' => in_array($preferences['dimension_unit'] ?? null, ['cm', 'in'], true)
                            ? $preferences['dimension_unit']
                            : 'cm',
                        'decimal_places' => 2,
                        'decimal_separator' => 'dot',
                        'thousands_separator' => 'comma',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                })->all();

                if ($rows !== []) {
                    DB::table('store_locale_settings')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_locale_settings');
    }
};
