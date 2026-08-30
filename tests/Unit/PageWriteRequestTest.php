<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Modules\Stores\Http\Requests\CreatePageRequest;
use PHPUnit\Framework\TestCase;

final class PageWriteRequestTest extends TestCase
{
    public function test_create_contract_accepts_unicode_slugs_and_multiple_languages(): void
    {
        $payload = [
            'page_type' => 'content',
            'translations' => [
                [
                    'language_id' => '01K4Z1BMX5A1F8QMCV1A8Z5E21',
                    'title' => 'About us',
                    'slug' => 'about-us',
                    'content' => 'About the Store.',
                ],
                [
                    'language_id' => '01K4Z1BMX5A1F8QMCV1A8Z5E22',
                    'title' => 'ہمارے بارے میں',
                    'slug' => 'ہمارے-بارے-میں',
                    'content' => 'اسٹور کے بارے میں۔',
                ],
            ],
        ];

        self::assertTrue($this->validator($payload)->passes());
    }

    public function test_create_contract_rejects_duplicate_languages_and_unsafe_slugs(): void
    {
        $language = '01K4Z1BMX5A1F8QMCV1A8Z5E21';
        $payload = [
            'page_type' => 'content',
            'translations' => [
                ['language_id' => $language, 'title' => 'One', 'slug' => 'valid-slug'],
                ['language_id' => $language, 'title' => 'Two', 'slug' => 'unsafe/slug'],
            ],
        ];
        $validator = $this->validator($payload);

        self::assertTrue($validator->fails());
        self::assertTrue($validator->errors()->has('translations.1.language_id'));
        self::assertTrue($validator->errors()->has('translations.1.slug'));
    }

    /** @param array<string, mixed> $payload */
    private function validator(array $payload): Validator
    {
        $request = CreatePageRequest::create('/api/v1/store/pages', 'POST', $payload);
        $factory = new Factory(new Translator(new ArrayLoader, 'en'));

        return $factory->make($payload, $request->rules());
    }
}
