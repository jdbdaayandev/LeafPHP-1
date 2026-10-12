<?php
declare(strict_types=1);
namespace LeafPHP\Database;

use PDO;
use InvalidArgumentException;

final class Connection
{
    /** @var array<string, PDO> */ private static array $connections = [];
    public static function get(?string $name = null): PDO
    {
        $config = require dirname(__DIR__, 2) . '/app/config/database.php';
        $name ??= (string)($config['default'] ?? 'sqlite');
        if (isset(self::$connections[$name])) return self::$connections[$name];
        $connection = $config['connections'][$name] ?? null;
        if (!is_array($connection)) throw new InvalidArgumentException("Database connection [{$name}] is not configured.");
        $driver = (string)($connection['driver'] ?? 'sqlite');
        if ($driver === 'sqlite') {
            $path = (string)($connection['database'] ?? dirname(__DIR__, 2) . '/database/database.sqlite');
            if ($path !== ':memory:') {
                $dir = dirname($path);
                if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) throw new \RuntimeException("Unable to create SQLite directory: {$dir}");
            }
            $dsn = 'sqlite:' . $path;
        } else {
            $dsn = match ($driver) {
                'mysql' => sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $connection['host'] ?? '127.0.0.1', (int)($connection['port'] ?? 3306), $connection['database'] ?? '', $connection['charset'] ?? 'utf8mb4'),
                'pgsql' => sprintf('pgsql:host=%s;port=%d;dbname=%s', $connection['host'] ?? '127.0.0.1', (int)($connection['port'] ?? 5432), $connection['database'] ?? ''),
                default => throw new InvalidArgumentException("Unsupported database driver [{$driver}]."),
            };
        }
        $pdo = new PDO($dsn, $connection['username'] ?? null, $connection['password'] ?? null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
        if ($driver === 'sqlite') $pdo->exec('PRAGMA foreign_keys = ON');
        self::$connections[$name] = $pdo;
        return $pdo;
    }
    public static function prefix(): string
    {
        $config = require dirname(__DIR__, 2) . '/app/config/database.php';
        $prefix = (string)($config['prefix'] ?? '');
        if ($prefix !== '' && !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $prefix)) throw new InvalidArgumentException('DB_PREFIX must contain only letters, numbers, and underscores and cannot start with a number.');
        return $prefix;
    }
    public static function tableName(string $table): string
    {
        $prefix = self::prefix();
        return $prefix !== '' && !str_starts_with($table, $prefix) ? $prefix . $table : $table;
    }
    public static function purge(?string $name = null): void { if ($name === null) self::$connections=[]; else unset(self::$connections[$name]); }
    public static function names(): array { $c=require dirname(__DIR__,2).'/app/config/database.php'; return array_keys($c['connections'] ?? []); }
}
