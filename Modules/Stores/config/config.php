<?php

declare(strict_types=1);

return [
    'name' => 'Stores',
    'admin_dashboard_url' => env(
        'STORE_ADMIN_DASHBOARD_URL',
        rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/').'/dashboard',
    ),
    'platform_domain' => env('STOREFRONT_ROOT_DOMAIN', 'shopnxe.com'),
    'default_theme_key' => env('STORE_DEFAULT_THEME_KEY', 'default'),
];
