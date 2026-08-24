<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Enums\MediaStatus;
use App\Enums\MediaVariantName;
use App\Enums\MediaVisibility;
use App\Jobs\Media\ExtractMediaMetadata;
use App\Jobs\Media\FinalizeMediaProcessing;
use App\Jobs\Media\GenerateMediaVariants;
use App\Jobs\Media\OptimizeMedia;
use App\Models\Media;
use App\Models\MediaUsage;
use App\Models\ProductMedia;
use App\Models\ProductVariantMedia;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Models\User;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Stores\Models\Store;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class MediaService
{
    public function __construct(private MediaAccessService $access) {}

    /** @param array<string, mixed> $filters */
    public function list(User $user, array $filters, int $page, int $perPage): LengthAwarePaginator
    {
        $store = $this->access->view($user);

        return Media::query()
            ->where('store_id', $store->getKey())
            ->where('status', '!=', MediaStatus::Deleted->value)
            ->when($filters['status'] ?? null, fn (Builder $query, mixed $status) => $query->where('status', $status))
            ->when($filters['mime_type'] ?? null, fn (Builder $query, mixed $mime) => $query->where('mime_type', $mime))
            ->when($filters['search'] ?? null, function (Builder $query, mixed $search): void {
                $value = '%'.addcslashes((string) $search, '%_\\').'%';
                $query->where(function (Builder $query) use ($value): void {
                    $query->where('original_filename', 'ilike', $value)
                        ->orWhere('title', 'ilike', $value)
                        ->orWhere('alt_text', 'ilike', $value);
                });
            })
            ->with('variants.media')
            ->latest('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function get(User $user, string $publicId, bool $manage = false): Media
    {
        $store = $manage ? $this->access->manage($user) : $this->access->view($user);

        return $this->media($store, $publicId)->load('variants.media');
    }

    /** @param array<string, mixed> $input */
    public function createUpload(User $user, UploadedFile $file, array $input): Media
    {
        $store = $this->access->manage($user);
        [$mimeType, $extension] = $this->validatedFileType($file);
        $disk = (string) ($input['disk'] ?? config('media-management.disk'));
        if (! in_array($disk, (array) config('media-management.allowed_disks'), true)) {
            throw ValidationException::withMessages(['disk' => ['The selected media disk is not allowed.']]);
        }
        if (! array_key_exists($disk, (array) config('filesystems.disks'))) {
            throw ValidationException::withMessages(['disk' => ['The selected media disk is not configured.']]);
        }

        $publicId = (string) Str::ulid();
        $originalFilename = basename($file->getClientOriginalName());
        $baseName = Str::slug(pathinfo($originalFilename, PATHINFO_FILENAME));
        $filename = Str::limit($baseName !== '' ? $baseName : 'media', 160, '').'.'.$extension;
        $directory = sprintf(
            'stores/%s/media/%s/%s/%s',
            $store->public_id,
            now()->format('Y'),
            now()->format('m'),
            $publicId,
        );
        $visibility = MediaVisibility::from((string) ($input['visibility'] ?? MediaVisibility::Private->value));
        $storedPath = Storage::disk($disk)->putFileAs($directory, $file, $filename, [
            'visibility' => $visibility->value,
        ]);
        if (! is_string($storedPath) || $storedPath === '') {
            throw new RuntimeException('The media file could not be written to storage.');
        }

        try {
            $media = Media::query()->create([
                'public_id' => $publicId,
                'store_id' => $store->getKey(),
                'model_type' => Store::class,
                'model_id' => $store->getKey(),
                'uuid' => (string) Str::uuid(),
                'collection_name' => 'media',
                'name' => pathinfo($originalFilename, PATHINFO_FILENAME),
                'file_name' => $filename,
                'mime_type' => $mimeType,
                'disk' => $disk,
                'conversions_disk' => $disk,
                'size' => $file->getSize() ?: Storage::disk($disk)->size($storedPath),
                'manipulations' => [],
                'custom_properties' => $input['metadata'] ?? [],
                'generated_conversions' => [],
                'responsive_images' => [],
                'directory' => $directory,
                'path' => $storedPath,
                'original_filename' => $originalFilename,
                'filename' => $filename,
                'extension' => $extension,
                'checksum' => hash_file('sha256', $file->getRealPath()) ?: null,
                'alt_text' => $input['alt_text'] ?? null,
                'title' => $input['title'] ?? null,
                'caption' => $input['caption'] ?? null,
                'status' => MediaStatus::Pending,
                'visibility' => $visibility,
                'metadata' => $input['metadata'] ?? null,
            ]);
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($storedPath);
            throw $exception;
        }

        return $media->load('variants.media');
    }

    public function completeUpload(User $user, string $publicId): Media
    {
        $store = $this->access->manage($user);
        $shouldDispatch = false;
        $media = DB::transaction(function () use ($store, $publicId, &$shouldDispatch): Media {
            $media = Media::query()
                ->where('store_id', $store->getKey())
                ->where('public_id', $publicId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($media->status === MediaStatus::Deleted) {
                throw ValidationException::withMessages(['media' => ['Deleted media cannot be processed.']]);
            }
            if (! Storage::disk($media->disk)->exists($media->path)) {
                throw ValidationException::withMessages(['media' => ['The uploaded object is missing from storage.']]);
            }
            if (in_array($media->status, [MediaStatus::Processing, MediaStatus::Ready], true)) {
                return $media;
            }

            $media->forceFill(['status' => MediaStatus::Processing])->save();
            $shouldDispatch = true;

            return $media;
        });

        if ($shouldDispatch) {
            try {
                $this->dispatchProcessingChain($media);
            } catch (\Throwable $exception) {
                $media->forceFill(['status' => MediaStatus::Failed])->save();
                throw $exception;
            }
        }

        return $media->refresh()->load('variants.media');
    }

    public function attachToProduct(
        User $user,
        string $productPublicId,
        string $mediaPublicId,
        int $sortOrder = 0,
        ?bool $isPrimary = null,
    ): ProductMedia {
        $store = $this->access->manage($user);
        $product = $this->product($store, $productPublicId);
        $media = $this->attachableMedia($store, $mediaPublicId);

        return DB::transaction(function () use ($store, $product, $media, $sortOrder, $isPrimary): ProductMedia {
            if ($isPrimary) {
                ProductMedia::query()->where('product_id', $product->getKey())->update(['is_primary' => false]);
            }

            $attributes = [
                'store_id' => $store->getKey(),
                'sort_order' => $sortOrder,
            ];
            if ($isPrimary !== null) {
                $attributes['is_primary'] = $isPrimary;
            }
            $attachment = ProductMedia::query()->updateOrCreate(
                ['product_id' => $product->getKey(), 'media_id' => $media->getKey()],
                $attributes,
            );
            $this->recordUsage($media, $store, Product::class, (int) $product->getKey());

            return $attachment;
        });
    }

    public function detachFromProduct(User $user, string $productPublicId, string $mediaPublicId): void
    {
        $store = $this->access->manage($user);
        $product = $this->product($store, $productPublicId);
        $media = $this->media($store, $mediaPublicId);

        DB::transaction(function () use ($store, $product, $media): void {
            $attachment = ProductMedia::query()
                ->where('product_id', $product->getKey())
                ->where('media_id', $media->getKey())
                ->firstOrFail();
            $wasPrimary = (bool) $attachment->is_primary;
            $attachment->delete();
            MediaUsage::query()
                ->where('store_id', $store->getKey())
                ->where('media_id', $media->getKey())
                ->where('resource_type', Product::class)
                ->where('resource_id', $product->getKey())
                ->delete();

            if ($wasPrimary) {
                $nextAttachmentId = ProductMedia::query()
                    ->where('product_id', $product->getKey())
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->value('id');
                if ($nextAttachmentId !== null) {
                    ProductMedia::query()->whereKey($nextAttachmentId)->update(['is_primary' => true]);
                }
            }
        });
    }

    public function setPrimaryProductMedia(User $user, string $productPublicId, string $mediaPublicId): ProductMedia
    {
        $store = $this->access->manage($user);
        $product = $this->product($store, $productPublicId);
        $media = $this->media($store, $mediaPublicId);

        return DB::transaction(function () use ($product, $media): ProductMedia {
            $attachment = ProductMedia::query()
                ->where('product_id', $product->getKey())
                ->where('media_id', $media->getKey())
                ->firstOrFail();
            ProductMedia::query()->where('product_id', $product->getKey())->update(['is_primary' => false]);
            $attachment->forceFill(['is_primary' => true])->save();

            return $attachment->refresh();
        });
    }

    public function attachToProductVariant(
        User $user,
        string $variantPublicId,
        string $mediaPublicId,
        int $sortOrder = 0,
    ): ProductVariantMedia {
        $store = $this->access->manage($user);
        $variant = $this->productVariant($store, $variantPublicId);
        $media = $this->attachableMedia($store, $mediaPublicId);

        return DB::transaction(function () use ($store, $variant, $media, $sortOrder): ProductVariantMedia {
            $attachment = ProductVariantMedia::query()->updateOrCreate(
                ['product_variant_id' => $variant->getKey(), 'media_id' => $media->getKey()],
                ['store_id' => $store->getKey(), 'sort_order' => $sortOrder],
            );
            $this->recordUsage($media, $store, ProductVariant::class, (int) $variant->getKey());

            return $attachment;
        });
    }

    public function detachFromProductVariant(User $user, string $variantPublicId, string $mediaPublicId): void
    {
        $store = $this->access->manage($user);
        $variant = $this->productVariant($store, $variantPublicId);
        $media = $this->media($store, $mediaPublicId);

        DB::transaction(function () use ($store, $variant, $media): void {
            ProductVariantMedia::query()
                ->where('product_variant_id', $variant->getKey())
                ->where('media_id', $media->getKey())
                ->firstOrFail()
                ->delete();
            MediaUsage::query()
                ->where('store_id', $store->getKey())
                ->where('media_id', $media->getKey())
                ->where('resource_type', ProductVariant::class)
                ->where('resource_id', $variant->getKey())
                ->delete();
        });
    }

    public function delete(User $user, string $publicId): void
    {
        $store = $this->access->manage($user);
        DB::transaction(function () use ($store, $publicId): void {
            $media = Media::query()
                ->where('store_id', $store->getKey())
                ->where('public_id', $publicId)
                ->lockForUpdate()
                ->firstOrFail();
            $primaryProductIds = ProductMedia::query()
                ->where('media_id', $media->getKey())
                ->where('is_primary', true)
                ->pluck('product_id');

            ProductMedia::query()->where('media_id', $media->getKey())->delete();
            ProductVariantMedia::query()->where('media_id', $media->getKey())->delete();
            $media->forceFill([
                'status' => MediaStatus::Deleted,
                'visibility' => MediaVisibility::Private,
            ])->save();

            foreach ($primaryProductIds as $productId) {
                $nextAttachmentId = ProductMedia::query()
                    ->where('product_id', $productId)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->value('id');
                if ($nextAttachmentId !== null) {
                    ProductMedia::query()->whereKey($nextAttachmentId)->update(['is_primary' => true]);
                }
            }
        });
    }

    public function findDuplicate(User $user, string $checksum): ?Media
    {
        $store = $this->access->view($user);

        return Media::query()
            ->where('store_id', $store->getKey())
            ->where('checksum', $checksum)
            ->where('status', '!=', MediaStatus::Deleted->value)
            ->first();
    }

    public function generateUrl(Media $media, ?MediaVariantName $variant = null): string
    {
        $parameters = ['media' => $media->public_id];
        if ($variant !== null) {
            $parameters['variant'] = $variant->value;
        }

        return route('api.v1.store.media.content', $parameters);
    }

    public function stream(User $user, string $publicId, ?string $variant): StreamedResponse
    {
        $media = $this->get($user, $publicId);
        $disk = $media->disk;
        $path = $media->path;
        $mimeType = $media->mime_type;
        $filename = $media->original_filename;

        if ($variant !== null) {
            $variantName = MediaVariantName::tryFrom($variant);
            if ($variantName === null) {
                throw ValidationException::withMessages(['variant' => ['The selected media variant is invalid.']]);
            }
            $mediaVariant = $media->variants->firstWhere('variant', $variantName);
            if ($mediaVariant === null) {
                abort(404, 'Media variant not found.');
            }
            $disk = $mediaVariant->disk;
            $path = $mediaVariant->path;
            $mimeType = $mediaVariant->mime_type;
            $filename = $variantName->value.'-'.$media->filename;
        }

        return Storage::disk($disk)->response($path, $filename, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $filename).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function dispatchProcessingChain(Media $media): void
    {
        $chain = Bus::chain([
            new ExtractMediaMetadata((int) $media->getKey(), (int) $media->store_id),
            new OptimizeMedia((int) $media->getKey(), (int) $media->store_id),
            new GenerateMediaVariants((int) $media->getKey(), (int) $media->store_id),
            new FinalizeMediaProcessing((int) $media->getKey(), (int) $media->store_id),
        ]);
        $connection = (string) config('media-management.queue.connection');
        $queue = (string) config('media-management.queue.name');
        if ($connection !== '') {
            $chain->onConnection($connection);
        }
        if ($queue !== '') {
            $chain->onQueue($queue);
        }
        $chain->dispatch();
    }

    /** @return array{string, string} */
    private function validatedFileType(UploadedFile $file): array
    {
        $mimeType = (string) $file->getMimeType();
        $allowed = (array) config('media-management.allowed_mime_types');
        $extensions = $allowed[$mimeType] ?? null;
        $extension = strtolower($file->getClientOriginalExtension());
        if (! is_array($extensions) || ! in_array($extension, $extensions, true)) {
            throw ValidationException::withMessages([
                'file' => ['The file content and extension must be an allowed image type.'],
            ]);
        }

        return [$mimeType, $extension];
    }

    private function media(Store $store, string $publicId): Media
    {
        return Media::query()
            ->where('store_id', $store->getKey())
            ->where('public_id', $publicId)
            ->where('status', '!=', MediaStatus::Deleted->value)
            ->firstOrFail();
    }

    private function attachableMedia(Store $store, string $publicId): Media
    {
        return Media::query()
            ->where('store_id', $store->getKey())
            ->where('public_id', $publicId)
            ->where('status', MediaStatus::Ready->value)
            ->firstOrFail();
    }

    private function product(Store $store, string $publicId): Product
    {
        return Product::query()
            ->where('store_id', $store->getKey())
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    private function productVariant(Store $store, string $publicId): ProductVariant
    {
        return ProductVariant::query()
            ->where('store_id', $store->getKey())
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    private function recordUsage(Media $media, Store $store, string $type, int $id): void
    {
        MediaUsage::query()->firstOrCreate([
            'media_id' => $media->getKey(),
            'store_id' => $store->getKey(),
            'resource_type' => $type,
            'resource_id' => $id,
        ]);
    }
}
