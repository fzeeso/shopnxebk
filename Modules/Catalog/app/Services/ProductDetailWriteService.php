<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use App\Services\Media\MediaService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Models\User;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Support\ProductDetailReferenceMap;
use Modules\Catalog\Support\ProductInputMapper;
use Modules\Stores\Contracts\StoreContext;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final readonly class ProductDetailWriteService
{
    public function __construct(
        private StoreContext $context,
        private CatalogAccessService $access,
        private ProductManagementService $products,
        private ProductImageManagementService $images,
        private ProductOptionManagementService $options,
        private ProductVariantManagementService $variants,
        private CustomFieldManagementService $customFields,
        private CustomObjectReferenceService $customObjects,
        private SharedProductOptionService $sharedOptions,
        private ProductModifierAssignmentService $modifiers,
        private MediaService $media,
        private ProductDetailSectionRegistry $sectionRegistry,
    ) {}

    /** @param array<string, mixed> $command @return array<string, mixed> */
    public function create(User $user, array $command): array
    {
        return DB::transaction(function () use ($user, $command): array {
            /** @var array<string, mixed> $productInput */
            $productInput = $command['product'];
            $product = $this->products->create($user, ProductInputMapper::fromRest($productInput));

            return $this->saveSections($user, (string) $product->public_id, $command);
        });
    }

    /** @param array<string, mixed> $command @return array<string, mixed> */
    public function update(User $user, string $productPublicId, array $command): array
    {
        if (($command['product'] ?? []) === [] && ($command['sections'] ?? []) === []) {
            throw ValidationException::withMessages([
                'command' => ['Supply product fields or at least one section command.'],
            ]);
        }

        return DB::transaction(function () use ($user, $productPublicId, $command): array {
            $store = $this->context->require();
            $this->access->ensureCanManageProducts($user, $store);
            $product = Product::query()
                ->where('store_id', $store->getKey())
                ->where('public_id', $productPublicId)
                ->lockForUpdate()
                ->firstOrFail();
            $this->ensureRevisionMatches($product, $command['expected_updated_at'] ?? null);

            if (($command['product'] ?? []) !== []) {
                /** @var array<string, mixed> $productInput */
                $productInput = $command['product'];
                $this->products->update($user, $productPublicId, ProductInputMapper::fromRest($productInput));
            }

            return $this->saveSections($user, $productPublicId, $command);
        });
    }

    /** @param array<string, mixed> $command @return array<string, mixed> */
    private function saveSections(User $user, string $productPublicId, array $command): array
    {
        /** @var array<string, array<string, mixed>> $sections */
        $sections = $command['sections'] ?? [];
        $references = new ProductDetailReferenceMap;

        $this->deleteCustomFields($user, $productPublicId, $sections['custom_fields']['delete'] ?? [], $references);
        $this->clearCustomObjects($user, $productPublicId, $sections['custom_objects']['clear'] ?? []);
        $this->deleteMedia($user, $productPublicId, $sections['media'] ?? [], $references);
        $this->deleteImages($user, $productPublicId, $sections['images']['delete'] ?? []);
        $this->deleteModifiers($user, $productPublicId, $sections['modifiers']['delete'] ?? []);
        $this->deleteSharedOptions($user, $productPublicId, $sections['shared_options']['delete'] ?? []);
        $this->deleteVariants($user, $productPublicId, $sections['variants']['delete'] ?? []);
        $this->deleteOptionValues($user, $productPublicId, $sections['options']['value_delete'] ?? [], $references);
        $this->deleteOptions($user, $productPublicId, $sections['options']['delete'] ?? []);
        $this->deleteModifierGroups($user, $productPublicId, $sections['modifier_groups']['delete'] ?? []);

        $this->upsertOptions($user, $productPublicId, $sections['options']['upsert'] ?? [], $references);
        $this->upsertOptionValues($user, $productPublicId, $sections['options']['value_upsert'] ?? [], $references);
        $this->upsertVariants($user, $productPublicId, $sections['variants']['upsert'] ?? [], $references);
        $this->upsertImages($user, $productPublicId, $sections['images']['upsert'] ?? [], $references);
        $this->upsertCustomFields($user, $productPublicId, $sections['custom_fields']['upsert'] ?? [], $references);
        $this->replaceCustomObjects($user, $productPublicId, $sections['custom_objects']['replace'] ?? []);
        $this->upsertModifierGroups($user, $productPublicId, $sections['modifier_groups']['upsert'] ?? [], $references);
        $this->upsertSharedOptions($user, $productPublicId, $sections['shared_options']['upsert'] ?? []);
        $this->upsertModifiers($user, $productPublicId, $sections['modifiers']['upsert'] ?? [], $references);
        $this->attachMedia($user, $productPublicId, $sections['media'] ?? [], $references);

        $store = $this->context->require();
        $providerProduct = Product::query()
            ->where('store_id', $store->getKey())
            ->where('public_id', $productPublicId)
            ->lockForUpdate()
            ->firstOrFail();
        foreach ($this->sectionRegistry->all() as $provider) {
            $key = $provider->key();
            if (array_key_exists($key, $sections)) {
                $provider->save($user, $store, $providerProduct, $sections[$key], $references);
            }
        }

        $revisionProduct = Product::query()
            ->where('store_id', $store->getKey())
            ->where('public_id', $productPublicId)
            ->firstOrFail();
        $now = now()->startOfSecond();
        $current = $revisionProduct->updated_at?->copy()->startOfSecond();
        $next = $current !== null && ! $now->greaterThan($current)
            ? $current->addSecond()
            : $now;
        Product::query()
            ->where('store_id', $store->getKey())
            ->whereKey($revisionProduct->getKey())
            ->update(['updated_at' => $next]);

        $savedSections = array_keys($sections);
        if (($command['product'] ?? []) !== []) {
            array_unshift($savedSections, 'product');
        }

        return [
            'product_id' => $productPublicId,
            'saved_sections' => array_values(array_unique($savedSections)),
            'references' => $references->all(),
        ];
    }

    private function ensureRevisionMatches(Product $product, mixed $expected): void
    {
        if ($expected === null) {
            return;
        }

        $actual = $product->updated_at?->toImmutable()->startOfSecond();
        $candidate = CarbonImmutable::parse((string) $expected)->startOfSecond();
        if ($actual === null || ! $actual->equalTo($candidate)) {
            throw new ConflictHttpException(
                'The Product changed after it was loaded. Reload it and reapply the pending sections.',
            );
        }
    }

    /** @param iterable<mixed> $definitionIds */
    private function clearCustomObjects(User $user, string $productId, iterable $definitionIds): void
    {
        foreach ($definitionIds as $definitionId) {
            $this->customObjects->clear($user, 'product', $productId, (string) $definitionId);
        }
    }

    /** @param iterable<array<string, mixed>> $commands */
    private function replaceCustomObjects(User $user, string $productId, iterable $commands): void
    {
        foreach ($commands as $command) {
            $this->customObjects->replace(
                $user,
                'product',
                $productId,
                (string) $command['definition_id'],
                array_map('strval', $command['entry_ids']),
            );
        }
    }

    /** @param iterable<mixed> $ids */
    private function deleteImages(User $user, string $productId, iterable $ids): void
    {
        foreach ($ids as $id) {
            $this->images->delete($user, $productId, (string) $id);
        }
    }

    /** @param iterable<mixed> $ids */
    private function deleteVariants(User $user, string $productId, iterable $ids): void
    {
        foreach ($ids as $id) {
            $this->variants->delete($user, $productId, (string) $id);
        }
    }

    /** @param iterable<mixed> $ids */
    private function deleteOptions(User $user, string $productId, iterable $ids): void
    {
        foreach ($ids as $id) {
            $this->options->delete($user, $productId, (string) $id);
        }
    }

    /** @param iterable<mixed> $ids */
    private function deleteSharedOptions(User $user, string $productId, iterable $ids): void
    {
        foreach ($ids as $id) {
            $this->sharedOptions->unassign($user, $productId, (string) $id);
        }
    }

    /** @param iterable<mixed> $ids */
    private function deleteModifiers(User $user, string $productId, iterable $ids): void
    {
        foreach ($ids as $id) {
            $this->modifiers->remove($user, $productId, (string) $id);
        }
    }

    /** @param iterable<mixed> $ids */
    private function deleteModifierGroups(User $user, string $productId, iterable $ids): void
    {
        foreach ($ids as $id) {
            $this->modifiers->deleteGroup($user, $productId, (string) $id);
        }
    }

    /** @param iterable<array<string, mixed>> $items */
    private function deleteOptionValues(User $user, string $productId, iterable $items, ProductDetailReferenceMap $refs): void
    {
        foreach ($items as $item) {
            $this->options->deleteValue(
                $user,
                $productId,
                $this->resolve((string) $item['option_id'], 'options', $refs),
                (string) $item['id'],
            );
        }
    }

    /** @param iterable<array<string, mixed>> $items */
    private function deleteCustomFields(User $user, string $productId, iterable $items, ProductDetailReferenceMap $refs): void
    {
        foreach ($items as $item) {
            $variantId = $this->nullableReference($item['variant_id'] ?? null, 'variants', $refs);
            $this->customFields->deleteValue($user, $productId, (string) $item['definition_id'], $variantId);
        }
    }

    /** @param array<string, mixed> $section */
    private function deleteMedia(User $user, string $productId, array $section, ProductDetailReferenceMap $refs): void
    {
        foreach ($section['detach'] ?? [] as $mediaId) {
            $this->media->detachFromProduct($user, $productId, (string) $mediaId);
        }
        foreach ($section['variant_detach'] ?? [] as $item) {
            $this->media->detachFromProductVariant(
                $user,
                $this->resolve((string) $item['variant_id'], 'variants', $refs),
                (string) $item['media_id'],
            );
        }
    }

    /** @param iterable<array<string, mixed>> $items */
    private function upsertOptions(User $user, string $productId, iterable $items, ProductDetailReferenceMap $refs): void
    {
        foreach ($items as $item) {
            $id = $item['id'] ?? null;
            $input = Arr::except($item, ['id', 'ref']);
            if ($id !== null && array_key_exists('values', $input)) {
                throw ValidationException::withMessages([
                    'sections.options.upsert' => [
                        'Use value_upsert/value_delete when changing Values on an existing Product Option.',
                    ],
                ]);
            }
            $option = $id === null
                ? $this->options->create($user, $productId, $input)
                : $this->options->update($user, $productId, (string) $id, $input);
            $this->register($refs, 'options', $item['ref'] ?? null, (string) $option->public_id);

            if ($id === null && isset($item['values'])) {
                $createdValues = $option->values->sortBy('id')->values();
                foreach ($item['values'] as $index => $valueInput) {
                    $value = $createdValues->get($index);
                    if ($value !== null) {
                        $this->register(
                            $refs,
                            'option_values',
                            $valueInput['ref'] ?? null,
                            (string) $value->public_id,
                        );
                    }
                }
            }
        }
    }

    /** @param iterable<array<string, mixed>> $items */
    private function upsertOptionValues(User $user, string $productId, iterable $items, ProductDetailReferenceMap $refs): void
    {
        foreach ($items as $item) {
            $optionId = $this->resolve((string) $item['option_id'], 'options', $refs);
            $id = $item['id'] ?? null;
            $input = Arr::except($item, ['option_id', 'id', 'ref']);
            $value = $id === null
                ? $this->options->createValue($user, $productId, $optionId, $input)
                : $this->options->updateValue($user, $productId, $optionId, (string) $id, $input);
            $this->register($refs, 'option_values', $item['ref'] ?? null, (string) $value->public_id);
        }
    }

    /** @param iterable<array<string, mixed>> $items */
    private function upsertVariants(User $user, string $productId, iterable $items, ProductDetailReferenceMap $refs): void
    {
        foreach ($items as $item) {
            $id = $item['id'] ?? null;
            $input = Arr::except($item, ['id', 'ref']);
            if (isset($input['option_value_ids'])) {
                $input['option_value_ids'] = array_map(
                    fn (mixed $value): string => $this->resolve((string) $value, 'option_values', $refs),
                    $input['option_value_ids'],
                );
            }
            $variant = $id === null
                ? $this->variants->create($user, $productId, $input)
                : $this->variants->update($user, $productId, (string) $id, $input);
            $this->register($refs, 'variants', $item['ref'] ?? null, (string) $variant->public_id);
        }
    }

    /** @param iterable<array<string, mixed>> $items */
    private function upsertImages(User $user, string $productId, iterable $items, ProductDetailReferenceMap $refs): void
    {
        foreach ($items as $item) {
            $id = $item['id'] ?? null;
            $input = $this->imageInput(Arr::except($item, ['id']));
            if (array_key_exists('variantId', $input)) {
                $input['variantId'] = $this->nullableReference($input['variantId'], 'variants', $refs);
            }
            $id === null
                ? $this->images->create($user, $productId, $input)
                : $this->images->update($user, $productId, (string) $id, $input);
        }
    }

    /** @param iterable<array<string, mixed>> $items */
    private function upsertCustomFields(User $user, string $productId, iterable $items, ProductDetailReferenceMap $refs): void
    {
        foreach ($items as $item) {
            $variantId = $this->nullableReference($item['variant_id'] ?? null, 'variants', $refs);
            $this->customFields->setValue(
                $user,
                $productId,
                (string) $item['definition_id'],
                Arr::except($item, ['definition_id', 'variant_id']),
                $variantId,
            );
        }
    }

    /** @param iterable<array<string, mixed>> $items */
    private function upsertModifierGroups(User $user, string $productId, iterable $items, ProductDetailReferenceMap $refs): void
    {
        foreach ($items as $item) {
            $id = $item['id'] ?? null;
            $input = Arr::except($item, ['id', 'ref']);
            $group = $id === null
                ? $this->modifiers->createGroup($user, $productId, $input)
                : $this->modifiers->updateGroup($user, $productId, (string) $id, $input);
            $this->register($refs, 'modifier_groups', $item['ref'] ?? null, (string) $group->public_id);
        }
    }

    /** @param iterable<array<string, mixed>> $items */
    private function upsertSharedOptions(User $user, string $productId, iterable $items): void
    {
        foreach ($items as $item) {
            $this->sharedOptions->assign($user, $productId, $item);
        }
    }

    /** @param iterable<array<string, mixed>> $items */
    private function upsertModifiers(User $user, string $productId, iterable $items, ProductDetailReferenceMap $refs): void
    {
        foreach ($items as $item) {
            $id = $item['id'] ?? null;
            $input = Arr::except($item, ['id']);
            if (array_key_exists('group_id', $input)) {
                $input['group_id'] = $this->nullableReference($input['group_id'], 'modifier_groups', $refs);
            }
            $id === null
                ? $this->modifiers->assign($user, $productId, $input)
                : $this->modifiers->update($user, $productId, (string) $id, $input);
        }
    }

    /** @param array<string, mixed> $section */
    private function attachMedia(User $user, string $productId, array $section, ProductDetailReferenceMap $refs): void
    {
        foreach ($section['attach'] ?? [] as $item) {
            $this->media->attachToProduct(
                $user,
                $productId,
                (string) $item['media_id'],
                (int) ($item['sort_order'] ?? 0),
                array_key_exists('is_primary', $item) ? (bool) $item['is_primary'] : null,
            );
        }
        foreach ($section['variant_attach'] ?? [] as $item) {
            $this->media->attachToProductVariant(
                $user,
                $this->resolve((string) $item['variant_id'], 'variants', $refs),
                (string) $item['media_id'],
                (int) ($item['sort_order'] ?? 0),
            );
        }
        if (($section['primary_media_id'] ?? null) !== null) {
            $this->media->setPrimaryProductMedia($user, $productId, (string) $section['primary_media_id']);
        }
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function imageInput(array $data): array
    {
        $input = [];
        foreach ([
            'variant_id' => 'variantId', 'url' => 'url', 'width' => 'width',
            'height' => 'height', 'position' => 'position',
        ] as $rest => $internal) {
            if (array_key_exists($rest, $data)) {
                $input[$internal] = $data[$rest];
            }
        }
        if (array_key_exists('translations', $data)) {
            if (! is_array($data['translations'])) {
                $input['translations'] = $data['translations'];

                return $input;
            }
            $input['translations'] = array_map(static function (mixed $translation): array {
                if (! is_array($translation)) {
                    return [];
                }

                return [
                    ...array_key_exists('locale', $translation) ? ['locale' => $translation['locale']] : [],
                    ...array_key_exists('alt_text', $translation) ? ['altText' => $translation['alt_text']] : [],
                    ...array_key_exists('lock_it', $translation) ? ['lockIt' => $translation['lock_it']] : [],
                ];
            }, $data['translations']);
        }

        return $input;
    }

    private function resolve(string $value, string $type, ProductDetailReferenceMap $refs): string
    {
        return $refs->resolve($type, $value);
    }

    private function nullableReference(mixed $value, string $type, ProductDetailReferenceMap $refs): ?string
    {
        return $refs->nullable($type, $value);
    }

    private function register(ProductDetailReferenceMap $refs, string $type, mixed $reference, string $publicId): void
    {
        $refs->register($type, $reference, $publicId);
    }
}
