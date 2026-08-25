<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Modules\Catalog\Models\ModifierDefinition;
use Modules\Catalog\Models\ModifierValue;
use Modules\Catalog\Models\ProductModifierAssignment;

final class ModifierPricingResolver
{
    /**
     * Product rows override the corresponding library component. Modifier and
     * value components are then added; percentages use the product base price.
     *
     * @return array{amount: string, currency: string}
     */
    public function resolve(
        ProductModifierAssignment $assignment,
        ModifierDefinition $modifier,
        ?ModifierValue $value,
        string $currency,
        string|int|float $basePrice,
        ?int $channelId = null,
        ?int $customerGroupId = null,
        ?DateTimeInterface $at = null,
        bool $includeModifierAdjustment = true,
    ): array {
        $modifierRow = $this->pick(
            $assignment->priceOverrides,
            $currency,
            $channelId,
            $customerGroupId,
            $at,
        ) ?? $this->pick($modifier->priceAdjustments, $currency, $channelId, $customerGroupId, $at);
        $valueRow = $value === null ? null : ($this->pick(
            $assignment->valuePriceOverrides->where('modifier_value_id', $value->getKey()),
            $currency,
            $channelId,
            $customerGroupId,
            $at,
        ) ?? $this->pick($value->priceAdjustments, $currency, $channelId, $customerGroupId, $at));

        $amount = ($includeModifierAdjustment ? $this->calculate($modifierRow, $basePrice) : 0.0)
            + $this->calculate($valueRow, $basePrice);

        return ['amount' => number_format($amount, 4, '.', ''), 'currency' => strtoupper($currency)];
    }

    /**
     * @param  iterable<array<string, mixed>|object>  $rows
     * @return array<string, mixed>|object|null
     */
    public function pick(
        iterable $rows,
        string $currency,
        ?int $channelId = null,
        ?int $customerGroupId = null,
        ?DateTimeInterface $at = null,
    ): array|object|null {
        $now = CarbonImmutable::instance($at ?? new CarbonImmutable);
        $eligible = Collection::make($rows)->filter(function (array|object $row) use ($currency, $channelId, $customerGroupId, $now): bool {
            $value = static fn (string $key): mixed => is_array($row) ? ($row[$key] ?? null) : ($row->{$key} ?? null);
            $startsAt = $value('starts_at');
            $endsAt = $value('ends_at');

            return (bool) ($value('is_active') ?? true)
                && strtoupper((string) $value('currency_code')) === strtoupper($currency)
                && ($value('channel_id') === null || (int) $value('channel_id') === $channelId)
                && ($value('customer_group_id') === null || (int) $value('customer_group_id') === $customerGroupId)
                && ($startsAt === null || CarbonImmutable::parse($startsAt)->lessThanOrEqualTo($now))
                && ($endsAt === null || CarbonImmutable::parse($endsAt)->greaterThanOrEqualTo($now));
        })->sortByDesc(function (array|object $row): array {
            $value = static fn (string $key): mixed => is_array($row) ? ($row[$key] ?? null) : ($row->{$key} ?? null);

            return [
                ($value('channel_id') !== null ? 2 : 0) + ($value('customer_group_id') !== null ? 1 : 0),
                (int) ($value('id') ?? 0),
            ];
        });

        return $eligible->first();
    }

    /** @param array<string, mixed>|object|null $row */
    private function calculate(array|object|null $row, string|int|float $basePrice): float
    {
        if ($row === null) {
            return 0.0;
        }
        $type = (string) (is_array($row) ? ($row['adjustment_type'] ?? '') : $row->adjustment_type);
        $amount = (float) (is_array($row) ? ($row['amount'] ?? 0) : $row->amount);

        return $type === 'percentage' ? ((float) $basePrice * $amount / 100) : $amount;
    }
}
