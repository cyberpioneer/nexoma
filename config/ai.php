<?php

/*
|--------------------------------------------------------------------------
| Laravel AI SDK: Nexoma-Konfiguration
|--------------------------------------------------------------------------
|
| Nexoma nutzt ausschliesslich MiniMax ueber den OpenAI-kompatiblen Endpunkt
| (https://platform.minimax.io/docs/api-reference/text-openai-api).
| Zugangsdaten stehen nur in .env: OPENAI_COMPATIBLE_API_KEY, OPENAI_COMPATIBLE_URL
| (z. B. https://api.minimax.io/v1) und optional OPENAI_COMPATIBLE_MODEL.
| Die Datei ersetzt den Abschnitt `providers` der SDK-Vorgabe vollstaendig.
|
*/

return [

    'default' => 'openai-compatible',

    'providers' => [
        'openai-compatible' => [
            'driver' => 'openai-compatible',
            'url' => env('OPENAI_COMPATIBLE_URL'),
            'key' => env('OPENAI_COMPATIBLE_API_KEY'),
            'models' => [
                'text' => ['default' => env('OPENAI_COMPATIBLE_MODEL', 'MiniMax-M3')],
            ],
        ],
    ],

];
