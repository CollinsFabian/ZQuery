# ZQuery Usage

Examples for common tasks. For every method and option, see [docs/API.md](docs/API.md).

## Connect

```php
use ZQuery\ZQuery;

// mysqli
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$zq = new ZQuery(new mysqli('127.0.0.1', 'user', 'pass', 'app'));

// PDO
$pdo = new PDO('mysql:host=127.0.0.1;dbname=app', 'user', 'pass', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$zq = new ZQuery($pdo);
```

Optional arguments: `grammar` (default MySQL), `prefix` (added to every table name) and `log` (see [Log queries](#log-queries)). Use named arguments to skip ahead: `new ZQuery($pdo, prefix: 'app_')`.

ZQuery never opens or closes connections. Create and close your own `mysqli` or `PDO`.

## Read

```php
$rows = $zq->table('users')
    ->select(['id', 'email'])
    ->where('status', '=', 'active')
    ->orderBy('id', 'DESC')
    ->limit(10, 20)          // 10 rows, skipping 20
    ->get();                 // list of rows

$user   = $zq->table('users')->where('id', '=', 7)->first();            // row or null
$name   = $zq->table('users')->where('id', '=', 7)->value('name');      // single value
$emails = $zq->table('users')->where('status', '=', 'active')->pluck('email');
$total  = $zq->table('users')->where('status', '=', 'active')->count();
$exists = $zq->table('users')->whereIn('role', ['admin', 'owner'])->exists();
```

## Filter

```php
$zq->table('users')
    ->where('age', '>', 18)
    ->where(['role' => 'admin', 'verified' => 1])         // equality on each key
    ->whereNull('deleted_at')
    ->get();

// OR conditions and groups
$zq->table('posts')
    ->where('tenant_id', '=', 7)
    ->where(fn ($q) => $q->where('title', 'like', 'a%')->orWhere('body', 'like', 'a%'))
    ->get();
// WHERE `tenant_id` = ? AND (`title` LIKE ? OR `body` LIKE ?)
```

`AND` binds tighter than `OR`. Put an `orWhere` inside a group whenever other conditions (tenant, soft delete, permissions) must always apply.

## Join, Group, Aggregate

```php
$zq->table('users')
    ->leftJoin('profiles', 'profiles.user_id', '=', 'users.id')
    ->select(['users.id', 'profiles.bio as about'])
    ->get();

$zq->table('orders')
    ->select(['user_id', $zq->raw('COUNT(*) AS total_orders')])
    ->groupBy('user_id')
    ->having('total_orders', '>', 1)
    ->get();
```

## Write

```php
// Insert one row
$zq->table('users')->insert(['email' => 'a@example.com', 'status' => 'active'])->executeInsert();   // 1

// Insert many rows in one statement
$zq->table('users')->insert([
    ['email' => 'a@example.com', 'status' => 'active'],
    ['email' => 'b@example.com', 'status' => 'pending'],
])->executeInsert();                                                                                 // 2

// Insert and get the new id
$id = $zq->table('users')->insert(['email' => 'c@example.com'])->insertGetId();

// Update / delete (a WHERE clause is required)
$zq->table('users')->where('id', '=', 10)->update(['status' => 'disabled'])->executeUpdate();        // affected rows
$zq->table('users')->where('id', '=', 10)->executeDelete();                                          // affected rows
```

Start each statement from a new `table()` call. A builder keeps its conditions, so reusing one carries earlier WHERE clauses into the next statement.

## Upsert

Insert rows, or update them when a unique key already exists.

```php
// Copies every non-key column when the row exists
$zq->table('products')->upsert([
    ['sku' => 'A1', 'name' => 'Apple', 'qty' => 5],
    ['sku' => 'B2', 'name' => 'Berry', 'qty' => 4],
], 'sku')->executeUpsert();

// Choose what changes
$zq->table('stock')->upsert(
    ['sku' => 'A1', 'qty' => 10],
    uniqueBy: ['sku'],
    update: [
        'qty',                               // copy the inserted value
        'hits'    => $zq->raw('hits + 1'),   // raw SQL, used as written
        'seen_at' => date('Y-m-d'),          // bound value
    ]
)->executeUpsert();
```

`uniqueBy` must be backed by a unique index. PostgreSQL uses it as the conflict target. MySQL reacts to any unique key, so there it documents intent. On MySQL, `executeUpsert()` returns 1 per inserted row, 2 per updated row and 0 per unchanged row.

## Transactions

```php
$zq->transaction(function (ZQuery $db) {
    $db->table('accounts')->where('id', '=', 1)->update(['balance' => 90])->executeUpdate();
    $db->table('accounts')->where('id', '=', 2)->update(['balance' => 110])->executeUpdate();
});
```

It commits when the callback returns and rolls back when it throws. Nested calls use savepoints.

Prefer manual control? Use `beginTransaction()`, `commit()` and `rollBack()`. They share the same nesting, so mixing them with `transaction()` is safe:

```php
$zq->beginTransaction();
try {
    // ...queries...
    $zq->commit();
} catch (\Throwable $e) {
    $zq->rollBack();
    throw $e;
}
```

## Raw SQL

```php
$row = $zq->statement('SELECT * FROM users WHERE email = ?', ['ada@example.com'])->fetch();
```

Use this for anything the builder lacks: subqueries, `BETWEEN`, `UNION`.

## Log Queries

```php
$zq = new ZQuery($mysqli, log: true);
$zq->table('users')->first();
\ZQuery\Utils\QueryLogger::dump();   // [14:02:11] SELECT * FROM `users` LIMIT 1 | [] (0.42 ms)
```

Pass a callable to `log` to send each query to your own logger. Logged parameters are not masked.

## Inspect SQL Without Running It

```php
$compiled = $zq->table('users')->where('status', '=', 'active')->limit(10)->compileSelect();
// ['sql' => 'SELECT * FROM `users` WHERE `status` = ? LIMIT 10', 'params' => ['active']]
```

`toSql()`, `compileInsert()`, `compileUpdate()` and `compileDelete()` work the same way.
