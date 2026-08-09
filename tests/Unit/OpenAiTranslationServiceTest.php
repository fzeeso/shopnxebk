<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Translations\OpenAiTranslationException;
use App\Support\Translations\OpenAiTranslationService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class OpenAiTranslationServiceTest extends TestCase
{
    public function test_it_requests_and_validates_structured_translations_for_arbitrary_fields(): void
    {
        config([
            'services.openai.api_key' => 'test-api-key',
            'services.openai.translation_model' => 'gpt-5-mini',
        ]);
        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode([
                            'translations' => [
                                [
                                    'locale' => 'de',
                                    'name' => 'Acme',
                                    'description' => 'Zuverlässige Produkte.',
                                    'seo_title' => 'Acme Produkte',
                                    'seo_description' => null,
                                ],
                                [
                                    'locale' => 'ar',
                                    'name' => 'Acme',
                                    'description' => 'منتجات موثوقة.',
                                    'seo_title' => 'منتجات Acme',
                                    'seo_description' => null,
                                ],
                            ],
                        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    ]],
                ]],
            ]),
        ]);

        $translations = app(OpenAiTranslationService::class)->translateFields([
            'name' => 'Acme',
            'description' => 'Reliable products.',
            'seo_title' => 'Acme products',
            'seo_description' => null,
        ], 'en', ['de', 'ar'], 'ecommerce Brand metadata', ['name']);

        self::assertSame('Zuverlässige Produkte.', $translations['de']['description']);
        self::assertSame('منتجات موثوقة.', $translations['ar']['description']);

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return $request->url() === 'https://api.openai.com/v1/responses'
                && $request->hasHeader('Authorization', 'Bearer test-api-key')
                && $payload['model'] === 'gpt-5-mini'
                && $payload['store'] === false
                && $payload['text']['format']['type'] === 'json_schema'
                && $payload['text']['format']['strict'] === true;
        });
    }

    public function test_it_rejects_responses_that_omit_a_requested_locale(): void
    {
        config(['services.openai.api_key' => 'test-api-key']);
        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'output_text' => json_encode([
                    'translations' => [[
                        'locale' => 'de',
                        'name' => 'Acme',
                        'description' => null,
                        'seo_title' => null,
                        'seo_description' => null,
                    ]],
                ], JSON_THROW_ON_ERROR),
            ]),
        ]);

        $this->expectException(OpenAiTranslationException::class);

        app(OpenAiTranslationService::class)->translateFields([
            'name' => 'Acme',
            'description' => null,
            'seo_title' => null,
            'seo_description' => null,
        ], 'en', ['de', 'ar'], 'ecommerce Brand metadata', ['name']);
    }

    public function test_it_requires_a_server_side_api_key(): void
    {
        config(['services.openai.api_key' => null]);
        Http::fake();

        $this->expectException(OpenAiTranslationException::class);

        app(OpenAiTranslationService::class)->translateFields([
            'name' => 'Acme',
            'description' => null,
            'seo_title' => null,
            'seo_description' => null,
        ], 'en', ['de'], 'ecommerce Brand metadata', ['name']);
    }
}
