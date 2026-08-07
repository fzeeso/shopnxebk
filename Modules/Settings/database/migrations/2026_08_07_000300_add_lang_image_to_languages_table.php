<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DEFAULT_IMAGE = '/assets/languages/flags/generic.svg';

    /** @var array<string, string> */
    private const IMAGES_BY_LOCALE = [
        'en' => '/assets/languages/images/gb.webp',
        'ar' => '/assets/languages/images/sa.webp',
        'zh_CN' => '/assets/languages/images/cn.webp',
        'zh_TW' => '/assets/languages/flags/tw.svg',
        'cs' => '/assets/languages/flags/cz.svg',
        'da' => '/assets/languages/images/dk.webp',
        'nl' => '/assets/languages/images/nl.webp',
        'fi' => '/assets/languages/images/fi.webp',
        'fr' => '/assets/languages/images/fr.webp',
        'de' => '/assets/languages/images/de.webp',
        'hi' => '/assets/languages/images/in.webp',
        'it' => '/assets/languages/images/it.webp',
        'ja' => '/assets/languages/images/jp.webp',
        'ko' => '/assets/languages/images/kr.webp',
        'nb' => '/assets/languages/flags/no.svg',
        'fa' => '/assets/languages/flags/ir.svg',
        'pl' => '/assets/languages/flags/pl.svg',
        'pt_BR' => '/assets/languages/images/br.webp',
        'pt_PT' => '/assets/languages/images/pt.webp',
        'es' => '/assets/languages/images/es.webp',
        'sv' => '/assets/languages/images/se.webp',
        'th' => '/assets/languages/flags/th.svg',
        'tr' => '/assets/languages/images/tr.webp',
        'ur' => '/assets/languages/flags/pk.svg',
    ];

    public function up(): void
    {
        Schema::table('languages', function (Blueprint $table): void {
            $table->string('lang_image', 2048)
                ->default(self::DEFAULT_IMAGE)
                ->after('lang_icon');
        });

        foreach (self::IMAGES_BY_LOCALE as $locale => $langImage) {
            DB::table('languages')
                ->where('locale', $locale)
                ->update(['lang_image' => $langImage]);
        }
    }

    public function down(): void
    {
        Schema::table('languages', function (Blueprint $table): void {
            $table->dropColumn('lang_image');
        });
    }
};
