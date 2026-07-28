<?php

declare(strict_types=1);

namespace Modules\Stores\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class StoreCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly int $storeId, public readonly int $ownerId) {}
}
