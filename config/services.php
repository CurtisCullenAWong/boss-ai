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

    // 'postmark' => [
    //     'key' => env('POSTMARK_API_KEY'),
    // ],

    // 'resend' => [
    //     'key' => env('RESEND_API_KEY'),
    // ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // 'slack' => [
    //     'notifications' => [
    //         'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
    //         'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
    //     ],
    // ],

    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://localhost:11434'),
        'username' => env('OLLAMA_USERNAME'),
        'base_model' => env('OLLAMA_BASE_MODEL'),
        'cloud_base_model' => env('OLLAMA_CLOUD_MODEL'),
        'model' => env('OLLAMA_MODEL'),
        'cloud_model' => env('OLLAMA_CLOUD_MODEL'),
        'cache_ttl' => (int) env('OLLAMA_CACHE_TTL', 3600),
        'options' => [
            'num_ctx' => (int) env('OLLAMA_NUM_CTX', 2048),
            'temperature' => (float) env('OLLAMA_TEMPERATURE', 0.0),
            'top_p' => (float) env('OLLAMA_TOP_P', 0.1),
            'repeat_penalty' => (float) env('OLLAMA_REPEAT_PENALTY', 1.1),
        ],
    ],

];
