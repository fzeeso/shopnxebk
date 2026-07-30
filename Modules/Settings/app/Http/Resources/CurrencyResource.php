<?php

declare(strict_types=1);

namespace Modules\Settings\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Settings\Models\Currency;

/** @extends JsonResource<Currency> */
final class CurrencyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'code' => $this->code,
            'symbol' => $this->symbol,
            'symbol_position' => $this->symbol_position,
            'decimal_places' => $this->decimal_places,
            'usd_exchange_rate' => $this->usd_exchange_rate === null
                ? null
                : (string) $this->usd_exchange_rate,
            'is_base' => (bool) $this->is_base,
            'is_active' => (bool) $this->is_active,
            'exchange_rate_updated_at' => $this->exchange_rate_updated_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
