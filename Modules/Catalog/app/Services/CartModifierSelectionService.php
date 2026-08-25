<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use App\Models\Media;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Models\CartItemModifierSelection;
use Modules\Catalog\Models\ModifierValue;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductModifierAssignment;
use Modules\Stores\Models\Store;

final readonly class CartModifierSelectionService
{
    public function __construct(
        private ProductModifierResolver $resolver,
        private ModifierSelectionValidator $validator,
        private ModifierPricingResolver $pricing,
    ) {}

    /**
     * Replaces selection rows for a cart item. Client prices are deliberately
     * absent from the accepted input and every amount is recalculated here.
     *
     * @param  list<array{assignment_id: string, value_id?: string|null, input_value?: array<string, mixed>|null}>  $selections
     * @return list<CartItemModifierSelection>
     */
    public function replace(
        Store $store,
        Product $product,
        int $cartItemId,
        array $selections,
        string $locale,
        string $currency,
        ?int $channelId = null,
        ?int $customerGroupId = null,
    ): array {
        if ((int) $product->store_id !== (int) $store->getKey()) {
            throw ValidationException::withMessages(['product_id' => ['The product does not belong to this Store.']]);
        }
        $configuration = collect($this->resolver->resolve($store, $product, $locale, $currency, $channelId, $customerGroupId))->keyBy('id');
        $grouped = collect($selections)->groupBy('assignment_id');
        $errors = [];
        foreach ($configuration as $assignmentId => $modifier) {
            $candidate = $grouped->get($assignmentId, collect())->map(fn (array $row): array => [
                'value_id' => $row['value_id'] ?? null, 'input_value' => $row['input_value'] ?? null,
            ])->values()->all();
            foreach ($this->validator->validate($modifier, $candidate) as $path => $messages) {
                $errors["modifiers.{$assignmentId}.{$path}"] = $messages;
            }
            foreach ($this->validateAssets($store, $modifier, $candidate) as $path => $messages) {
                $errors["modifiers.{$assignmentId}.{$path}"] = $messages;
            }
        }
        foreach ($grouped->keys() as $assignmentId) {
            if (! $configuration->has($assignmentId)) {
                $errors["modifiers.{$assignmentId}"] = ['The modifier assignment is not available for this product.'];
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($cartItemId, $channelId, $configuration, $currency, $customerGroupId, $grouped, $product, $store): array {
            CartItemModifierSelection::query()->where('store_id', $store->getKey())->where('cart_item_id', $cartItemId)->delete();
            $created = [];
            foreach ($configuration as $assignmentId => $modifierConfiguration) {
                /** @var ProductModifierAssignment $assignment */
                $assignment = ProductModifierAssignment::query()->where('store_id', $store->getKey())->where('product_id', $product->getKey())->where('public_id', $assignmentId)
                    ->with(['modifier.priceAdjustments', 'priceOverrides', 'valuePriceOverrides'])->firstOrFail();
                foreach ($grouped->get($assignmentId, collect())->values() as $index => $selection) {
                    $value = ($selection['value_id'] ?? null) === null ? null : ModifierValue::query()
                        ->where('store_id', $store->getKey())->where('modifier_id', $assignment->modifier_id)->where('public_id', $selection['value_id'])
                        ->with('priceAdjustments')->firstOrFail();
                    $price = $this->pricing->resolve($assignment, $assignment->modifier, $value, $currency, (string) ($product->price ?? 0), $channelId, $customerGroupId, null, $index === 0);
                    $created[] = CartItemModifierSelection::query()->create([
                        'store_id' => $store->getKey(), 'cart_item_id' => $cartItemId,
                        'product_modifier_assignment_id' => $assignment->getKey(), 'modifier_id' => $assignment->modifier_id,
                        'modifier_value_id' => $value?->getKey(), 'input_value' => $selection['input_value'] ?? null,
                        'price_adjustment' => $price['amount'], 'currency_code' => $price['currency'],
                    ]);
                }
            }

            return $created;
        });
    }

    /**
     * @param  array<string, mixed>  $configuration
     * @param  list<array<string, mixed>>  $selections
     * @return array<string, list<string>>
     */
    private function validateAssets(Store $store, array $configuration, array $selections): array
    {
        if (! in_array($configuration['type'] ?? null, ['file', 'image_upload'], true)) {
            return [];
        }
        $ids = collect($selections)->flatMap(fn (array $selection): array => (array) ($selection['input_value']['asset_ids'] ?? []))->unique()->values();
        $media = Media::query()->where('store_id', $store->getKey())->where('status', 'ready')->whereIn('public_id', $ids)->get()->keyBy('public_id');
        if ($media->count() !== $ids->count()) {
            return ['selections' => [$this->validationMessage($configuration, 'Every selected asset must belong to this Store.')]];
        }
        $errors = [];
        foreach ($media as $asset) {
            if (($configuration['type'] ?? null) === 'image_upload' && ! str_starts_with((string) $asset->mime_type, 'image/')) {
                $errors['selections'][] = $this->validationMessage($configuration, 'Image-upload modifiers only accept image assets.');
            }
            foreach ($configuration['validationRules'] ?? [] as $rule) {
                $value = (array) ($rule['value'] ?? []);
                if (($rule['type'] ?? null) === 'allowed_file_extensions' && ! in_array(strtolower((string) $asset->extension), array_map('strtolower', (array) ($value['extensions'] ?? [])), true)) {
                    $errors['selections'][] = trim((string) ($rule['message'] ?? '')) ?: $this->validationMessage($configuration, 'The selected file extension is not allowed.');
                }
                if (($rule['type'] ?? null) === 'max_file_size' && (int) $asset->size > (int) ($value['bytes'] ?? 0)) {
                    $errors['selections'][] = trim((string) ($rule['message'] ?? '')) ?: $this->validationMessage($configuration, 'The selected file is too large.');
                }
            }
        }

        return $errors;
    }

    /** @param array<string, mixed> $configuration */
    private function validationMessage(array $configuration, string $fallback): string
    {
        $message = trim((string) ($configuration['validationMessage'] ?? ''));

        return $message !== '' ? $message : $fallback;
    }
}
