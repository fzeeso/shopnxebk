<?php

declare(strict_types=1);

namespace Modules\Customers\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Customers\Models\Customer;

/** @extends JsonResource<Customer> */
final class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'customer_group_id' => $this->group?->public_id,
            'email' => $this->email,
            'company' => $this->company,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => $this->phone,
            'status' => $this->status->value,
            'registered_ip' => $this->registered_ip,
            'admin_notes' => $this->admin_notes,
            'points_balance' => $this->points_balance,
            'redeemed_points' => $this->redeemed_points,
            'credit_balance' => (string) ($this->getAttribute('credits_sum_amount') ?? '0.0000'),
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'joined_at' => $this->joined_at?->toIso8601String(),
            'last_activity_at' => $this->last_activity_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
