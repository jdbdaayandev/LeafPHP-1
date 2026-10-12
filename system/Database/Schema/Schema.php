<?php
declare(strict_types=1);
namespace LeafPHP\Database\Schema;
use LeafPHP\Database\DB;
use LeafPHP\Database\Connection;
final class Schema
{
    public static function create(string $table, callable $callback): void {
        self::assertIdentifier($table); $table = Connection::tableName($table); $blueprint = new Blueprint(); $callback($blueprint);
        $driver = DB::connection()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $definitions = $blueprint->definitions();
        if ($driver === 'sqlite') {
            $definitions = array_map(static function (string $definition): string {
                return str_replace('`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY', '`id` INTEGER PRIMARY KEY AUTOINCREMENT', $definition);
            }, $definitions);
        }
        $sql = 'CREATE TABLE `' . $table . '` (' . implode(', ', $definitions) . ')';
        if ($driver === 'mysql') $sql .= ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
        DB::statement($sql);
    }
    public static function dropIfExists(string $table): void { self::assertIdentifier($table); DB::statement('DROP TABLE IF EXISTS `' . Connection::tableName($table) . '`'); }
    public static function hasTable(string $table): bool {
        self::assertIdentifier($table); $table = Connection::tableName($table); $pdo = DB::connection(); $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') return (bool)DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?", [$table]);
        if ($driver === 'pgsql') return (bool)DB::select('SELECT table_name FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = ?', [$table]);
        return (bool)DB::select('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$table]);
    }
    private static function assertIdentifier(string $name): void { if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) throw new \InvalidArgumentException('Invalid SQL identifier'); }
}
