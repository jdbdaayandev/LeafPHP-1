# CLI commands

Run commands from the project root using `php leaf <command>`.

| Command | Purpose |
|---|---|
| `php leaf list` | List available commands. |
| `php leaf serve [host] [port]` | Start the optional PHP development server. Apache/XAMPP use does not require it. |
| `php leaf make:controller Name` | Create a controller. |
| `php leaf make:model Name` | Create a model. |
| `php leaf make:migration create_books_table` | Generate a timestamped create-table migration. |
| `php leaf make:session-migration` | Generate the database-session migration. |
| `php leaf make:seeder ExampleSeeder` | Generate a seeder. |
| `php leaf migrate` | Run pending migrations. |
| `php leaf migrate:status` | Show migration status. |
| `php leaf migrate:rollback [steps]` | Roll back recent batches. |
| `php leaf migrate:reset` | Roll back all recorded migrations. |
| `php leaf migrate:fresh` | Destructively rebuild schema after confirmation. |
| `php leaf migrate:fresh --seed` | Destructively rebuild schema, then run all seeders. |
| `php leaf db:seed [SeederClass]` | Run all seeders or one named seeder. |
| `php leaf key:generate` | Generate an application key in `.env`. |

`migrate:fresh` drops/recreates tables by running each recorded migration's `down()` method, then reruns migrations. It asks for `YES` as a safety check. `--seed` runs seeders only after migrations succeed. Back up important data first.

The built-in Schema helper and table prefix support target common SQLite and MySQL/MariaDB use. Raw SQL inside custom migrations must use SQL compatible with your selected driver. PostgreSQL connection configuration exists, but schema/migration SQL and every query-builder feature are not fully portable to PostgreSQL yet.


## Migration conventions

Migration files are stored in `database/migrations` and execute in filename order. Each file returns an anonymous class implementing `MigrationInterface` with `up()` and `down()` methods. Keep `up()` and `down()` paired: `down()` should reverse the change made by `up()`.

```php
<?php
declare(strict_types=1);

use LeafPHP\Database\Migration\MigrationInterface;
use LeafPHP\Database\Schema\Blueprint;
use LeafPHP\Database\Schema\Schema;

return new class implements MigrationInterface {
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
```

The generator scaffolds `Schema::create()` automatically for names shaped like `create_books_table`. For other names, it creates a clean empty `up()`/`down()` scaffold so the intended schema change is explicit rather than guessing the wrong table operation. The migration runner records a migration only after `up()` succeeds and wraps each migration plus its tracking update in a database transaction. SQLite and MySQL/MariaDB are supported by the migration repository.
