<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'jobsuche' => [
        'base_url' => env('JOBSUCHE_BASE_URL', 'https://rest.arbeitsagentur.de/jobboerse/jobsuche-service'),
        'api_key' => env('JOBSUCHE_API_KEY', 'jobboerse-jobsuche'),
        'timeout' => (int) env('JOBSUCHE_TIMEOUT', 10),
        'connect_timeout' => (int) env('JOBSUCHE_CONNECT_TIMEOUT', 5),
        'global_limit_per_minute' => (int) env('JOBSUCHE_GLOBAL_LIMIT_PER_MINUTE', 0),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        // Einziger verkaeuflicher Plan; die Preis-ID kommt nie aus einer Anfrage.
        'price_id' => env('STRIPE_PRICE_ID'),
        // Tage, die ein Abo nach dem Periodenende noch gilt, falls der Verlaengerungs-Webhook verspaetet eintrifft.
        'grace_days' => (int) env('STRIPE_GRACE_DAYS', 3),
    ],

    'piper' => [
        'python_binary' => env('PIPER_PYTHON_BINARY', '/opt/piper/venv/bin/python3'),
        'data_dir' => env('PIPER_DATA_DIR', '/opt/piper/voices'),
        'timeout' => (int) env('PIPER_TIMEOUT', 300),
        // Disk `local` zeigt auf storage/app/private (nicht oeffentlich erreichbar).
        'disk' => 'local',
        'directory' => 'audio-briefings',
        // Feste Whitelist Sprache => Stimme (Dateiname ohne .onnx in `data_dir`); nie aus Benutzereingaben.
        'voices' => [
            'de' => 'de_DE-thorsten-high',
            'en' => 'en_US-lessac-high',
            'es' => 'es_ES-davefx-medium',
            'it' => 'it_IT-paola-medium',
            'ru' => 'ru_RU-dmitri-medium',
            'uk' => 'uk_UA-ukrainian_tts-medium',
        ],
    ],

];
