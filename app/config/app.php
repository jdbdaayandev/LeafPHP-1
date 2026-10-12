<?php
$environment = strtolower((string) env('APP_ENV', 'local'));
return [
    'name' => env('APP_NAME', 'LeafPHP'),
    'env' => $environment,
    'debug' => filter_var(env('APP_DEBUG', $environment !== 'production'), FILTER_VALIDATE_BOOL),
    'url' => env('APP_URL', ''),
    'key' => env('APP_KEY', ''),
    'timezone' => env('APP_TIMEZONE', 'Asia/Manila'),
    'session_driver' => env('SESSION_DRIVER', 'file'),
    'session_lifetime' => (int) env('SESSION_LIFETIME', 120),
];
