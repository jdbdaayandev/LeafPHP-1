# CLI commands

Run commands from the project root using `php leaf <command>`.

| Command | Purpose |
|---|---|
| `php leaf list` | List available commands. |
| `php leaf serve [host] [port]` | Start the optional PHP development server. Apache/XAMPP use does not require it. |
| `php leaf make:controller Name` | Create a controller. |
| `php leaf make:controller Name --resource` | Create a controller with seven resource actions. |
| `php leaf make:model Name` | Create a model. |
| `php leaf make:model User -mcr` | Create a model, migration, and resource controller in one command. |
| `php leaf make:model User -m` | Create a model and migration. |
| `php leaf make:model User -c` | Create a model and standard controller. |
| `php leaf make:model User -r` | Create a model and resource controller. |
| `php leaf make:migration create_books_table` | Generate a migration. |
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


See [MIGRATIONS.md](MIGRATIONS.md) for Laravel-style Blueprint migration syntax, supported column types, modifiers, and index declarations.


### Combined model-generation flags

`make:model` supports Laravel-style short flags: `-m` creates a migration, `-c` creates a controller, and `-r` creates a resource controller (and implies `-c`). Flags can be combined as `-mcr` or supplied separately. Long forms `--migration`, `--controller`, and `--resource` are also accepted. Resource generation creates the controller class but does not edit `routes/web.php`; register routes explicitly, for example `Router::resource('users', UserController::class);`. Generated resource action bodies are placeholders and must be implemented for your application.
