<?php
declare(strict_types=1);

$vendor = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($vendor)) {
    require $vendor;
} else {
    spl_autoload_register(static function (string $class): void {
        $map = ['LeafPHP\\' => dirname(__DIR__) . '/system/', 'App\\' => dirname(__DIR__) . '/app/'];
        foreach ($map as $prefix => $root) {
            if (strncmp($class, $prefix, strlen($prefix)) !== 0) continue;
            $relative = substr($class, strlen($prefix));
            $file = $root . str_replace('\\', '/', $relative) . '.php';
            if (is_file($file)) require $file;
            return;
        }
    });
}
require_once dirname(__DIR__) . '/system/Core/helpers.php';
