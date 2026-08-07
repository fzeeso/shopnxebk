<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DEFAULT_ICON = '/assets/languages/flags/generic.svg';

    /** @var array<string, string> */
    private const ICONS_BY_LOCALE = [
        'en' => '/assets/languages/flags/gb.svg',
        'ar' => '/assets/languages/flags/sa.svg',
        'zh_CN' => '/assets/languages/flags/cn.svg',
        'zh_TW' => '/assets/languages/flags/tw.svg',
        'cs' => '/assets/languages/flags/cz.svg',
        'da' => '/assets/languages/flags/dk.svg',
        'nl' => '/assets/languages/flags/nl.svg',
        'fi' => '/assets/languages/flags/fi.svg',
        'fr' => '/assets/languages/flags/fr.svg',
        'de' => '/assets/languages/flags/de.svg',
        'hi' => '/assets/languages/flags/in.svg',
        'it' => '/assets/languages/flags/it.svg',
        'ja' => '/assets/languages/flags/jp.svg',
        'ko' => '/assets/languages/flags/kr.svg',
        'nb' => '/assets/languages/flags/no.svg',
        'fa' => '/assets/languages/flags/ir.svg',
        'pl' => '/assets/languages/flags/pl.svg',
        'pt_BR' => '/assets/languages/flags/br.svg',
        'pt_PT' => '/assets/languages/flags/pt.svg',
        'es' => '/assets/languages/flags/es.svg',
        'sv' => '/assets/languages/flags/se.svg',
        'th' => '/assets/languages/flags/th.svg',
        'tr' => '/assets/languages/flags/tr.svg',
        'ur' => '/assets/languages/flags/pk.svg',
    ];

    public function up(): void
    {
        Schema::table('languages', function (Blueprint $table): void {
            $table->string('lang_icon', 2048)
                ->default(self::DEFAULT_ICON)
                ->after('locale');
        });

        foreach (self::ICONS_BY_LOCALE as $locale => $langIcon) {
            DB::table('languages')
                ->where('locale', $locale)
                ->update(['lang_icon' => $langIcon]);
        }
    }

    public function down(): void
    {
        Schema::table('languages', function (Blueprint $table): void {
            $table->dropColumn('lang_icon');
        });
    }
};
