# ZQuery API Reference

Requires PHP 8.2 or newer. Namespace root: `ZQuery\`.

## Setup

```php
new ZQuery(
    PDO|mysqli|ConnectionInterface $connection,
    ?GrammarInterface $grammar = null,   // default: MysqlGrammar
    string $prefix = '',
    bool|callable $log = false
);
```

```php
$zq = new ZQuery($mysqli);                                // mysqli
$zq = new ZQuery($pdo, prefix: 'app_');                   // PDO with a table prefix
$zq = new ZQuery($pdo, new PostgresGrammar(), log: true);
```

| Argument | Description |
| --- | --- |
| `$connection` | Your connected `mysqli` or `PDO`. ZQuery picks the engine from its type. A `ConnectionInterface` implementation is used as is. |
| `$grammar` | `MysqlGrammar` (default) or `PostgresGrammar`. |
| `$prefix` | Prepended to every name passed to `table()`. |
| `$log` | `true`, or `callable(string $sql, array $params, float $ms)`. See [Logging](#logging). |

ZQuery uses the connection you give it and never opens or closes one. Turn on exceptions in your driver (`PDO::ERRMODE_EXCEPTION`, or `MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT`).

## ZQuery

| Method | Description |
| --- | --- |
| `table(string $table): QueryBuilder` | Start a query. Returns a new builder each call. |
| `raw(string $sql): RawExpression` | Wrap SQL that must not be escaped (see [Raw expressions](#raw-expressions)). |
| `statement(string $sql, array $params = [])` | Run SQL with `?` placeholders. Returns a statement with `fetch()`, `fetchAll()` and `rowCount()`. |
| `transaction(callable $callback): mixed` | Run the callback in a transaction and return its result. |
| `getConnection()` | The connection. Use it for `lastInsertId()` and `isConnected()`. |
| `getGrammar()` | The active grammar. |

### transaction()

Commits when the callback returns, and rolls back and rethrows when it throws. The callback can take the `ZQuery` instance or use `$this`:

```php
$zq->transaction(function (ZQuery $db) {
    $db->table('users')->where('id', '=', 10)->update(['status' => 'disabled'])->executeUpdate();
});

$affected = $zq->transaction(fn () => $this->table('orders')->insert(['total' => 10])->executeInsert());
```

Nested calls create savepoints, so an exception in an inner block rolls back only that block. In MySQL, DDL statements (`CREATE`, `ALTER`, ...) commit implicitly.

## QueryBuilder

Methods return the builder, so calls chain in any order. Column and table names are escaped for the active database, and `col as alias` is supported.

### Select

| Method | Description |
| --- | --- |
| `select(array $columns = ['*'])` | Set the columns. Strings are escaped, `RawExpression` values are used as written. |
| `addSelect(array $columns)` | Add columns to the selection. |

### Joins

`join($table, $first, $operator, $second, $type = 'INNER')`, `leftJoin(...)`, `rightJoin(...)`

```php
$zq->table('users')->leftJoin('profiles', 'profiles.user_id', '=', 'users.id');
// SELECT * FROM `users` LEFT JOIN `profiles` ON `profiles`.`user_id` = `users`.`id`
```

Types: `INNER`, `LEFT`, `RIGHT`, `FULL` (MySQL has no `FULL JOIN`). An unsupported type or operator throws `InvalidArgumentException`.

### Filtering

| Call | Result |
| --- | --- |
| `where('age', '>', 18)` | `` `age` > ? `` |
| `where('status', null, 'active')` | `` `status` = ? `` (operator defaults to `=`) |
| `where(['status' => 'active', 'role' => 'admin'])` | equality on each key, joined with AND |
| `where([['id', '>', 5], ['age', '<', 30]])` | list of `[column, operator, value]` |
| `where(fn ($q) => ...)` | a parenthesised group |
| `orWhere(...)` | same forms as `where()`, joined with OR |
| `whereIn($col, $values)` / `orWhereIn(...)` | `IN (?, ?, ...)`. An empty array throws `InvalidArgumentException`. |
| `whereNull($col)` / `whereNotNull($col)` | `IS NULL` / `IS NOT NULL`. `orWhere...` variants exist. |

`orWhere()` with an array or closure makes one group, so its members stay AND-ed:

```php
$zq->table('users')
    ->where('tenant_id', '=', 7)
    ->where(fn ($q) => $q->where('role', '=', 'admin')->orWhere('role', '=', 'owner'))
    ->orWhere(['vip' => 1, 'banned' => 0])
    ->compileSelect();
// WHERE `tenant_id` = ? AND (`role` = ? OR `role` = ?) OR (`vip` = ? AND `banned` = ?)
```

`AND` binds tighter than `OR`: `where(a)->where(b)->orWhere(c)` means `(a AND b) OR c`. Put an `orWhere` inside a group when other conditions (tenant, soft delete, permissions) must always apply.

`where('col', '=', null)` produces `= NULL`, which never matches. Use `whereNull()`.

### Grouping and ordering

| Method | Description |
| --- | --- |
| `groupBy($col)` / `groupByMany(array $cols)` | Add `GROUP BY` columns. |
| `having($col, $operator, $value)` | Add a `HAVING` condition (AND only). The column can be a select alias. |
| `orderBy($col, $direction = 'ASC')` | `DESC` (any case) sorts descending, anything else ascending. |
| `latest($col = 'created_at')` / `oldest(...)` | `orderBy` with `DESC` / `ASC`. |
| `limit(int $limit, ?int $offset = null)` | `LIMIT n OFFSET m`. Replaces an earlier limit. |

### Reading

These methods leave the builder unchanged, so you can reuse it.

| Method | Returns |
| --- | --- |
| `get(array $columns = ['*'])` | All matching rows. |
| `first(array $columns = ['*'])` | The first row, or `null`. |
| `value(string $column)` | One column of the first row, or `null`. |
| `exists()` | `bool`. |
| `count(string $column = '*')` | `int`. Ignores `orderBy()` and `limit()`. With `groupBy()` it counts the first group only. |
| `pluck(string $column)` | A list of one column across all rows. |

### Writing

| Method | Description |
| --- | --- |
| `insert(array $data)` | Set the data: one row (`['col' => value]`) or a list of rows. All rows need the same columns. |
| `executeInsert(): int` | Run the insert. Returns the number of inserted rows. |
| `insertGetId(): int\|string` | Insert one row and return its generated id. Throws `LogicException` for several rows. |
| `update(array $data)` | Set `column => value` pairs. |
| `executeUpdate(): int` | Run the update on rows matched by `where()`. Returns affected rows. |
| `executeDelete(): int` | Delete rows matched by `where()`. Returns affected rows. |

```php
$zq->table('users')->insert([
    ['email' => 'a@example.com', 'status' => 'active'],
    ['email' => 'b@example.com', 'status' => 'pending'],
])->executeInsert();
// INSERT INTO `users` (`email`, `status`) VALUES (?, ?), (?, ?)
```

- `update` and `delete` throw `RuntimeException` without a `WHERE` clause. To change every row, use `statement()`.
- MySQL allows 65,535 placeholders per statement (rows x columns). Split large imports with `array_chunk()`.
- `insertGetId()` returns an `int` for numeric ids. On PostgreSQL with PDO, use `statement('INSERT ... RETURNING id', ...)` instead.
- On MySQL, use `limit()` without an offset in `update` and `delete`.

### Inspecting SQL

`toSql()` returns the SELECT string. `compileSelect()`, `compileInsert()`, `compileUpdate()` and `compileDelete()` return `['sql' => ..., 'params' => [...]]` without touching the database.

### Cloning

`clone $builder` gives an independent copy:

```php
$active = $zq->table('users')->where('status', '=', 'active');
$admins = (clone $active)->where('role', '=', 'admin');
```

## Raw Expressions

`$zq->raw($sql)` (or `new \ZQuery\Query\RawExpression($sql)`) marks SQL that is used as written. It works in `select()` and `addSelect()`.

```php
$zq->table('orders')->select(['user_id', $zq->raw('SUM(total) AS spent')])->groupBy('user_id')->get();
```

## Safety

- Values are always sent as bound parameters.
- Table and column names are escaped. If a column name comes from user input, check it against an allow-list first.
- Operators must be one of `=`, `<`, `>`, `<=`, `>=`, `<>`, `!=`, `<=>`, `LIKE`, `NOT LIKE`. Anything else throws `InvalidArgumentException`.
- `raw()` and `statement()` SQL is sent unchanged. Never build it from user input.

## Logging

```php
// Built-in log
$zq = new ZQuery($mysqli, log: true);
\ZQuery\Utils\QueryLogger::dump();    // also: ::all(), ::clear()

// Your own callback
$zq = new ZQuery(
    $pdo,
    log: fn (string $sql, array $params, float $ms) => error_log(sprintf('%.2fms %s', $ms, $sql))
);
```

Every statement is logged, including failed ones. Parameters are not masked, so avoid logging them when they can hold secrets. `QueryLogger` keeps entries until the process ends, so call `clear()` in long-running workers.

## Exceptions

| Exception | When |
| --- | --- |
| `ZQuery\Exceptions\QueryException` | A statement fails. The message includes the SQL and parameters, so avoid showing it to end users. |
| `ZQuery\Exceptions\ConnectionException` | The connection is unavailable. |
| `InvalidArgumentException` | Bad operator or join type, empty `whereIn()`, or mismatched insert rows. |
| `RuntimeException` | `update` or `delete` without `WHERE`, or `update` without columns. |

## Limits

- No subqueries, `UNION`, `BETWEEN` or upserts. Use `statement()`.
- `having()` supports AND only.
- The table prefix applies to names passed to `table()`, not to joined tables or raw SQL.
- `getConnection()->lastInsertId()` returns `0` after any later query. Use `insertGetId()` to avoid this.
