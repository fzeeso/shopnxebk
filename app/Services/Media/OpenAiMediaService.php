<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Enums\MediaAiOperation;
use App\Exceptions\OpenAiMediaException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;

final readonly class OpenAiMediaService
{
    private const GENERATIONS_ENDPOINT = 'https://api.openai.com/v1/images/generations';

    private const EDITS_ENDPOINT = 'https://api.openai.com/v1/images/edits';

    private const RESPONSES_ENDPOINT = 'https://api.openai.com/v1/responses';

    /**
     * @return array{images: list<array{bytes: string, revised_prompt: string|null}>, model: string, provider_request_id: string|null}
     */
    public function generateImages(
        string $prompt,
        int $count,
        string $size,
        ?string $quality = null,
    ): array {
        $model = $this->imageModel();

        try {
            $response = $this->jsonClient()->post(self::GENERATIONS_ENDPOINT, [
                'model' => $model,
                'prompt' => $prompt,
                'n' => $count,
                'size' => $size,
                'quality' => $this->quality($quality),
            ]);
        } catch (ConnectionException $exception) {
            throw $this->connectionFailure($exception);
        }

        $this->ensureSuccessful($response, 'generation');

        return [
            'images' => $this->decodeImages($response),
            'model' => $model,
            'provider_request_id' => $this->requestId($response),
        ];
    }

    /** @return array{bytes: string, model: string, provider_request_id: string|null, revised_prompt: string|null} */
    public function editImage(
        string $imageBytes,
        string $filename,
        string $mimeType,
        string $prompt,
        bool $transparent,
        ?string $quality = null,
    ): array {
        $model = $this->imageModel();

        try {
            $client = Http::withToken($this->apiKey())
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout($this->timeout())
                ->attach('image', $imageBytes, $filename, ['Content-Type' => $mimeType]);
            $input = [
                'model' => $model,
                'prompt' => $prompt,
                'quality' => $this->quality($quality),
            ];
            if ($transparent) {
                $input['background'] = 'transparent';
            }
            $response = $client->post(self::EDITS_ENDPOINT, $input);
        } catch (ConnectionException $exception) {
            throw $this->connectionFailure($exception);
        }

        $this->ensureSuccessful($response, 'edit');
        $images = $this->decodeImages($response);
        $image = $images[0] ?? null;
        if ($image === null) {
            throw new OpenAiMediaException('The AI media provider returned no edited image.');
        }

        return [
            'bytes' => $image['bytes'],
            'model' => $model,
            'provider_request_id' => $this->requestId($response),
            'revised_prompt' => $image['revised_prompt'],
        ];
    }

    /** @return array{model: string, provider_request_id: string|null, result: array<string, mixed>, usage: array<string, mixed>|null} */
    public function analyzeImage(
        MediaAiOperation $operation,
        string $imageBytes,
        string $mimeType,
        string $safetyIdentifier,
    ): array {
        if ($operation->createsMedia()) {
            throw new OpenAiMediaException('The selected operation is not a metadata operation.', 422);
        }

        $model = $this->analysisModel();
        $dataUrl = sprintf('data:%s;base64,%s', $mimeType, base64_encode($imageBytes));

        try {
            $response = $this->jsonClient()->post(self::RESPONSES_ENDPOINT, [
                'model' => $model,
                'store' => false,
                'safety_identifier' => $safetyIdentifier,
                'max_output_tokens' => min(
                    4000,
                    max(500, (int) config('services.openai.media_max_output_tokens', 2000)),
                ),
                'input' => [[
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => $this->analysisInstruction($operation),
                        ],
                        [
                            'type' => 'input_image',
                            'image_url' => $dataUrl,
                            'detail' => 'high',
                        ],
                    ],
                ]],
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => $operation->value,
                        'strict' => true,
                        'schema' => $this->analysisSchema($operation),
                    ],
                ],
            ]);
        } catch (ConnectionException $exception) {
            throw $this->connectionFailure($exception);
        }

        $this->ensureSuccessful($response, 'analysis');

        try {
            $result = json_decode($this->outputText($response->json()), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new OpenAiMediaException('The AI media provider returned invalid analysis data.', previous: $exception);
        }
        if (! is_array($result)) {
            throw new OpenAiMediaException('The AI media provider returned incomplete analysis data.');
        }

        return [
            'model' => $model,
            'provider_request_id' => $this->requestId($response),
            'result' => $result,
            'usage' => is_array($response->json('usage')) ? $response->json('usage') : null,
        ];
    }

    private function jsonClient(): PendingRequest
    {
        return Http::withToken($this->apiKey())
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout($this->timeout());
    }

    private function apiKey(): string
    {
        $apiKey = trim((string) config('services.openai.api_key'));
        if ($apiKey === '') {
            throw new OpenAiMediaException('AI media generation is not configured.', 503);
        }

        return $apiKey;
    }

    private function imageModel(): string
    {
        return trim((string) config('services.openai.media_image_model', 'gpt-image-2')) ?: 'gpt-image-2';
    }

    private function analysisModel(): string
    {
        return trim((string) config('services.openai.media_analysis_model', 'gpt-5-mini')) ?: 'gpt-5-mini';
    }

    private function quality(?string $quality): string
    {
        $selected = $quality ?? (string) config('services.openai.media_quality', 'medium');

        return in_array($selected, ['low', 'medium', 'high'], true) ? $selected : 'medium';
    }

    private function timeout(): int
    {
        return min(600, max(30, (int) config('services.openai.media_timeout', 240)));
    }

    private function connectionFailure(ConnectionException $exception): OpenAiMediaException
    {
        Log::warning('OpenAI media connection failed.', ['exception' => $exception::class]);

        return new OpenAiMediaException('The AI media provider could not be reached.', 503, $exception);
    }

    private function ensureSuccessful(Response $response, string $operation): void
    {
        if (! $response->failed()) {
            return;
        }

        Log::warning('OpenAI media request failed.', [
            'operation' => $operation,
            'status' => $response->status(),
            'request_id' => $this->requestId($response),
            'error_type' => $response->json('error.type'),
            'error_code' => $response->json('error.code'),
        ]);

        throw new OpenAiMediaException('The AI media provider rejected the request.');
    }

    /** @return list<array{bytes: string, revised_prompt: string|null}> */
    private function decodeImages(Response $response): array
    {
        $decoded = [];
        $maxBytes = max(1024, (int) config('services.openai.media_max_output_bytes', 20 * 1024 * 1024));

        foreach ($response->json('data', []) as $image) {
            if (! is_array($image) || ! is_string($image['b64_json'] ?? null)) {
                continue;
            }
            $bytes = base64_decode($image['b64_json'], true);
            if (! is_string($bytes) || $bytes === '' || strlen($bytes) > $maxBytes) {
                throw new OpenAiMediaException('The AI media provider returned an invalid image payload.');
            }
            $decoded[] = [
                'bytes' => $bytes,
                'revised_prompt' => is_string($image['revised_prompt'] ?? null) ? $image['revised_prompt'] : null,
            ];
        }

        if ($decoded === []) {
            throw new OpenAiMediaException('The AI media provider returned no image.');
        }

        return $decoded;
    }

    private function requestId(Response $response): ?string
    {
        $requestId = $response->header('x-request-id') ?: $response->json('id');

        return is_string($requestId) && $requestId !== '' ? $requestId : null;
    }

    /** @param array<string, mixed> $payload */
    private function outputText(array $payload): string
    {
        if (is_string($payload['output_text'] ?? null)) {
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

        throw new OpenAiMediaException('The AI media provider returned no analysis text.');
    }

    private function analysisInstruction(MediaAiOperation $operation): string
    {
        return match ($operation) {
            MediaAiOperation::GenerateAltText => 'Write concise, factual ecommerce alt text for this image. Do not invent unseen product details.',
            MediaAiOperation::GenerateAttributes => 'Identify visible ecommerce product attributes only. Use short attribute names and values and do not infer hidden specifications.',
            MediaAiOperation::GenerateTags => 'Generate concise ecommerce discovery tags based only on visible image content.',
            MediaAiOperation::GenerateSeoFilename => 'Suggest one lowercase, hyphen-separated SEO filename stem based only on visible image content. Do not include an extension.',
            default => throw new OpenAiMediaException('The selected operation is not supported.', 422),
        };
    }

    /** @return array<string, mixed> */
    private function analysisSchema(MediaAiOperation $operation): array
    {
        $properties = match ($operation) {
            MediaAiOperation::GenerateAltText => [
                'alt_text' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 500],
            ],
            MediaAiOperation::GenerateAttributes => [
                'attributes' => [
                    'type' => 'array',
                    'maxItems' => 12,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 80],
                            'value' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 160],
                        ],
                        'required' => ['name', 'value'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            MediaAiOperation::GenerateTags => [
                'tags' => [
                    'type' => 'array',
                    'maxItems' => 15,
                    'items' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 80],
                ],
            ],
            MediaAiOperation::GenerateSeoFilename => [
                'seo_filename' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 160],
            ],
            default => throw new OpenAiMediaException('The selected operation is not supported.', 422),
        };

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }
}
