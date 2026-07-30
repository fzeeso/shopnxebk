<?php

declare(strict_types=1);

namespace Modules\Settings\Actions;

use Modules\Settings\Models\Language;

final class EnsureLanguageCatalog
{
    /** @var list<array{name: string, native_name: string, locale: string, direction: string}> */
    private const LANGUAGES = [
        ['name' => 'English', 'native_name' => 'English', 'locale' => 'en', 'direction' => 'ltr'],
        ['name' => 'Arabic', 'native_name' => 'العربية', 'locale' => 'ar', 'direction' => 'rtl'],
        ['name' => 'Chinese Simplified', 'native_name' => '简体中文', 'locale' => 'zh_CN', 'direction' => 'ltr'],
        ['name' => 'Chinese Traditional', 'native_name' => '繁體中文', 'locale' => 'zh_TW', 'direction' => 'ltr'],
        ['name' => 'Czech', 'native_name' => 'Čeština', 'locale' => 'cs', 'direction' => 'ltr'],
        ['name' => 'Danish', 'native_name' => 'Dansk', 'locale' => 'da', 'direction' => 'ltr'],
        ['name' => 'Dutch', 'native_name' => 'Nederlands', 'locale' => 'nl', 'direction' => 'ltr'],
        ['name' => 'Finnish', 'native_name' => 'Suomi', 'locale' => 'fi', 'direction' => 'ltr'],
        ['name' => 'French', 'native_name' => 'Français', 'locale' => 'fr', 'direction' => 'ltr'],
        ['name' => 'German', 'native_name' => 'Deutsch', 'locale' => 'de', 'direction' => 'ltr'],
        ['name' => 'Hindi', 'native_name' => 'हिन्दी', 'locale' => 'hi', 'direction' => 'ltr'],
        ['name' => 'Italian', 'native_name' => 'Italiano', 'locale' => 'it', 'direction' => 'ltr'],
        ['name' => 'Japanese', 'native_name' => '日本語', 'locale' => 'ja', 'direction' => 'ltr'],
        ['name' => 'Korean', 'native_name' => '한국어', 'locale' => 'ko', 'direction' => 'ltr'],
        ['name' => 'Norwegian Bokmål', 'native_name' => 'Norsk Bokmål', 'locale' => 'nb', 'direction' => 'ltr'],
        ['name' => 'Persian', 'native_name' => 'فارسی', 'locale' => 'fa', 'direction' => 'rtl'],
        ['name' => 'Polish', 'native_name' => 'Polski', 'locale' => 'pl', 'direction' => 'ltr'],
        ['name' => 'Portuguese (Brazil)', 'native_name' => 'Português (Brasil)', 'locale' => 'pt_BR', 'direction' => 'ltr'],
        ['name' => 'Portuguese (Portugal)', 'native_name' => 'Português (Portugal)', 'locale' => 'pt_PT', 'direction' => 'ltr'],
        ['name' => 'Spanish', 'native_name' => 'Español', 'locale' => 'es', 'direction' => 'ltr'],
        ['name' => 'Swedish', 'native_name' => 'Svenska', 'locale' => 'sv', 'direction' => 'ltr'],
        ['name' => 'Thai', 'native_name' => 'ไทย', 'locale' => 'th', 'direction' => 'ltr'],
        ['name' => 'Turkish', 'native_name' => 'Türkçe', 'locale' => 'tr', 'direction' => 'ltr'],
        ['name' => 'Urdu', 'native_name' => 'اردو', 'locale' => 'ur', 'direction' => 'rtl'],
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
