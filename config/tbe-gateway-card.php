<?php

return [
    'disk' => env('GATEWAY_CARD_DISK', 'local'),

    'routes' => [
        'enabled' => env('GATEWAY_CARD_ROUTES_ENABLED', true),
        'api_prefix' => env('GATEWAY_CARD_ROUTES_API_PREFIX', 'api'),
    ],
];
