<?php
declare(strict_types=1);

use LeafPHP\Core\Response;
use LeafPHP\Core\Request;
use LeafPHP\Core\Session;
use LeafPHP\Core\RedirectResponse;
use LeafPHP\Core\View;
use LeafPHP\Core\Validator;
use LeafPHP\Database\DB;
use LeafPHP\Security\Auth;
use LeafPHP\Security\Hash;
use LeafPHP\Core\Router;

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed {
        static $loaded = false;
        static $values = [];
        static $external = [];
        if (!$loaded) {
            $external = $_ENV;
            $read = static function (string $path): array {
                $out = [];
                if (!is_file($path)) return $out;
                foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, '#')) continue;
                    if (str_starts_with($line, 'export ')) $line = substr($line, 7);
                    if (!str_contains($line, '=')) continue;
                    [$k, $v] = explode('=', $line, 2);
                    $k = trim($k); $v = trim($v);
                    if ($v !== '' && (($v[0] === '"' && str_ends_with($v, '"')) || ($v[0] === "'" && str_ends_with($v, "'")))) $v = substr($v, 1, -1);
                    $out[$k] = $v;
                }
                return $out;
            };
            $root = dirname(__DIR__, 2);
            $values = $read($root . '/.env');
            $environment = (string)(getenv('APP_ENV') ?: ($external['APP_ENV'] ?? $values['APP_ENV'] ?? 'local'));
            if (preg_match('/^[A-Za-z0-9_-]+$/', $environment)) $values = array_replace($values, $read($root . '/.env.' . $environment));
            $loaded = true;
        }
        $processValue = getenv($key);
        if ($processValue !== false) return $processValue;
        if (array_key_exists($key, $external)) return $external[$key];
        return $values[$key] ?? $default;
    }
}
if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed {
        static $items = [];
        [$file, $nested] = array_pad(explode('.', $key, 2), 2, null);
        if (!isset($items[$file])) { $path = dirname(__DIR__, 2) . '/app/config/' . $file . '.php'; $items[$file] = is_file($path) ? require $path : []; }
        $value = $items[$file];
        foreach ($nested ? explode('.', $nested) : [] as $segment) { if (!is_array($value) || !array_key_exists($segment, $value)) return $default; $value = $value[$segment]; }
        return $value ?? $default;
    }
}
if (!function_exists('dd')) {
    /** Dump one or more values in a readable format, then stop execution. */
    function dd(mixed ...$values): never {
        if (PHP_SAPI === 'cli') {
            foreach ($values as $value) { fwrite(STDOUT, print_r($value, true) . PHP_EOL); }
            exit(1);
        }
        if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dump and Die</title><style>body{margin:0;padding:28px;background:#f7f7f7;color:#171717;font:14px/1.6 system-ui,sans-serif}.wrap{max-width:1000px;margin:auto}h1{font-size:18px}pre{white-space:pre-wrap;overflow-wrap:anywhere;background:white;border:1px solid #ddd;border-radius:8px;padding:18px;overflow:auto}</style><main class="wrap"><h1>LeafPHP · Dump and Die</h1>';
        foreach ($values as $value) {
            ob_start(); var_dump($value); $dump = (string) ob_get_clean();
            echo '<pre>' . htmlspecialchars($dump, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';
        }
        echo '</main></html>';
        exit(1);
    }
}
if (!function_exists('e')) { function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); } }
if (!function_exists('base_url_path')) {
    /** Return the app's URL path prefix, such as /mylibrary, without a trailing slash. */
    function base_url_path(): string {
        $configured = (string) config('app.url', '');
        $configuredPath = parse_url($configured, PHP_URL_PATH);
        if (is_string($configuredPath) && $configuredPath !== '' && $configuredPath !== '/') {
            return '/' . trim($configuredPath, '/');
        }
        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        if (preg_match('~^(.*?)(?:/public)?/index\.php$~i', $script, $matches)) {
            $prefix = rtrim((string)($matches[1] ?? ''), '/');
            return $prefix === '' || $prefix === '/' ? '' : '/' . ltrim($prefix, '/');
        }
        return '';
    }
}
if (!function_exists('base_url')) {
    /** Build an absolute URL, respecting an app installed in a subdirectory. */
    function base_url(string $path = ''): string {
        $configured = rtrim((string) config('app.url', ''), '/');
        $schemePart = parse_url($configured, PHP_URL_SCHEME);
        $hostPart = parse_url($configured, PHP_URL_HOST);
        $portPart = parse_url($configured, PHP_URL_PORT);
        $origin = ($schemePart && $hostPart) ? $schemePart . '://' . $hostPart . ($portPart ? ':' . $portPart : '') : '';
        if ($origin === '' || !preg_match('~^https?://~i', $origin)) {
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
            $scheme = $https ? 'https' : 'http';
            $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
            $origin = $scheme . '://' . $host;
        }
        $prefix = base_url_path();
        $path = ltrim($path, '/');
        return $origin . $prefix . ($path !== '' ? '/' . $path : ($prefix === '' ? '/' : '/'));
    }
}
if (!function_exists('asset')) {
    function asset(string $path): string { return base_url('assets/' . ltrim($path, '/')); }
}
if (!function_exists('url')) {
    function url(string $path = ''): string { return base_url($path); }
}

if (!function_exists('view')) {
    function view(string $name, array $data = []): string { return View::render($name, $data); }
}
if (!function_exists('response')) { function response(): Response { return new Response(''); } }
if (!function_exists('csrf_token')) {
    function csrf_token(): string { if (session_status() !== PHP_SESSION_ACTIVE) session_start(); return $_SESSION['_csrf'] ??= bin2hex(random_bytes(32)); }
}
if (!function_exists('csrf_field')) { function csrf_field(): string { return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">'; } }


if (!function_exists('request')) {
    function request(): Request { static $request; return $request ??= Request::capture(); }
}
if (!function_exists('session')) {
    function session(): Session { static $session; return $session ??= new Session(); }
}
if (!function_exists('redirect')) {
    function redirect(string $to = '/', int $status = 302): RedirectResponse {
        if ($to !== '' && !preg_match('~^(?:[a-z][a-z0-9+.-]*:|//|#)~i', $to)) {
            $prefix = base_url_path();
            $to = $prefix . '/' . ltrim($to, '/');
            if ($to === '') $to = '/';
        }
        return new RedirectResponse($to, $status);
    }
}
if (!function_exists('back')) {
    function back(): RedirectResponse {
        $referer = (string)($_SERVER['HTTP_REFERER'] ?? '/');
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        $parts = parse_url($referer);
        // Only redirect to the same host to avoid open-redirect vulnerabilities.
        $path = ($parts === false || isset($parts['host']) && strcasecmp((string)$parts['host'], preg_replace('/:\d+$/', '', $host)) !== 0) ? '/' : (($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : ''));
        return redirect($path);
    }
}
if (!function_exists('validator')) {
    function validator(array $data, array $rules, array $messages = []): Validator { return Validator::make($data, $rules, $messages); }
}
if (!function_exists('errors')) {
    function errors(): array { return session()->flashed('_errors', []); }
}
if (!function_exists('old')) {
    function old(string $key, mixed $default = null): mixed { return session()->flashed('_old_input', [])[$key] ?? $default; }
}
if (!function_exists('session_flash')) {
    function session_flash(string $key, mixed $value): void { session()->flash($key, $value); }
}
if (!function_exists('abort')) {
    function abort(int $status = 404, string $message = ''): never { http_response_code($status); exit($message !== '' ? $message : match($status) { 403 => 'Forbidden', 404 => 'Not Found', 419 => 'Page Expired', default => 'Request Failed' }); }
}

if (!function_exists('hash_password')) { function hash_password(string $value): string { return Hash::make($value); } }
if (!function_exists('password_matches')) { function password_matches(string $value, string $hash): bool { return Hash::check($value, $hash); } }
if (!function_exists('auth')) { function auth(): Auth { static $instance; return $instance ??= new Auth(); } }
if (!function_exists('route')) { function route(string $name, array $parameters = []): string { return Router::url($name, $parameters); } }
if (!function_exists('db')) { function db(?string $connection = null): \LeafPHP\Database\DB { if ($connection !== null) DB::use($connection); return new \LeafPHP\Database\DB(); } }
