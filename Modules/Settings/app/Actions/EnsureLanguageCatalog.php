<?php

declare(strict_types=1);

namespace Modules\Settings\Actions;

use Modules\Settings\Models\Language;

final class EnsureLanguageCatalog
{
    /** @var list<array{name: string, native_name: string, locale: string, lang_icon: string, direction: string}> */
    private const LANGUAGES = [
        ['name' => 'English', 'native_name' => 'English', 'locale' => 'en', 'lang_icon' => '/assets/languages/flags/gb.svg', 'direction' => 'ltr'],
        ['name' => 'Arabic', 'native_name' => 'العربية', 'locale' => 'ar', 'lang_icon' => '/assets/languages/flags/sa.svg', 'direction' => 'rtl'],
        ['name' => 'Chinese Simplified', 'native_name' => '简体中文', 'locale' => 'zh_CN', 'lang_icon' => '/assets/languages/flags/cn.svg', 'direction' => 'ltr'],
        ['name' => 'Chinese Traditional', 'native_name' => '繁體中文', 'locale' => 'zh_TW', 'lang_icon' => '/assets/languages/flags/tw.svg', 'direction' => 'ltr'],
        ['name' => 'Czech', 'native_name' => 'Čeština', 'locale' => 'cs', 'lang_icon' => '/assets/languages/flags/cz.svg', 'direction' => 'ltr'],
        ['name' => 'Danish', 'native_name' => 'Dansk', 'locale' => 'da', 'lang_icon' => '/assets/languages/flags/dk.svg', 'direction' => 'ltr'],
        ['name' => 'Dutch', 'native_name' => 'Nederlands', 'locale' => 'nl', 'lang_icon' => '/assets/languages/flags/nl.svg', 'direction' => 'ltr'],
        ['name' => 'Finnish', 'native_name' => 'Suomi', 'locale' => 'fi', 'lang_icon' => '/assets/languages/flags/fi.svg', 'direction' => 'ltr'],
        ['name' => 'French', 'native_name' => 'Français', 'locale' => 'fr', 'lang_icon' => '/assets/languages/flags/fr.svg', 'direction' => 'ltr'],
        ['name' => 'German', 'native_name' => 'Deutsch', 'locale' => 'de', 'lang_icon' => '/assets/languages/flags/de.svg', 'direction' => 'ltr'],
        ['name' => 'Hindi', 'native_name' => 'हिन्दी', 'locale' => 'hi', 'lang_icon' => '/assets/languages/flags/in.svg', 'direction' => 'ltr'],
        ['name' => 'Italian', 'native_name' => 'Italiano', 'locale' => 'it', 'lang_icon' => '/assets/languages/flags/it.svg', 'direction' => 'ltr'],
        ['name' => 'Japanese', 'native_name' => '日本語', 'locale' => 'ja', 'lang_icon' => '/assets/languages/flags/jp.svg', 'direction' => 'ltr'],
        ['name' => 'Korean', 'native_name' => '한국어', 'locale' => 'ko', 'lang_icon' => '/assets/languages/flags/kr.svg', 'direction' => 'ltr'],
        ['name' => 'Norwegian Bokmål', 'native_name' => 'Norsk Bokmål', 'locale' => 'nb', 'lang_icon' => '/assets/languages/flags/no.svg', 'direction' => 'ltr'],
        ['name' => 'Persian', 'native_name' => 'فارسی', 'locale' => 'fa', 'lang_icon' => '/assets/languages/flags/ir.svg', 'direction' => 'rtl'],
        ['name' => 'Polish', 'native_name' => 'Polski', 'locale' => 'pl', 'lang_icon' => '/assets/languages/flags/pl.svg', 'direction' => 'ltr'],
        ['name' => 'Portuguese (Brazil)', 'native_name' => 'Português (Brasil)', 'locale' => 'pt_BR', 'lang_icon' => '/assets/languages/flags/br.svg', 'direction' => 'ltr'],
        ['name' => 'Portuguese (Portugal)', 'native_name' => 'Português (Portugal)', 'locale' => 'pt_PT', 'lang_icon' => '/assets/languages/flags/pt.svg', 'direction' => 'ltr'],
        ['name' => 'Spanish', 'native_name' => 'Español', 'locale' => 'es', 'lang_icon' => '/assets/languages/flags/es.svg', 'direction' => 'ltr'],
        ['name' => 'Swedish', 'native_name' => 'Svenska', 'locale' => 'sv', 'lang_icon' => '/assets/languages/flags/se.svg', 'direction' => 'ltr'],
        ['name' => 'Thai', 'native_name' => 'ไทย', 'locale' => 'th', 'lang_icon' => '/assets/languages/flags/th.svg', 'direction' => 'ltr'],
        ['name' => 'Turkish', 'native_name' => 'Türkçe', 'locale' => 'tr', 'lang_icon' => '/assets/languages/flags/tr.svg', 'direction' => 'ltr'],
        ['name' => 'Urdu', 'native_name' => 'اردو', 'locale' => 'ur', 'lang_icon' => '/assets/languages/flags/pk.svg', 'direction' => 'rtl'],
    ];

    public function ensure(): void
    {
        foreach (self::LANGUAGES as $language) {
            Language::query()->updateOrCreate(
                ['locale' => $language['locale']],
                [...$language, 'is_active' => true],
            );
        }
    }
}
