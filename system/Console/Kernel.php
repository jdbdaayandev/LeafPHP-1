<?php
declare(strict_types=1);
namespace LeafPHP\Console;

use LeafPHP\Database\DB;
use LeafPHP\Database\Migration\MigrationInterface;
use Throwable;

final class Kernel
{
    public function __construct(private string $basePath) {}
    public function handle(array $argv): int
    {
        $command = $argv[1] ?? 'list';
        try {
            return match ($command) {
                'list', 'help' => $this->list(),
                'serve' => $this->serve($argv[2] ?? '127.0.0.1', (int)($argv[3] ?? 8080)),
                'make:controller' => $this->makeClass('Controllers', $argv[2] ?? '', 'controller'),
                'make:model' => $this->makeClass('Models', $argv[2] ?? '', 'model'),
                'make:migration' => $this->makeMigration($argv[2] ?? ''),
                'make:session-migration', 'make:migration:sessions' => $this->makeSessionMigration(),
                'make:seeder' => $this->makeSeeder($argv[2] ?? ''),
                'migrate' => $this->migrate(),
                'migrate:status' => $this->migrationStatus(),
                'migrate:rollback' => $this->rollback((int)($argv[2] ?? 1)),
                'migrate:reset' => $this->resetMigrations(),
                'migrate:fresh' => $this->fresh(in_array('--seed', $argv, true)),
                'db:seed' => $this->seed($argv[2] ?? null),
                'key:generate' => $this->generateKey(),
                default => $this->unknown($command),
            };
        } catch (Throwable $e) { fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL); return 1; }
    }
    private function list(): int {
        echo "LeafPHP Framework v1.4.1 | Owner: JDBD\n\n";
        echo "Commands:\n  serve [host] [port]\n  make:controller Name\n  make:model Name\n  make:migration description\n  make:session-migration\n  make:seeder Name\n  migrate\n  migrate:status\n  migrate:rollback [steps]\n  migrate:reset\n  migrate:fresh [--seed]\n  db:seed [SeederClass]\n  key:generate\n";
        return 0;
    }
    private function serve(string $host, int $port): int {
        if ($port < 1 || $port > 65535) throw new \InvalidArgumentException('Port must be between 1 and 65535.');
        echo "LeafPHP running at http://{$host}:{$port}\nPress Ctrl+C to stop.\n";
        passthru(escapeshellarg(PHP_BINARY) . ' -S ' . escapeshellarg($host . ':' . $port) . ' -t ' . escapeshellarg($this->basePath . '/public') . ' ' . escapeshellarg($this->basePath . '/system/Core/dev-router.php'), $status);
        return $status;
    }
    private function makeClass(string $folder, string $name, string $kind): int {
        $this->assertName($name); $dir = $this->basePath . '/app/' . $folder; if (!is_dir($dir)) mkdir($dir, 0775, true);
        $namespace = 'App\\' . $folder; $file = $dir . '/' . $name . '.php';
        if (is_file($file)) throw new \RuntimeException("File already exists: {$file}");
        $body = $kind === 'controller'
            ? "<?php\ndeclare(strict_types=1);\n\nnamespace {$namespace};\n\nclass {$name}\n{\n    public function index(): string\n    {\n        return view('home', ['title' => '{$name}']);\n    }\n}\n"
            : "<?php\ndeclare(strict_types=1);\n\nnamespace {$namespace};\n\nclass {$name} extends Model\n{\n    protected string \$table = '" . strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', preg_replace('/Model$/', '', $name))) . "';\n}\n";
        if ($kind === 'model') $body = str_replace("namespace {$namespace};", "namespace {$namespace};\n\nuse App\\Models\\Model;", $body);
        file_put_contents($file, $body); echo "Created {$file}\n"; return 0;
    }
    private function makeMigration(string $name): int {
        $name = strtolower(trim($name)); $name = preg_replace('/[^a-z0-9_]+/', '_', $name);
        if ($name === '') throw new \InvalidArgumentException('Provide a migration name.');
        $dir = $this->basePath . '/database/migrations'; if (!is_dir($dir)) mkdir($dir, 0775, true);
        $file = $dir . '/' . date('Y_m_d_His') . '_' . $name . '.php';
        $table = preg_replace('/^(create_|add_)/', '', $name); $table = preg_replace('/_table$/', '', $table);
        $body = "<?php\ndeclare(strict_types=1);\n\nuse LeafPHP\\Database\\Migration\\MigrationInterface;\nuse LeafPHP\\Database\\Schema\\Blueprint;\nuse LeafPHP\\Database\\Schema\\Schema;\n\nreturn new class implements MigrationInterface {\n    public function up(): void\n    {\n        Schema::create('{$table}', function (Blueprint \$table): void {\n            \$table->id();\n            \$table->timestamps();\n        });\n    }\n\n    public function down(): void\n    {\n        Schema::dropIfExists('{$table}');\n    }\n};\n";
        file_put_contents($file, $body); echo "Created {$file}\n"; return 0;
    }

    private function makeSessionMigration(): int {
        $dir = $this->basePath . '/database/migrations'; if (!is_dir($dir)) mkdir($dir, 0775, true);
        $file = $dir . '/' . date('Y_m_d_His') . '_create_sessions_table.php';
        if (is_file($file)) throw new \RuntimeException('A session migration already exists for this timestamp. Run the command again in one second.');
        $body = <<<'PHP'
<?php
declare(strict_types=1);

use LeafPHP\Database\Migration\MigrationInterface;
use LeafPHP\Database\DB;
use LeafPHP\Database\Connection;

return new class implements MigrationInterface {
    public function up(): void
    {
        $table = Connection::tableName('sessions');
        $driver = DB::connection()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            DB::statement("CREATE TABLE IF NOT EXISTS `{$table}` (`id` VARCHAR(255) NOT NULL PRIMARY KEY, `user_id` INTEGER NULL, `ip_address` VARCHAR(45) NULL, `user_agent` VARCHAR(500) NULL, `payload` TEXT NOT NULL, `last_activity` INTEGER NOT NULL)");
            DB::statement("CREATE INDEX IF NOT EXISTS `{$table}_last_activity_index` ON `{$table}` (`last_activity`)");
            DB::statement("CREATE INDEX IF NOT EXISTS `{$table}_user_id_index` ON `{$table}` (`user_id`)");
            return;
        }
        DB::statement("CREATE TABLE IF NOT EXISTS `{$table}` (`id` VARCHAR(255) NOT NULL PRIMARY KEY, `user_id` BIGINT UNSIGNED NULL, `ip_address` VARCHAR(45) NULL, `user_agent` VARCHAR(500) NULL, `payload` LONGTEXT NOT NULL, `last_activity` INT UNSIGNED NOT NULL, INDEX `sessions_last_activity_index` (`last_activity`), INDEX `sessions_user_id_index` (`user_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS `' . Connection::tableName('sessions') . '`');
    }
};
PHP;
        file_put_contents($file, $body . "\n"); echo "Created database-session migration: {$file}\n";
        echo "Next: php leaf migrate, then set SESSION_DRIVER=database in .env.\n"; return 0;
    }

    private function makeSeeder(string $name): int {
        $this->assertName($name); $dir = $this->basePath . '/database/seeders'; if (!is_dir($dir)) mkdir($dir, 0775, true);
        $file = $dir . '/' . $name . '.php';
        if (is_file($file)) throw new \RuntimeException("File already exists: {$file}");
        $body = "<?php\ndeclare(strict_types=1);\n\nuse LeafPHP\\Database\\Seeder;\nuse LeafPHP\\Database\\DB;\n\nreturn new class extends Seeder {\n    public function run(): void\n    {\n        // Add repeatable seed data using DB::statement() or DB::table().\n    }\n};\n";
        file_put_contents($file, $body); echo "Created {$file}\n"; return 0;
    }
    private function ensureMigrationsTable(): void {
        $driver = DB::connection()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $sql = $driver === 'sqlite'
            ? 'CREATE TABLE IF NOT EXISTS `migrations` (`id` INTEGER PRIMARY KEY AUTOINCREMENT, `migration` VARCHAR(255) NOT NULL UNIQUE, `batch` INTEGER NOT NULL, `ran_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP)'
            : ($driver === 'pgsql'
                ? 'CREATE TABLE IF NOT EXISTS `migrations` (`id` BIGSERIAL PRIMARY KEY, `migration` VARCHAR(255) NOT NULL UNIQUE, `batch` INTEGER NOT NULL, `ran_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP)'
                : 'CREATE TABLE IF NOT EXISTS `migrations` (`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, `migration` VARCHAR(255) NOT NULL UNIQUE, `batch` INT NOT NULL, `ran_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        DB::statement($sql);
    }
    private function migrate(): int {
        $this->ensureMigrationsTable(); $ran = array_column(DB::select('SELECT migration FROM migrations'), 'migration');
        $files = glob($this->basePath . '/database/migrations/*.php') ?: []; sort($files); $batch = (int)(DB::select('SELECT COALESCE(MAX(batch),0)+1 AS next_batch FROM migrations')[0]['next_batch'] ?? 1); $count = 0;
        foreach ($files as $file) { $name = basename($file, '.php'); if (in_array($name, $ran, true)) continue; $migration = require $file; if (!$migration instanceof MigrationInterface) throw new \RuntimeException("Invalid migration: {$name}"); $migration->up(); DB::statement('INSERT INTO migrations (migration,batch) VALUES (?,?)', [$name,$batch]); echo "Migrated: {$name}\n"; $count++; }
        echo $count ? "Completed {$count} migration(s).\n" : "Nothing to migrate.\n"; return 0;
    }
    private function migrationStatus(): int {
        $this->ensureMigrationsTable(); $ran = array_column(DB::select('SELECT migration FROM migrations'), 'migration'); $files = glob($this->basePath . '/database/migrations/*.php') ?: []; sort($files);
        foreach ($files as $file) { $n = basename($file,'.php'); echo (in_array($n,$ran,true) ? '[Ran]     ' : '[Pending] ') . $n . "\n"; } return 0;
    }
    private function rollback(int $steps): int {
        $this->ensureMigrationsTable(); $rows = DB::select('SELECT migration,batch FROM migrations ORDER BY batch DESC,id DESC');
        $batchGroups = []; foreach ($rows as $row) $batchGroups[(int)$row['batch']][] = $row;
        $batches = array_slice(array_keys($batchGroups), 0, max(1,$steps));
        foreach ($batches as $batch) foreach ($batchGroups[$batch] as $row) {
            $file = $this->basePath . '/database/migrations/' . $row['migration'] . '.php'; if (!is_file($file)) throw new \RuntimeException('Migration file missing: ' . $row['migration']);
            $migration = require $file; $migration->down(); DB::statement('DELETE FROM migrations WHERE migration = ?', [$row['migration']]); echo "Rolled back: {$row['migration']}\n";
        } return 0;
    }
    private function resetMigrations(): int {
        $this->ensureMigrationsTable(); $rows = DB::select('SELECT migration FROM migrations ORDER BY batch DESC,id DESC');
        foreach ($rows as $row) { $file = $this->basePath . '/database/migrations/' . $row['migration'] . '.php'; if (!is_file($file)) continue; $migration = require $file; $migration->down(); DB::statement('DELETE FROM migrations WHERE migration = ?', [$row['migration']]); echo "Rolled back: {$row['migration']}\n"; } return 0;
    }
    private function fresh(bool $seedAfter = false): int {
        fwrite(STDOUT, "WARNING: migrate:fresh is destructive. It rolls back recorded migrations and recreates the schema. Type YES to continue: ");
        if (trim((string)fgets(STDIN)) !== 'YES') { echo "Cancelled.\n"; return 0; }
        $this->resetMigrations();
        $status = $this->migrate();
        if ($status !== 0) return $status;
        if ($seedAfter) return $this->seed(null);
        return 0;
    }
    private function seed(?string $class): int {
        $dir = $this->basePath . '/database/seeders'; $files = glob($dir . '/*.php') ?: []; sort($files); $count = 0;
        foreach ($files as $file) {
            $name = basename($file,'.php');
            if ($class && $class !== $name && $class !== '--class='.$name) continue;
            $seeder = require $file; if (!$seeder instanceof \LeafPHP\Database\Seeder) throw new \RuntimeException("Invalid seeder: {$name}");
            $seeder->run(); echo "Seeded: {$name}\n"; $count++;
        }
        if ($count === 0) echo $class ? "Seeder not found: {$class}\n" : "No seeders found.\n";
        return 0;
    }
    private function generateKey(): int {
        $path = $this->basePath . '/.env'; if (!is_file($path)) throw new \RuntimeException('Copy .env.example to .env first.');
        $contents = file_get_contents($path); $key = 'base64:' . base64_encode(random_bytes(32));
        if (preg_match('/^APP_KEY=.*$/m', $contents)) $contents = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY='.$key, $contents);
        else $contents .= "\nAPP_KEY={$key}\n";
        file_put_contents($path, $contents); echo "Application key generated.\n"; return 0;
    }
    private function assertName(string $name): void { if (!preg_match('/^[A-Z][A-Za-z0-9_]*$/', $name)) throw new \InvalidArgumentException('Use a class name beginning with an uppercase letter, e.g. BookSeeder.'); }
    private function unknown(string $command): int { fwrite(STDERR, "Unknown command: {$command}\nRun php leaf list\n"); return 1; }
}
