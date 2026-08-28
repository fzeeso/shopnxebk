<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Requests\SharedProductOptionWriteRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use PHPUnit\Framework\TestCase;

final class SharedProductOptionWriteRequestTest extends TestCase
{
    public function test_translation_locales_are_distinct_within_each_option_value(): void
    {
        $payload = [
            'name' => 'Color',
            'type' => 'dropdown',
            'translations' => [
                ['locale' => 'en', 'display_name' => 'Color'],
            ],
            'values' => [
                [
                    'translations' => [
                        ['locale' => 'en', 'display_label' => 'Red'],
                        ['locale' => 'ur', 'display_label' => 'سرخ'],
                    ],
                ],
                [
                    'translations' => [
                        ['locale' => 'en', 'display_label' => 'Green'],
                        ['locale' => 'ur', 'display_label' => 'سبز'],
                    ],
                ],
            ],
        ];

        self::assertTrue($this->validator($payload)->passes());

        $payload['values'][0]['translations'][1]['locale'] = 'en';
        $validator = $this->validator($payload);

        self::assertTrue($validator->fails());
        self::assertTrue($validator->errors()->has('values.0.translations'));
    }

    /** @param array<string, mixed> $payload */
    private function validator(array $payload): Validator
    {
        $request = SharedProductOptionWriteRequest::create(
            '/api/v1/store/options',
            'POST',
            $payload,
        );
        $factory = new Factory(new Translator(new ArrayLoader, 'en'));

        return $factory->make($payload, $request->rules());
    }
}
