<?php
declare(strict_types=1);

namespace LeafPHP\Core;

/** Small wrapper around native PHP sessions. */
final class Session
{
    public function __construct() { if (session_status() !== PHP_SESSION_ACTIVE) session_start(); }
    public function get(string $key, mixed $default = null): mixed { return $_SESSION[$key] ?? $default; }
    public function put(string $key, mixed $value): void { $_SESSION[$key] = $value; }
    public function has(string $key): bool { return array_key_exists($key, $_SESSION); }
    public function forget(string $key): void { unset($_SESSION[$key]); }
    public function flush(): void { $_SESSION = []; }
    public function flash(string $key, mixed $value): void { $_SESSION['_flash_next'][$key] = $value; }
    public function ageFlashData(): void {
        unset($_SESSION['_flash_current']);
        $_SESSION['_flash_current'] = $_SESSION['_flash_next'] ?? [];
        unset($_SESSION['_flash_next']);
    }
    public function flashed(string $key, mixed $default = null): mixed { return $_SESSION['_flash_current'][$key] ?? $default; }
    public function regenerate(bool $deleteOld = true): void { session_regenerate_id($deleteOld); }
    public function invalidate(): void { $_SESSION = []; session_regenerate_id(true); }
}
