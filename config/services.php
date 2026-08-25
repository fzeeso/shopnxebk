<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'translation_model' => env('OPENAI_TRANSLATION_MODEL', 'gpt-5-mini'),
        'translation_timeout' => (int) env('OPENAI_TRANSLATION_TIMEOUT', 180),
        'translation_max_output_tokens' => (int) env('OPENAI_TRANSLATION_MAX_OUTPUT_TOKENS', 16000),
        'media_image_model' => env('OPENAI_MEDIA_IMAGE_MODEL', 'gpt-image-2'),
        'media_analysis_model' => env('OPENAI_MEDIA_ANALYSIS_MODEL', 'gpt-5-mini'),
        'media_timeout' => (int) env('OPENAI_MEDIA_TIMEOUT', 240),
        'media_max_output_tokens' => (int) env('OPENAI_MEDIA_MAX_OUTPUT_TOKENS', 2000),
        'media_quality' => env('OPENAI_MEDIA_QUALITY', 'medium'),
        'media_max_output_bytes' => (int) env('OPENAI_MEDIA_MAX_OUTPUT_BYTES', 20971520),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
