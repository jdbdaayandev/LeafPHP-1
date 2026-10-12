# Query Builder

LeafPHP's fluent Query Builder is available through `LeafPHP\Database\DB::table()`. It targets the configured PDO connection (currently MySQL). Values are bound as prepared-statement parameters. Table and column identifiers are validated separately.

```php
use LeafPHP\Database\DB;

$users = DB::table('users')
    ->select('id', 'name', 'email')
    ->where('active', 1)       // equality shorthand
    ->where('age', '>=', 18)
    ->whereIn('role', ['admin', 'editor'])
    ->whereNull('deleted_at')
    ->orderBy('name')
    ->limit(20)
    ->get();

$user = DB::table('users')->where('id', 10)->first();
```

## Read queries

- `select(...$columns)`, `addSelect(...$columns)`, `distinct()`
- `where`, `orWhere`, `whereNull`, `whereNotNull`, `orWhereNull`, `orWhereNotNull`
- `whereIn`, `whereNotIn`, `orWhereIn`, `whereBetween`, `whereNotBetween`, `whereColumn`, `whereNested`
- `whereExists($trustedSubquery, $bindings)`, `whereRaw($trustedSql, $bindings)`
- `join`, `innerJoin`, `leftJoin`, `rightJoin`, `crossJoin`
- `orderBy`, `latest`, `oldest`, `inRandomOrder`, `groupBy`, `having`, `orHaving`
- `limit`, `offset`, `take`, `skip`, `get`, `first`, `value`, `pluck`
- `count`, `sum`, `avg`, `min`, `max`, `exists`, `doesntExist`
- `paginate($perPage = 15, $page = null)`, `simplePaginate($perPage = 15, $page = null)`

Pagination returns an array. `paginate()` includes `data`, `total`, `per_page`, `current_page`, `last_page`, `from`, `to`, `has_more_pages`, `prev_page`, and `next_page`. `simplePaginate()` avoids a total-count query and returns navigation fields without a total.

## Writes

- `insert($values)`, `insertMany($rows)`, `insertOrIgnore($values)`, `insertGetId($values)`
- `upsert($values, $uniqueBy, $update = null)` (MySQL `ON DUPLICATE KEY UPDATE`; the database must have a matching unique key)
- `update($values)`, `updateOrInsert($attributes, $values)`, `delete()`
- `increment($column, $amount = 1, $extra = [])`, `decrement(...)`

```php
DB::table('users')->insert(['name' => 'Ari', 'email' => 'ari@example.test']);
DB::table('users')->where('id', 10)->update(['name' => 'Ari D.']);
DB::table('users')->where('id', 10)->increment('login_count');
DB::table('users')->where('id', 10)->delete();
```

**Caution:** `update()` and `delete()` without a `where()` condition affect the whole table. Always verify the condition before executing destructive operations.

## Transactions and diagnostics

```php
$result = DB::transaction(function ($pdo) {
    DB::table('orders')->insert(['reference' => 'ORD-001']);
    DB::table('audit_logs')->insert(['event' => 'order-created']);
    return true;
});
```

Manual alternatives are `DB::beginTransaction()`, `DB::commit()`, and `DB::rollBack()`. Inspect generated SQL using `$query->toSql()` and its parameter values using `$query->getBindings()`. `toRawSql()` is a debugging convenience only; do not execute its output or expose sensitive binding values in production logs.

`whereRaw()` and `whereExists()` accept SQL fragments, so use only application-authored SQL and always bind untrusted values. Identifiers cannot be bound as parameters and are validated by the builder. Query Builder methods are integration-tested against the configured database by the application developer; syntax checks alone do not verify every MySQL version or schema.


## Pagination

`paginate()` returns a metadata array and the current page's rows. `simplePaginate()` avoids the total-count query and only checks whether another page exists. Both default to 15 rows and read the `page` query parameter unless an explicit page is supplied.

```php
$page = DB::table('books')->where('published', 1)->orderBy('title')->paginate(10);
// ['data', 'total', 'per_page', 'current_page', 'last_page',
//  'from', 'to', 'has_more_pages', 'prev_page', 'next_page']

$results = DB::table('books')->orderBy('id')->simplePaginate(20);
// ['data', 'per_page', 'current_page', 'has_more_pages',
//  'prev_page', 'next_page']

$pageNumber = max(1, (int) request()->query('page', 1));
$page = Book::query()->paginate(10, $pageNumber);
```

Both methods return arrays, not paginator objects or rendered HTML links. Build navigation links in your view using the returned page metadata. Page size must be at least 1. Existing query conditions and ordering are retained.
