<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Enums\MediaAiOperation;
use App\Models\Media;
use App\Models\MediaAiResult;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Modules\Authentication\Models\User;
use Throwable;

/** Store-scoped orchestration for provider-backed media AI operations. */
final readonly class MediaAiService
{
    public function __construct(
        private MediaAccessService $access,
        private MediaService $mediaService,
        private OpenAiMediaService $provider,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return list<Media>
     */
    public function generate(User $user, array $input): array
    {
        $this->access->manage($user);
        $prompt = $this->generationPrompt($input);
        $aspectRatio = (string) ($input['aspect_ratio'] ?? '1:1');
        $providerResult = $this->provider->generateImages(
            $prompt,
            (int) ($input['image_count'] ?? 1),
            $this->sizeForAspectRatio($aspectRatio),
            is_string($input['quality'] ?? null) ? $input['quality'] : null,
        );
        $mediaItems = [];

        foreach ($providerResult['images'] as $image) {
            $filename = 'ai-'.strtolower((string) Str::ulid()).'.png';
            $media = $this->mediaService->createGeneratedImage($user, $image['bytes'], $filename, [
                'visibility' => 'private',
                'title' => Str::limit((string) $input['prompt'], 255, ''),
                'metadata' => [
                    'source' => 'ai_generated',
                    'ai' => [
                        'provider' => 'openai',
                        'model' => $providerResult['model'],
                        'operation' => 'generate_image',
                        'aspect_ratio' => $aspectRatio,
                    ],
                ],
            ]);
            $media->aiResults()->create([
                'provider' => 'openai',
                'model' => $providerResult['model'],
                'operation' => 'generate_image',
                'status' => 'completed',
                'result' => [
                    'media_id' => $media->public_id,
                    'prompt' => $input['prompt'],
                    'revised_prompt' => $image['revised_prompt'],
                    'provider_request_id' => $providerResult['provider_request_id'],
                    'aspect_ratio' => $aspectRatio,
                    'quality' => $input['quality'] ?? config('services.openai.media_quality', 'medium'),
                ],
            ]);
            $mediaItems[] = $media->refresh()->load('variants.media');
        }

        return $mediaItems;
    }

    /**
     * @return array{ai_result: MediaAiResult, media: Media, generated_media: Media|null}
     */
    public function run(
        User $user,
        string $mediaPublicId,
        MediaAiOperation $operation,
        ?string $quality = null,
    ): array {
        $source = $this->mediaService->readImage($user, $mediaPublicId);
        $media = $source['media'];
        $aiResult = $this->start(
            $user,
            $mediaPublicId,
            $operation->value,
            'openai',
            $operation->createsMedia()
                ? (string) config('services.openai.media_image_model', 'gpt-image-2')
                : (string) config('services.openai.media_analysis_model', 'gpt-5-mini'),
        );

        try {
            if ($operation->createsMedia()) {
                $providerResult = $this->provider->editImage(
                    $source['bytes'],
                    (string) $media->original_filename,
                    (string) $media->mime_type,
                    $this->editPrompt($operation),
                    $operation === MediaAiOperation::RemoveBackground,
                    $quality,
                );
                $generatedMedia = $this->mediaService->createGeneratedImage(
                    $user,
                    $providerResult['bytes'],
                    $this->derivedFilename($media, $operation),
                    [
                        'visibility' => 'private',
                        'title' => $media->title,
                        'alt_text' => $media->alt_text,
                        'caption' => $media->caption,
                        'metadata' => [
                            'source' => 'ai_generated',
                            'ai' => [
                                'provider' => 'openai',
                                'model' => $providerResult['model'],
                                'operation' => $operation->value,
                                'source_media_id' => $media->public_id,
                            ],
                        ],
                    ],
                );
                $aiResult = $this->complete($aiResult, [
                    'generated_media_id' => $generatedMedia->public_id,
                    'provider_request_id' => $providerResult['provider_request_id'],
                    'revised_prompt' => $providerResult['revised_prompt'],
                ]);

                return [
                    'ai_result' => $aiResult->load('media'),
                    'media' => $media->refresh()->load('variants.media'),
                    'generated_media' => $generatedMedia->refresh()->load('variants.media'),
                ];
            }

            $providerResult = $this->provider->analyzeImage(
                $operation,
                $source['bytes'],
                (string) $media->mime_type,
                hash('sha256', sprintf('shopnxe-media:%s:%s', $media->store_id, $user->getKey())),
            );
            $normalized = $this->applyMetadataResult($media, $operation, $providerResult['result']);
            $aiResult = $this->complete($aiResult, [
                ...$normalized,
                'provider_request_id' => $providerResult['provider_request_id'],
                'usage' => $providerResult['usage'],
            ]);

            return [
                'ai_result' => $aiResult->load('media'),
                'media' => $media->refresh()->load('variants.media'),
                'generated_media' => null,
            ];
        } catch (Throwable $exception) {
            $this->fail($aiResult, ['message' => 'The AI media operation failed.']);
            throw $exception;
        }
    }

    /** @return Collection<int, MediaAiResult> */
    public function history(User $user, string $mediaPublicId): Collection
    {
        $store = $this->access->view($user);
        $media = Media::query()
            ->where('store_id', $store->getKey())
            ->where('public_id', $mediaPublicId)
            ->where('status', '!=', 'deleted')
            ->firstOrFail();

        return $media->aiResults()->with('media')->limit(50)->get();
    }

    public function start(
        User $user,
        string $mediaPublicId,
        string $operation,
        ?string $provider = null,
        ?string $model = null,
    ): MediaAiResult {
        $store = $this->access->manage($user);
        $media = Media::query()
            ->where('store_id', $store->getKey())
            ->where('public_id', $mediaPublicId)
            ->where('status', '!=', 'deleted')
            ->firstOrFail();

        return $media->aiResults()->create([
            'provider' => $provider,
            'model' => $model,
            'operation' => $operation,
            'status' => 'processing',
        ]);
    }

    /** @param array<string, mixed> $result */
    public function complete(MediaAiResult $aiResult, array $result, ?float $confidence = null): MediaAiResult
    {
        $aiResult->forceFill([
            'status' => 'completed',
            'result' => $result,
            'confidence' => $confidence,
        ])->save();

        return $aiResult->refresh();
    }

    /** @param array<string, mixed> $result */
    public function fail(MediaAiResult $aiResult, array $result): MediaAiResult
    {
        $aiResult->forceFill(['status' => 'failed', 'result' => $result])->save();

        return $aiResult->refresh();
    }

    /** @param array<string, mixed> $input */
    private function generationPrompt(array $input): string
    {
        $details = array_filter([
            trim((string) ($input['image_type'] ?? '')),
            trim((string) ($input['style'] ?? '')),
        ]);

        return implode("\n", array_filter([
            trim((string) $input['prompt']),
            $details === [] ? null : 'Creative direction: '.implode(', ', $details).'.',
            'Create production-ready ecommerce imagery. Do not add text, logos, trademarks, or product claims unless explicitly requested.',
        ]));
    }

    private function sizeForAspectRatio(string $aspectRatio): string
    {
        return match ($aspectRatio) {
            '4:5' => '1024x1280',
            '16:9' => '1536x864',
            default => '1024x1024',
        };
    }

    private function editPrompt(MediaAiOperation $operation): string
    {
        return match ($operation) {
            MediaAiOperation::RemoveBackground => 'Remove the entire background and return the isolated subject on a transparent background. Preserve the product, edges, proportions, colors, text, and branding exactly.',
            MediaAiOperation::EnhanceImage => 'Enhance this ecommerce image for clarity, sharpness, balanced lighting, and professional presentation. Preserve the product identity, composition, colors, text, logos, and proportions exactly.',
            default => '',
        };
    }

    private function derivedFilename(Media $media, MediaAiOperation $operation): string
    {
        $stem = Str::slug(pathinfo((string) $media->original_filename, PATHINFO_FILENAME)) ?: 'media';
        $suffix = $operation === MediaAiOperation::RemoveBackground ? 'background-removed' : 'enhanced';

        return Str::limit($stem, 120, '').'-'.$suffix.'.png';
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function applyMetadataResult(Media $media, MediaAiOperation $operation, array $result): array
    {
        $metadata = is_array($media->metadata) ? $media->metadata : [];
        $aiMetadata = is_array($metadata['ai'] ?? null) ? $metadata['ai'] : [];

        if ($operation === MediaAiOperation::GenerateAltText) {
            $altText = trim((string) ($result['alt_text'] ?? ''));
            $media->forceFill(['alt_text' => Str::limit($altText, 5000, '')])->save();

            return ['alt_text' => $media->alt_text];
        }

        if ($operation === MediaAiOperation::GenerateSeoFilename) {
            $stem = Str::slug((string) ($result['seo_filename'] ?? '')) ?: 'media';
            $result = ['seo_filename' => Str::limit($stem, 160, '').'.'.($media->extension ?: 'png')];
        }

        $firstKey = array_key_first($result);
        $aiMetadata[$operation->value] = $firstKey === null ? $result : $result[$firstKey];
        $metadata['ai'] = $aiMetadata;
        $media->forceFill(['metadata' => $metadata])->save();

        return $result;
    }
}
