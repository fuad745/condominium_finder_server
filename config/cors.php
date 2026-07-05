<?php

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    // Production: the Flutter web build's origin. Local dev additionally
    // accepts any localhost port (flutter run -d chrome picks a random one).
    'allowed_origins' => array_values(array_filter([
        env('CONDO_WEB_APP_URL'),
    ])),

    'allowed_origins_patterns' => env('APP_ENV') === 'production'
        ? []
        : ['#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#'],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => false,

];
