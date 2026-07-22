<?php

declare(strict_types=1);

return [
    'internal_dashboards_enabled' => (bool) env('INTERNAL_DASHBOARDS_ENABLED', false),
    'internal_dashboard_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('INTERNAL_DASHBOARD_IP_ALLOW_LIST', ''))))),
    'telescope_enabled' => (bool) env('TELESCOPE_ENABLED', false),
    'meilisearch_required' => (bool) env('MEILISEARCH_REQUIRED', false),
];
