<?php
declare(strict_types=1);

namespace LeafPHP\Core;

/** Fluent builder for nested route groups. */
final class RouteGroup
{
    public function __construct(private array $attributes = []) {}

    public function prefix(string $prefix): self
    {
        $clone = clone $this;
        $existing = trim((string)($clone->attributes['prefix'] ?? ''), '/');
        $clone->attributes['prefix'] = trim($existing . '/' . trim($prefix, '/'), '/');
        return $clone;
    }

    public function middleware(array|string $middleware): self
    {
        $clone = clone $this;
        $current = $clone->attributes['middleware'] ?? [];
        if (is_string($current)) $current = [$current];
        $incoming = is_array($middleware) ? $middleware : [$middleware];
        $clone->attributes['middleware'] = array_values(array_unique(array_merge($current, $incoming)));
        return $clone;
    }

    public function group(callable $callback): void
    {
        Router::group($this->attributes, $callback);
    }
}
