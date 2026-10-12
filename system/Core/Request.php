<?php
declare(strict_types=1);

namespace LeafPHP\Core;

/** Normalized HTTP request wrapper for form, query, JSON and file input. */
final class Request
{
    private function __construct(
        private array $query,
        private array $body,
        private array $server,
        private array $files,
        private array $cookies,
        private array $headers,
        private string $method,
        private string $uri,
        private string $rawBody
    ) {}

    public static function capture(): self
    {
        $raw = file_get_contents('php://input') ?: '';
        $contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
        $body = $_POST;
        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) $body = $decoded;
        }
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        return new self($_GET, $body, $_SERVER, $_FILES, $_COOKIE, $headers,
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            self::normalizePath(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'),
            $raw);
    }

    private static function normalizePath(string $path): string {
        if (function_exists('base_url_path')) {
            $prefix = base_url_path();
            if ($prefix !== '' && ($path === $prefix || str_starts_with($path, $prefix . '/'))) {
                $path = substr($path, strlen($prefix)) ?: '/';
            }
        }
        return '/' . ltrim($path, '/');
    }

    public function method(): string { return $this->method; }
    public function path(): string { return $this->uri; }
    public function url(): string { return (string)($this->server['REQUEST_URI'] ?? '/'); }
    public function all(): array { return array_replace($this->query, $this->body); }
    public function input(?string $key = null, mixed $default = null): mixed {
        $data = $this->all(); return $key === null ? $data : ($data[$key] ?? $default);
    }
    public function query(?string $key = null, mixed $default = null): mixed {
        return $key === null ? $this->query : ($this->query[$key] ?? $default);
    }
    public function only(array $keys): array { return array_intersect_key($this->all(), array_flip($keys)); }
    public function except(array $keys): array { return array_diff_key($this->all(), array_flip($keys)); }
    public function has(string $key): bool { return array_key_exists($key, $this->all()); }
    public function filled(string $key): bool { $v = $this->input($key); return $v !== null && $v !== ''; }
    public function isJson(): bool { return str_contains(strtolower((string)($this->server['HTTP_ACCEPT'] ?? '')), 'application/json') || str_contains(strtolower((string)($this->server['CONTENT_TYPE'] ?? '')), 'application/json'); }
    public function json(): array { $v = json_decode($this->rawBody, true); return is_array($v) ? $v : []; }
    public function file(string $key): ?array { return $this->files[$key] ?? null; }
    public function header(string $name, mixed $default = null): mixed {
        foreach ($this->headers as $key => $value) if (strcasecmp((string)$key, $name) === 0) return $value;
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $this->server[$serverKey] ?? $default;
    }
    public function ip(): string { return (string)($this->server['REMOTE_ADDR'] ?? '127.0.0.1'); }
    public function userAgent(): string { return (string)($this->server['HTTP_USER_AGENT'] ?? ''); }
}
