<?php

declare(strict_types=1);

namespace Modules\Customers\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Customers\Models\CustomerCredit;

/** @extends JsonResource<CustomerCredit> */
final class CustomerCreditResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'amount' => $this->amount,
            'type' => $this->type->value,
            'external_reference' => $this->external_reference,
            'reason' => $this->reason,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'created_by' => $this->whenLoaded('createdBy', fn () => $this->createdBy === null ? null : [
                'id' => $this->createdBy->public_id,
                'name' => $this->createdBy->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
