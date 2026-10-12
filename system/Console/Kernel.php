<?php
declare(strict_types=1);
namespace LeafPHP\Console;

use LeafPHP\Database\DB;
use LeafPHP\Database\Migration\MigrationRunner;
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
                'migrate' => $this->migrations()->run(),
                'migrate:status' => $this->migrations()->status(),
                'migrate:rollback' => $this->migrations()->rollback((int)($argv[2] ?? 1)),
                'migrate:reset' => $this->migrations()->reset(),
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
    private function makeMigration(string $name): int
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9_]+/', '_', $name) ?? '';
        $name = trim(preg_replace('/_+/', '_', $name) ?? '', '_');
        if ($name === '') throw new \InvalidArgumentException('Provide a migration name, e.g. create_books_table.');

        $dir = $this->basePath . '/database/migrations';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Could not create the database/migrations directory.');
        }

        if (preg_match('/^create_([a-z][a-z0-9_]*)_table$/', $name, $matches)) {
            $tableName = $matches[1];
            $up = "        Schema::create('{$tableName}', function (Blueprint \$table): void {\n            \$table->id();\n            \$table->timestamps();\n        });";
            $down = "        Schema::dropIfExists('{$tableName}');";
        } else {
            $up = '        // Add your schema changes here using Schema or DB.';
            $down = '        // Reverse the schema changes made in up().';
        }

        $timestamp = date('Y_m_d_His');
        $file = $dir . '/' . $timestamp . '_' . $name . '.php';
        $suffix = 1;
        while (is_file($file)) $file = $dir . '/' . $timestamp . '_' . $name . '_' . $suffix++ . '.php';

        $body = "<?php\ndeclare(strict_types=1);\n\nuse LeafPHP\\Database\\Migration\\MigrationInterface;\nuse LeafPHP\\Database\\Schema\\Blueprint;\nuse LeafPHP\\Database\\Schema\\Schema;\n\nreturn new class implements MigrationInterface {\n    public function up(): void\n    {\n{$up}\n    }\n\n    public function down(): void\n    {\n{$down}\n    }\n};\n";
        if (file_put_contents($file, $body) === false) throw new \RuntimeException('Could not write migration file.');
        echo 'Created migration: ' . basename($file) . PHP_EOL;
        return 0;
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
    private function migrations(): MigrationRunner
    {
        return new MigrationRunner($this->basePath);
    }
    private function fresh(bool $seedAfter = false): int {
        fwrite(STDOUT, "WARNING: migrate:fresh is destructive. It rolls back recorded migrations and recreates the schema. Type YES to continue: ");
        if (trim((string)fgets(STDIN)) !== 'YES') { echo "Cancelled.\n"; return 0; }
        $this->migrations()->reset();
        $status = $this->migrations()->run();
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
