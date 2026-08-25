<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use Illuminate\Support\Facades\DB;
use LogicException;
use Modules\Catalog\Models\CartItemModifierSelection;
use Modules\Catalog\Models\OrderItemModifierSnapshot;
use Modules\Stores\Models\Store;

final readonly class OrderModifierSnapshotService
{
    public function __construct(private ModifierTranslationResolver $translations) {}

    /** @return list<OrderItemModifierSnapshot> */
    public function create(Store $store, int $cartItemId, int $orderItemId, string $locale): array
    {
        if (OrderItemModifierSnapshot::query()->where('store_id', $store->getKey())->where('order_item_id', $orderItemId)->exists()) {
            throw new LogicException('Order modifier snapshots are immutable and may only be created once.');
        }
        $selections = CartItemModifierSelection::query()->where('store_id', $store->getKey())->where('cart_item_id', $cartItemId)
            ->with(['assignment.translations', 'modifier.translations', 'value.translations'])->orderBy('id')->get();

        return DB::transaction(function () use ($locale, $orderItemId, $selections, $store): array {
            $snapshots = [];
            foreach ($selections as $selection) {
                $copy = $this->translations->resolveModifier(
                    $selection->assignment->translations,
                    $selection->modifier->translations,
                    $locale,
                    (string) $store->language_code,
                    (string) $selection->modifier->code,
                );
                $snapshots[] = OrderItemModifierSnapshot::query()->create([
                    'store_id' => $store->getKey(), 'order_item_id' => $orderItemId,
                    'modifier_id' => $selection->modifier_id, 'modifier_value_id' => $selection->modifier_value_id,
                    'modifier_public_id' => $selection->modifier->public_id, 'value_public_id' => $selection->value?->public_id,
                    'modifier_code' => $selection->modifier->code, 'modifier_name' => $copy['name'],
                    'value_code' => $selection->value?->code,
                    'value_name' => $selection->value === null ? null : $this->translations->resolveValueName($selection->value->translations, $locale, (string) $store->language_code, (string) $selection->value->code),
                    'input_value' => $selection->input_value, 'price_adjustment' => $selection->price_adjustment,
                    'currency_code' => $selection->currency_code, 'locale' => $locale,
                    'metadata' => ['assignment_public_id' => $selection->assignment->public_id], 'created_at' => now(),
                ]);
            }

            return $snapshots;
        });
    }
}
