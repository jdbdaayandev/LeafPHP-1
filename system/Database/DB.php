<?php
declare(strict_types=1);
namespace LeafPHP\Database;
use PDO; use PDOStatement;
final class DB
{
    private static ?string $defaultConnection = null;
    public static function connection(?string $name = null): PDO { return Connection::get($name ?? self::$defaultConnection); }
    public static function use(string $name): void { Connection::get($name); self::$defaultConnection=$name; }
    public static function select(string $sql, array $bindings = [], ?string $connection = null): array { return self::statement($sql,$bindings,$connection)->fetchAll(); }
    public static function statement(string $sql, array $bindings = [], ?string $connection = null): PDOStatement { $pdo=self::connection($connection); $start=microtime(true); $stmt=$pdo->prepare($sql); $stmt->execute(array_values($bindings)); QueryLog::add($sql,$bindings,(microtime(true)-$start)*1000,$connection ?? self::$defaultConnection ?? 'default'); return $stmt; }
    public static function insert(string $sql, array $bindings = [], ?string $connection = null): bool { self::statement($sql,$bindings,$connection); return true; }
    public static function affectingStatement(string $sql, array $bindings = [], ?string $connection = null): int { return self::statement($sql,$bindings,$connection)->rowCount(); }
    public static function transaction(callable $callback, ?string $connection = null): mixed { $pdo=self::connection($connection); $owner=!$pdo->inTransaction(); if($owner)$pdo->beginTransaction(); try{$result=$callback($pdo);if($owner)$pdo->commit();return $result;}catch(\Throwable $e){if($owner&&$pdo->inTransaction())$pdo->rollBack();throw $e;} }
    public static function beginTransaction(?string $connection = null): void { self::connection($connection)->beginTransaction(); }
    public static function commit(?string $connection = null): void { self::connection($connection)->commit(); }
    public static function rollBack(?string $connection = null): void { $pdo=self::connection($connection); if($pdo->inTransaction())$pdo->rollBack(); }
    public static function table(string $table, ?string $connection = null): QueryBuilder { return new QueryBuilder($table,$connection); }
}
