<?php
declare(strict_types=1);

namespace LeafPHP\Core;

/**
 * Lightweight static router with named routes, nested groups, prefixes,
 * middleware filters, resource routes, and controller dispatch.
 */
final class Router
{
    private static array $routes = [];
    private static array $named = [];
    private static ?array $current = null;
    private static array $groupStack = [];

    public static function get(string $uri, callable|array $action, ?string $name = null, array|string|null $filters = []): void { self::add('GET', $uri, $action, $name, $filters); }
    public static function post(string $uri, callable|array $action, ?string $name = null, array|string|null $filters = []): void { self::add('POST', $uri, $action, $name, $filters); }
    public static function put(string $uri, callable|array $action, ?string $name = null, array|string|null $filters = []): void { self::add('PUT', $uri, $action, $name, $filters); }
    public static function patch(string $uri, callable|array $action, ?string $name = null, array|string|null $filters = []): void { self::add('PATCH', $uri, $action, $name, $filters); }
    public static function delete(string $uri, callable|array $action, ?string $name = null, array|string|null $filters = []): void { self::add('DELETE', $uri, $action, $name, $filters); }

    /** Start a route group that shares a URL prefix. */
    public static function prefix(string $prefix): RouteGroup { return new RouteGroup(['prefix' => $prefix]); }

    /** Start a route group that shares one or more middleware filters. */
    public static function middleware(array|string $middleware): RouteGroup
    {
        return new RouteGroup(['middleware' => is_array($middleware) ? $middleware : [$middleware]]);
    }

    /** Register routes inside a group with optional prefix and middleware. */
    public static function group(array $attributes, callable $callback): void
    {
        $parent = self::$groupStack ? self::$groupStack[count(self::$groupStack) - 1] : ['prefix' => '', 'middleware' => []];
        $prefix = trim((string)($attributes['prefix'] ?? ''), '/');
        $middleware = $attributes['middleware'] ?? [];
        if (is_string($middleware)) $middleware = [$middleware];
        if (!is_array($middleware)) throw new \InvalidArgumentException('Route group middleware must be a string or array.');
        $combinedPrefix = trim($parent['prefix'] . '/' . $prefix, '/');
        $combinedMiddleware = array_values(array_unique(array_merge($parent['middleware'], $middleware)));
        self::$groupStack[] = ['prefix' => $combinedPrefix, 'middleware' => $combinedMiddleware];
        try {
            $callback();
        } finally {
            array_pop(self::$groupStack);
        }
    }

    /** Register a conventional RESTful resource controller. */
    public static function resource(string $name, string $controller, array $options = []): void
    {
        $name = trim($name, '/');
        if ($name === '') throw new \InvalidArgumentException('Resource name cannot be empty.');
        $segments = explode('/', $name);
        $lastSegment = (string) end($segments);
        $defaultParameter = str_ends_with($lastSegment, 'ies') ? substr($lastSegment, 0, -3) . 'y' : rtrim($lastSegment, 's');
        $parameter = (string)($options['parameter'] ?? $defaultParameter);
        if ($parameter === '') $parameter = 'id';
        $parameter = preg_replace('/[^A-Za-z0-9_]/', '', $parameter) ?: 'id';
        $only = isset($options['only']) ? (array)$options['only'] : ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'];
        $except = (array)($options['except'] ?? []);
        $actions = [
            'index' => ['GET', '/' . $name, 'index', null],
            'create' => ['GET', '/' . $name . '/create', 'create', null],
            'store' => ['POST', '/' . $name, 'store', null],
            'show' => ['GET', '/' . $name . '/{' . $parameter . '}', 'show', $parameter],
            'edit' => ['GET', '/' . $name . '/{' . $parameter . '}/edit', 'edit', $parameter],
            'update' => ['PUT', '/' . $name . '/{' . $parameter . '}', 'update', $parameter],
            'destroy' => ['DELETE', '/' . $name . '/{' . $parameter . '}', 'destroy', $parameter],
        ];
        $names = $options['names'] ?? [];
        foreach ($actions as $action => [$method, $uri, $controllerMethod]) {
            if (!in_array($action, $only, true) || in_array($action, $except, true)) continue;
            $routeName = $names[$action] ?? $name . '.' . $action;
            self::add($method, $uri, [$controller, $controllerMethod], $routeName, $options['middleware'] ?? []);
            if ($action === 'update') self::add('PATCH', $uri, [$controller, $controllerMethod], $routeName, $options['middleware'] ?? []);
        }
    }

    private static function add(string $method, string $uri, callable|array $action, ?string $name, array|string|null $filters = []): void
    {
        $group = self::$groupStack ? self::$groupStack[count(self::$groupStack) - 1] : ['prefix' => '', 'middleware' => []];
        $prefix = trim($group['prefix'], '/');
        $uri = ($prefix !== '' ? '/' . $prefix : '') . '/' . trim($uri, '/');
        $uri = self::normalize($uri);
        $localFilters = is_string($filters) ? [$filters] : ($filters ?? []);
        $allFilters = array_values(array_unique(array_merge($group['middleware'], $localFilters)));
        self::$routes[$method][$uri] = ['action' => $action, 'name' => $name, 'filters' => $allFilters];
        if ($name !== null) {
            if ($name === '') throw new \InvalidArgumentException('Route name cannot be empty.');
            self::$named[$name] = ['method' => $method, 'uri' => $uri];
        }
    }

    public static function url(string $name, array $parameters = []): string
    {
        if (!isset(self::$named[$name])) throw new \InvalidArgumentException("Named route [{$name}] is not registered.");
        $uri = self::$named[$name]['uri'];
        $used = [];
        $uri = preg_replace_callback('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', static function ($m) use ($parameters, &$used) {
            $key = $m[1];
            if (!array_key_exists($key, $parameters)) throw new \InvalidArgumentException("Missing route parameter [{$key}].");
            $used[] = $key;
            return rawurlencode((string)$parameters[$key]);
        }, $uri) ?? $uri;
        $query = array_diff_key($parameters, array_flip($used));
        return base_url(ltrim($uri, '/')) . ($query ? '?' . http_build_query($query) : '');
    }

    public static function routes(): array
    {
        $out = [];
        foreach (self::$routes as $method => $routes) foreach ($routes as $uri => $r) {
            $out[] = ['method' => $method, 'uri' => $uri, 'name' => $r['name'], 'filters' => $r['filters'],
                'action' => is_array($r['action']) ? (($r['action'][0] ?? '') . '@' . ($r['action'][1] ?? '')) : 'Closure'];
        }
        return $out;
    }

    public static function current(): ?array { return self::$current; }

    public static function dispatch(string $method, string $uri): mixed
    {
        $uri = self::normalize($uri);
        foreach (self::$routes[$method] ?? [] as $pattern => $route) {
            $names = []; $regex = ''; $offset = 0;
            if (preg_match_all('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', $pattern, $tokens, PREG_OFFSET_CAPTURE)) {
                foreach ($tokens[0] as $i => $token) {
                    $regex .= preg_quote(substr($pattern, $offset, $token[1] - $offset), '#') . '([^/]+)';
                    $names[] = $tokens[1][$i][0]; $offset = $token[1] + strlen($token[0]);
                }
            }
            $regex .= preg_quote(substr($pattern, $offset), '#');
            if (!preg_match('#^' . $regex . '$#', $uri, $matches)) continue;
            array_shift($matches); $params = array_map('urldecode', $matches); $action = $route['action'];
            self::$current = ['method' => $method, 'uri' => $pattern, 'name' => $route['name'], 'filters' => $route['filters'],
                'action' => is_array($action) ? (($action[0] ?? '') . '@' . ($action[1] ?? '')) : 'Closure'];
            $shortCircuit = Filter::run($route['filters']);
            if ($shortCircuit !== null) return $shortCircuit;
            if (is_array($action) && is_string($action[0])) $action = [new $action[0](), $action[1]];
            return $action(...$params);
        }
        http_response_code(404);
        try { return view('errors.404', ['status' => 404, 'title' => 'Page not found', 'message' => 'The page you requested could not be found.']); }
        catch (\Throwable) { return '404 - Not Found'; }
    }

    private static function normalize(string $uri): string
    {
        $normalized = '/' . trim($uri, '/');
        return $normalized === '//' ? '/' : $normalized;
    }
}
