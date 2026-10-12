<?php
declare(strict_types=1);
namespace LeafPHP\Core;

final class Filter
{
    /** @var array<string, callable> */ private static array $filters = [];
    public static function register(string $name, callable $callback): void { self::$filters[$name] = $callback; }
    public static function aliases(): array
    {
        if (!isset(self::$filters['auth'])) self::register('auth', static fn () => auth()->check() ? null : redirect((string) env('AUTH_LOGIN_PATH', '/login')));
        if (!isset(self::$filters['guest'])) self::register('guest', static fn () => auth()->check() ? redirect('/') : null);
        if (!isset(self::$filters['verified'])) self::register('verified', static fn () => auth()->check() && auth()->user() && !empty(auth()->user()['email_verified_at']) ? null : redirect('/'));
        return self::$filters;
    }
    /** Return the first short-circuit response, or null to continue. */
    public static function run(array|string|null $filters): mixed
    {
        $filters = is_string($filters) ? [$filters] : ($filters ?? []);
        $aliases = self::aliases();
        foreach ($filters as $filter) {
            if (is_string($filter) && isset($aliases[$filter])) $callback = $aliases[$filter];
            elseif (is_callable($filter)) $callback = $filter;
            else $callback = null;
            if (!$callback) throw new \InvalidArgumentException('Unknown route filter [' . (is_string($filter) ? $filter : get_debug_type($filter)) . ']. Register it with Filter::register().');
            $result = $callback(request());
            if ($result === false) return redirect((string) env('AUTH_LOGIN_PATH', '/login'));
            if ($result !== null && $result !== true) return $result;
        }
        return null;
    }
}
