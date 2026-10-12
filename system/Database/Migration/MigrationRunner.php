<?php
declare(strict_types=1);

namespace LeafPHP\Database\Migration;

use LeafPHP\Database\DB;
use Throwable;

/**
 * Runs and tracks database migrations for the current default connection.
 * Migration files must return an object implementing MigrationInterface.
 */
final class MigrationRunner
{
    public function __construct(private readonly string $basePath) {}

    /** Run every pending migration in filename order. */
    public function run(): int
    {
        $this->ensureRepository();
        $ran = array_fill_keys(array_column(DB::select('SELECT migration FROM migrations'), 'migration'), true);
        $files = $this->files();
        $batch = (int) (DB::select('SELECT COALESCE(MAX(batch), 0) + 1 AS next_batch FROM migrations')[0]['next_batch'] ?? 1);
        $count = 0;

        foreach ($files as $file) {
            $name = basename($file, '.php');
            if (isset($ran[$name])) continue;

            DB::transaction(static function () use ($file, $name, $batch): void {
                $migration = require $file;
                if (!$migration instanceof MigrationInterface) {
                    throw new \RuntimeException("Invalid migration '{$name}': file must return a MigrationInterface instance.");
                }
                $migration->up();
                DB::statement('INSERT INTO migrations (migration, batch) VALUES (?, ?)', [$name, $batch]);
            });

            echo "Migrated: {$name}" . PHP_EOL;
            $count++;
        }

        echo $count > 0 ? "Completed {$count} migration(s)." . PHP_EOL : "Nothing to migrate." . PHP_EOL;
        return 0;
    }

    /** Display migration files and their current state. */
    public function status(): int
    {
        $this->ensureRepository();
        $ran = array_fill_keys(array_column(DB::select('SELECT migration FROM migrations'), 'migration'), true);
        $files = $this->files();
        if ($files === []) {
            echo "No migration files found." . PHP_EOL;
            return 0;
        }

        foreach ($files as $file) {
            $name = basename($file, '.php');
            printf("%-10s %s\n", isset($ran[$name]) ? '[Ran]' : '[Pending]', $name);
        }
        return 0;
    }

    /** Roll back the most recent N batches. */
    public function rollback(int $steps = 1): int
    {
        if ($steps < 1) throw new \InvalidArgumentException('Rollback steps must be at least 1.');
        $this->ensureRepository();
        $rows = DB::select('SELECT migration, batch FROM migrations ORDER BY batch DESC, id DESC');
        $groups = [];
        foreach ($rows as $row) $groups[(int) $row['batch']][] = $row;
        $batches = array_slice(array_keys($groups), 0, $steps);
        if ($batches === []) {
            echo "Nothing to roll back." . PHP_EOL;
            return 0;
        }

        foreach ($batches as $batch) {
            foreach ($groups[$batch] as $row) $this->rollbackOne((string) $row['migration']);
        }
        return 0;
    }

    /** Roll back all recorded migrations, newest batch first. */
    public function reset(): int
    {
        $this->ensureRepository();
        $rows = DB::select('SELECT migration FROM migrations ORDER BY batch DESC, id DESC');
        if ($rows === []) {
            echo "Nothing to reset." . PHP_EOL;
            return 0;
        }
        foreach ($rows as $row) $this->rollbackOne((string) $row['migration']);
        return 0;
    }

    private function rollbackOne(string $name): void
    {
        $file = $this->basePath . '/database/migrations/' . $name . '.php';
        if (!is_file($file)) throw new \RuntimeException("Cannot roll back '{$name}': migration file is missing.");

        DB::transaction(static function () use ($file, $name): void {
            $migration = require $file;
            if (!$migration instanceof MigrationInterface) {
                throw new \RuntimeException("Invalid migration '{$name}': file must return a MigrationInterface instance.");
            }
            $migration->down();
            DB::statement('DELETE FROM migrations WHERE migration = ?', [$name]);
        });
        echo "Rolled back: {$name}" . PHP_EOL;
    }

    /** @return list<string> */
    private function files(): array
    {
        $files = glob($this->basePath . '/database/migrations/*.php') ?: [];
        sort($files, SORT_STRING);
        return $files;
    }

    private function ensureRepository(): void
    {
        $driver = DB::connection()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $sql = 'CREATE TABLE IF NOT EXISTS `migrations` (`id` INTEGER PRIMARY KEY AUTOINCREMENT, `migration` VARCHAR(255) NOT NULL UNIQUE, `batch` INTEGER NOT NULL, `ran_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP)';
        } elseif ($driver === 'mysql') {
            $sql = 'CREATE TABLE IF NOT EXISTS `migrations` (`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, `migration` VARCHAR(255) NOT NULL UNIQUE, `batch` INT NOT NULL, `ran_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
        } else {
            throw new \RuntimeException("Migrations currently support SQLite and MySQL/MariaDB; connection driver '{$driver}' is not supported.");
        }
        DB::statement($sql);
    }
}
