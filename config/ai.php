<?php

/*
|--------------------------------------------------------------------------
| KI-Anbieter
|--------------------------------------------------------------------------
|
| Nexoma nutzt ausschliesslich den IONOS AI Model Hub (Rechenzentrum in Deutschland) ueber den
| OpenAI-kompatiblen Endpunkt des Laravel AI SDK (Treiber `openai-compatible`).
| Zugangsdaten und Modelle stehen nur in .env: IONOS_AI_BASE_URL, IONOS_AI_KEY sowie die Modelle
| IONOS_AI_MODEL_DEFAULT (Standard), IONOS_AI_MODEL_FAST (guenstigstes) und IONOS_AI_MODEL_SMART (staerkstes).
|
*/

return [

    'default' => 'ionos',

    'providers' => [
        'ionos' => [
            'driver' => 'openai-compatible',
            'url' => env('IONOS_AI_BASE_URL'),
            'key' => env('IONOS_AI_KEY'),
            'models' => [
                'text' => [
                    'default' => env('IONOS_AI_MODEL_DEFAULT', 'mistralai/Mistral-Small-24B-Instruct'),
                    'cheapest' => env('IONOS_AI_MODEL_FAST'),
                    'smartest' => env('IONOS_AI_MODEL_SMART'),
                ],
            ],
        ],
    ],

];
