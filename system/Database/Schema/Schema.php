<?php

declare(strict_types=1);

namespace LeafPHP\Database\Schema;

use LeafPHP\Database\Connection;
use LeafPHP\Database\DB;

/** Schema operations and database-driver-specific SQL compilation. */
final class Schema
{
    public static function create(string $table, callable $callback): void
    {
        self::assertIdentifier($table);
        $blueprint = new Blueprint();
        $callback($blueprint);

        $pdo = DB::connection();
        $driver = (string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $actualTable = Connection::tableName($table);
        $definitions = $blueprint->definitions($driver);
        if ($definitions === []) {
            throw new \LogicException('Cannot create a table without columns.');
        }

        $sql = 'CREATE TABLE `' . $actualTable . '` (' . implode(', ', $definitions) . ')';
        if ($driver === 'mysql') {
            $sql .= ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        }
        DB::statement($sql);

        foreach ($blueprint->indexes() as $index) {
            // A column-level primary key (for example id()) is already emitted
            // as part of its column definition and must not be duplicated.
            if ($index['type'] === 'primary') {
                continue;
            }

            $columns = array_map(static fn (string $column): string => '`' . $column . '`', $index['columns']);
            $indexName = $index['name'] ?? $actualTable . '_' . implode('_', $index['columns']) . '_' . $index['type'];
            self::assertIdentifier($indexName);
            $unique = $index['type'] === 'unique' ? 'UNIQUE ' : '';
            DB::statement('CREATE ' . $unique . 'INDEX `' . $indexName . '` ON `' . $actualTable . '` (' . implode(', ', $columns) . ')');
        }
    }

    public static function dropIfExists(string $table): void
    {
        self::assertIdentifier($table);
        DB::statement('DROP TABLE IF EXISTS `' . Connection::tableName($table) . '`');
    }

    public static function hasTable(string $table): bool
    {
        self::assertIdentifier($table);
        $actualTable = Connection::tableName($table);
        $pdo = DB::connection();
        $driver = (string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            return (bool) DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?", [$actualTable]);
        }
        if ($driver === 'pgsql') {
            return (bool) DB::select('SELECT table_name FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = ?', [$actualTable]);
        }
        return (bool) DB::select('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$actualTable]);
    }

    private static function assertIdentifier(string $name): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            throw new \InvalidArgumentException('Invalid SQL identifier: ' . $name);
        }
    }
}
