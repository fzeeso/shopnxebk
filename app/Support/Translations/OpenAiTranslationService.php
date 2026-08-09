<?php

declare(strict_types=1);

namespace App\Support\Translations;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use JsonException;

final class OpenAiTranslationService implements TranslationProvider
{
    private const ENDPOINT = 'https://api.openai.com/v1/responses';

    /**
     * @param  array<string, string|null>  $sourceFields
     * @param  list<string>  $targetLocales
     * @param  list<string>  $requiredFields
     * @return array<string, array<string, string|null>>
     */
    public function translateFields(
        array $sourceFields,
        string $sourceLocale,
        array $targetLocales,
        string $contentType,
        array $requiredFields = [],
    ): array {
        $this->validateInput($sourceFields, $requiredFields);
        $targetLocales = array_values(array_unique($targetLocales));
        if ($targetLocales === []) {
            return [];
        }

        $apiKey = trim((string) config('services.openai.api_key'));
        if ($apiKey === '') {
            throw new OpenAiTranslationException('OpenAI API translation is not configured.');
        }

        $model = trim((string) config('services.openai.translation_model', 'gpt-5-mini'));
        if ($model === '') {
            $model = 'gpt-5-mini';
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->connectTimeout(10)
                ->timeout(max(10, (int) config('services.openai.translation_timeout', 30)))
                ->post(self::ENDPOINT, [
                    'model' => $model,
                    'store' => false,
                    'max_output_tokens' => min(16000, 1500 + (count($targetLocales) * 1000)),
                    'input' => [
                        [
                            'role' => 'system',
                            'content' => implode(' ', [
                                "You translate {$contentType} faithfully and naturally.",
                                'Keep trademarks, product names, Brand names, and legal names unchanged unless they have an established localized form.',
                                'Preserve HTML tags, attributes, URLs, numbers, and placeholders exactly.',
                                'Do not add claims or information that is absent from the source.',
                                'Return exactly one translation for every requested target locale and keep null fields null.',
                            ]),
                        ],
                        [
                            'role' => 'user',
                            'content' => json_encode([
                                'source_locale' => $sourceLocale,
                                'target_locales' => $targetLocales,
                                'source' => $sourceFields,
                            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                    'text' => [
                        'format' => [
                            'type' => 'json_schema',
                            'name' => 'content_translations',
                            'strict' => true,
                            'schema' => $this->responseSchema($sourceFields, $targetLocales, $requiredFields),
                        ],
                    ],
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('OpenAI translation connection failed.', [
                'exception' => $exception::class,
            ]);

            throw new OpenAiTranslationException('The automatic translation service could not be reached.', previous: $exception);
        } catch (JsonException $exception) {
            throw new OpenAiTranslationException('Translation input could not be encoded.', previous: $exception);
        }

        if ($response->failed()) {
            Log::warning('OpenAI translation request failed.', [
                'status' => $response->status(),
                'request_id' => $response->header('x-request-id'),
                'error_type' => $response->json('error.type'),
                'error_code' => $response->json('error.code'),
            ]);

            throw new OpenAiTranslationException('The automatic translation service rejected the request.');
        }

        try {
            $decoded = json_decode($this->outputText($response->json()), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new OpenAiTranslationException('The automatic translation service returned invalid JSON.', previous: $exception);
        }

        return $this->validateTranslations($decoded, $sourceFields, $targetLocales, $requiredFields);
    }

    /**
     * @param  array<string, string|null>  $sourceFields
     * @param  list<string>  $targetLocales
     * @param  list<string>  $requiredFields
     */
    private function responseSchema(array $sourceFields, array $targetLocales, array $requiredFields): array
    {
        $properties = ['locale' => ['type' => 'string', 'enum' => $targetLocales]];
        foreach ($sourceFields as $field => $value) {
            if ($value === null) {
                $properties[$field] = ['type' => 'null'];
            } else {
                $properties[$field] = ['type' => 'string'];
                if (in_array($field, $requiredFields, true)) {
                    $properties[$field]['minLength'] = 1;
                }
            }
        }

        return [
            'type' => 'object',
            'properties' => [
                'translations' => [
                    'type' => 'array',
                    'minItems' => count($targetLocales),
                    'maxItems' => count($targetLocales),
                    'items' => [
                        'type' => 'object',
                        'properties' => $properties,
                        'required' => array_keys($properties),
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['translations'],
            'additionalProperties' => false,
        ];
    }

    /** @param array<string, mixed> $payload */
    private function outputText(array $payload): string
    {
        if (isset($payload['output_text']) && is_string($payload['output_text'])) {
            return $payload['output_text'];
        }

        foreach ($payload['output'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            foreach ($item['content'] ?? [] as $content) {
                if (is_array($content)
                    && ($content['type'] ?? null) === 'output_text'
                    && is_string($content['text'] ?? null)) {
                    return $content['text'];
                }
            }
        }

        throw new OpenAiTranslationException('The automatic translation service returned no text output.');
    }

    /**
     * @param  array<string, string|null>  $sourceFields
     * @param  list<string>  $targetLocales
     * @param  list<string>  $requiredFields
     * @return array<string, array<string, string|null>>
     */
    private function validateTranslations(
        mixed $decoded,
        array $sourceFields,
        array $targetLocales,
        array $requiredFields,
    ): array {
        if (! is_array($decoded) || ! is_array($decoded['translations'] ?? null)) {
            throw new OpenAiTranslationException('The automatic translation response is incomplete.');
        }

        $expected = array_fill_keys(array_map($this->localeKey(...), $targetLocales), true);
        $translations = [];

        foreach ($decoded['translations'] as $translation) {
            if (! is_array($translation) || ! is_string($translation['locale'] ?? null)) {
                throw new OpenAiTranslationException('The automatic translation response contains an invalid locale.');
            }

            $localeKey = $this->localeKey($translation['locale']);
            if (! isset($expected[$localeKey]) || isset($translations[$localeKey])) {
                throw new OpenAiTranslationException('The automatic translation response contains an unexpected locale.');
            }

            $fields = [];
            foreach ($sourceFields as $field => $sourceValue) {
                $value = $this->nullableString($translation, $field);
                if (in_array($field, $requiredFields, true) && trim((string) $value) === '') {
                    throw new OpenAiTranslationException("The automatic translation response contains an empty [{$field}] value.");
                }
                if ($sourceValue === null && $value !== null) {
                    throw new OpenAiTranslationException("The automatic translation response changed null [{$field}] content.");
                }
                if ($sourceValue !== null && $value === null) {
                    throw new OpenAiTranslationException("The automatic translation response omitted [{$field}] content.");
                }
                $fields[$field] = $value;
            }

            $translations[$localeKey] = $fields;
        }

        if (count($translations) !== count($expected)) {
            throw new OpenAiTranslationException('The automatic translation response omitted a requested locale.');
        }

        return $translations;
    }

    /** @param array<string, mixed> $translation */
    private function nullableString(array $translation, string $field): ?string
    {
        $value = $translation[$field] ?? null;
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            throw new OpenAiTranslationException("The automatic translation response contains an invalid [{$field}] value.");
        }

        return $value;
    }

    private function localeKey(string $locale): string
    {
        return strtolower(str_replace('-', '_', trim($locale)));
    }

    /**
     * @param  array<string, string|null>  $sourceFields
     * @param  list<string>  $requiredFields
     */
    private function validateInput(array $sourceFields, array $requiredFields): void
    {
        if ($sourceFields === []) {
            throw new InvalidArgumentException('At least one translatable field is required.');
        }

        foreach ($sourceFields as $field => $value) {
            if (preg_match('/^[a-z][a-z0-9_]*$/', $field) !== 1) {
                throw new InvalidArgumentException("Invalid translatable field [{$field}].");
            }
            if (! is_string($value) && $value !== null) {
                throw new InvalidArgumentException("Translatable field [{$field}] must be a string or null.");
            }
        }

        foreach ($requiredFields as $field) {
            if (! array_key_exists($field, $sourceFields)) {
                throw new InvalidArgumentException("Required translatable field [{$field}] is missing.");
            }
            if (trim((string) $sourceFields[$field]) === '') {
                throw new InvalidArgumentException("Required translatable field [{$field}] may not be empty.");
            }
        }
    }
}
