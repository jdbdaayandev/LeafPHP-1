# Database Migrations

LeafPHP migrations return an object implementing `MigrationInterface`. Put migration files in `database/migrations`; run them with `php leaf migrate`.

## Create a migration

```bash
php leaf make:migration create_books_table
php leaf migrate
php leaf migrate:status
php leaf migrate:rollback
```

## Laravel-style Blueprint

Migrations use `Schema` and `Blueprint` so database-specific SQL stays inside LeafPHP rather than each migration.

```php
<?php

declare(strict_types=1);

use LeafPHP\Database\Migration\MigrationInterface;
use LeafPHP\Database\Schema\Blueprint;
use LeafPHP\Database\Schema\Schema;

return new class implements MigrationInterface {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
```

## Supported column definitions

- `$table->id()`
- `$table->string('name', 120)`
- `$table->text('description')`
- `$table->longText('payload')`
- `$table->integer('count')` / `$table->unsignedInteger('count')`
- `$table->bigInteger('count')` / `$table->unsignedBigInteger('user_id')`
- `$table->boolean('active')`
- `$table->timestamp('published_at')`, `$table->date('birth_date')`, `$table->dateTime('starts_at')`
- `$table->timestamps()`

Column modifiers can be chained: `nullable()`, `default($value)`, `primary()`, `unique()`, and `index()`.

Indexes can also be declared with column names: `$table->index('email')`, `$table->unique(['first_name', 'last_name'])`, or `$table->index(['category_id', 'created_at'], 'posts_category_created_index')`.

## Schema helpers

- `Schema::create('table_name', function (Blueprint $table): void { ... })`
- `Schema::dropIfExists('table_name')`
- `Schema::hasTable('table_name')`

LeafPHP compiles the supported Blueprint definitions for SQLite and MySQL. Keep raw SQL out of migrations unless the database operation is not yet represented by the Blueprint API.
