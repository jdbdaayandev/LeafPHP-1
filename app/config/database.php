<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$sqlitePath = (string) env('DB_SQLITE_DATABASE', 'database/database.sqlite');
if ($sqlitePath !== '' && !str_starts_with($sqlitePath, '/') && !(strlen($sqlitePath) >= 3 && ctype_alpha($sqlitePath[0]) && $sqlitePath[1] === ':' && ($sqlitePath[2] === '\\' || $sqlitePath[2] === '/'))) {
    $sqlitePath = $root . '/' . $sqlitePath;
}

$mysql = [
    'driver' => 'mysql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => (int) env('DB_PORT', 3306),
    'database' => env('DB_DATABASE', 'leafphp'),
    'username' => env('DB_USERNAME', 'root'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => env('DB_CHARSET', 'utf8mb4'),
];

return [
    'default' => env('DB_CONNECTION', 'sqlite'),
    'prefix' => (string) env('DB_PREFIX', ''),
    'connections' => [
        'sqlite' => ['driver' => 'sqlite', 'database' => $sqlitePath],
        'mysql' => $mysql,
        'mysql_reporting' => [
            'driver' => 'mysql',
            'host' => env('DB_REPORTING_HOST', env('DB_HOST', '127.0.0.1')),
            'port' => (int) env('DB_REPORTING_PORT', env('DB_PORT', 3306)),
            'database' => env('DB_REPORTING_DATABASE', 'leafphp_reporting'),
            'username' => env('DB_REPORTING_USERNAME', env('DB_USERNAME', 'root')),
            'password' => env('DB_REPORTING_PASSWORD', env('DB_PASSWORD', '')),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
        ],
        'pgsql' => [
            'driver' => 'pgsql', 'host' => env('DB_PGSQL_HOST', '127.0.0.1'),
            'port' => (int) env('DB_PGSQL_PORT', 5432), 'database' => env('DB_PGSQL_DATABASE', 'leafphp'),
            'username' => env('DB_PGSQL_USERNAME', 'postgres'), 'password' => env('DB_PGSQL_PASSWORD', ''),
        ],
    ],
    // Legacy top-level keys remain available for older integrations.
    ...$mysql,
];
