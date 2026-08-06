<?php

declare(strict_types=1);

namespace Modules\Stores\Actions;

use Modules\Stores\Models\PolicyType;

final class EnsurePolicyTypeCatalog
{
    /** @var list<array{code: string, name: string, description: string, sort_order: int}> */
    private const TYPES = [
        ['code' => 'privacy', 'name' => 'Privacy Policy', 'description' => 'Explains how customer and visitor data is collected, used, and protected.', 'sort_order' => 10],
        ['code' => 'refund', 'name' => 'Refund Policy', 'description' => 'Defines refund eligibility, timing, and processing rules.', 'sort_order' => 20],
        ['code' => 'shipping', 'name' => 'Shipping Policy', 'description' => 'Describes shipping methods, costs, destinations, and delivery estimates.', 'sort_order' => 30],
        ['code' => 'terms', 'name' => 'Terms of Service', 'description' => 'Sets the terms governing use of the Store and purchases.', 'sort_order' => 40],
        ['code' => 'contact', 'name' => 'Contact Information', 'description' => 'Publishes customer-service and business contact information.', 'sort_order' => 50],
        ['code' => 'cookie', 'name' => 'Cookie Policy', 'description' => 'Explains the Store use of cookies and similar technologies.', 'sort_order' => 60],
        ['code' => 'billing', 'name' => 'Billing Policy', 'description' => 'Defines billing, payment, invoicing, and charge-handling practices.', 'sort_order' => 70],
        ['code' => 'cancellation', 'name' => 'Cancellation Policy', 'description' => 'Defines order or service cancellation eligibility and timing.', 'sort_order' => 80],
    ];

    public function ensure(): void
    {
        foreach (self::TYPES as $type) {
            PolicyType::query()->updateOrCreate(
                ['code' => $type['code']],
                [...$type, 'is_system' => true],
            );
        }
    }
}
