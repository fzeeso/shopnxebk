<?php

declare(strict_types=1);

return [
    'disk' => env('MEDIA_DISK', 'private'),

    'allowed_disks' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('MEDIA_ALLOWED_DISKS', 'private,public,s3')),
    ))),

    'max_file_size_kb' => (int) env('MEDIA_MAX_FILE_SIZE_KB', 10240),

    'allowed_mime_types' => [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'image/webp' => ['webp'],
        'image/avif' => ['avif'],
    ],

    'variants' => [
        'thumbnail' => 240,
        'small' => 480,
        'medium' => 960,
        'large' => 1600,
    ],

    'quality' => (int) env('MEDIA_IMAGE_QUALITY', 85),

    'queue' => [
        'connection' => env('QUEUE_CONNECTION', 'redis'),
        'name' => env('MEDIA_QUEUE', 'media'),
    ],
];
