<?php

declare(strict_types=1);

namespace Modules\Stores\Services;

use Modules\Stores\Models\Store;

final readonly class StoreDashboardUrl
{
    public function for(Store $store): string
    {
        $url = (string) config('stores.admin_dashboard_url');
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.'store='.rawurlencode((string) $store->public_id);
    }
}
