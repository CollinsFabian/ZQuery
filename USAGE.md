# ZQuery Usage

This is the extended usage guide for ZQuery.

For the package overview and quick start, see [README.md](README.md).

## Install (Composer)

```bash
composer require zi/zquery
```

## Configure And Build Queries

### PDO
```php
use ZQuery\ZQuery;
use ZQuery\Query\Grammar\MysqlGrammar;

$pdo = new PDO('mysql:host=127.0.0.1;dbname=app', 'user', 'pass', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$zq = new ZQuery([
    'engine' => 'pdo',
    'pdo' => $pdo,
    'prefix' => '', // optional
    'grammar' => new MysqlGrammar(), // optional
]);

$users = $zq->table('users')
    ->select(['id', 'email'])
    ->where('status', '=', 'active')
    ->orderBy('id', 'DESC')
    ->limit(10)
    ->get();
```

Returns:

```php
[
    ['id' => 42, 'email' => 'ada@example.com'],
    ['id' => 41, 'email' => 'grace@example.com'],
]
```

### MySQLi
```php
use ZQuery\ZQuery;
use ZQuery\Query\Grammar\MysqlGrammar;

$mysqli = new mysqli('127.0.0.1', 'user', 'pass', 'app');

$zq = new ZQuery([
    'engine' => 'mysqli',
    'mysqli' => $mysqli,
    'prefix' => '',
    'grammar' => new MysqlGrammar(),
]);

$rows = $zq->table('users')
    ->where([['status', '=', 'active'], ['role', '=', 'admin']])
    ->get();
```

Returns:

```php
[
    [
        'id' => 1,
        'email' => 'admin@example.com',
        'status' => 'active',
        'role' => 'admin',
    ],
]
```

## Builder Helpers

```php
$user = $zq->table('users')
    ->where('email', '=', 'a@example.com')
    ->first();

$activeCount = $zq->table('users')
    ->where('status', '=', 'active')
    ->count();

$hasAdmins = $zq->table('users')
    ->whereIn('role', ['admin', 'owner'])
    ->exists();

$emails = $zq->table('users')
    ->where('status', '=', 'active')
    ->pluck('email');
```

Returns:

```php
$user = [
    'id' => 7,
    'email' => 'a@example.com',
    'status' => 'active',
];

$activeCount = 24;

$hasAdmins = true;

$emails = [
    'a@example.com',
    'b@example.com',
    'c@example.com',
];
```

## SQL Compilation

The public builder API now exposes compilation directly when you want to inspect SQL before execution.

```php
$compiled = $zq->table('users')
    ->where('users.status', '=', 'active')
    ->latest('users.created_at')
    ->limit(10)
    ->compileSelect();

$compiled['sql'];
$compiled['params'];
```

Returns:

```php
$compiled = [
    'sql' => 'SELECT * FROM `users` WHERE `users`.`status` = ? ORDER BY `users`.`created_at` DESC LIMIT 10',
    'params' => ['active'],
];
```

## Inserts, Updates, Deletes

```php
$qb = $zq->table('users');

// Insert
$qb->insert([
    'email' => 'a@example.com',
    'status' => 'active',
])->executeInsert();

// Update
$qb->where('id', '=', 10)
   ->update(['status' => 'disabled'])
   ->executeUpdate();

// Delete
$qb->where('id', '=', 10)->executeDelete();
```

Returns:

```php
$inserted = 1; // affected rows
$updated = 1;  // affected rows
$deleted = 1;  // affected rows
```

## Raw Expressions

Use `RawExpression` to bypass identifier escaping for expressions.

```php
use ZQuery\Query\RawExpression;

$rows = $zq->table('orders')
    ->select([
        'user_id',
        new RawExpression('COUNT(*) AS total_orders'),
    ])
    ->groupBy('user_id')
    ->get();
```

Returns:

```php
[
    ['user_id' => 1, 'total_orders' => 5],
    ['user_id' => 2, 'total_orders' => 3],
]
```

## Transactions And Raw Statements

```php
$zq->transaction(function () {
    $this->table('users')
        ->where('id', '=', 10)
        ->update(['status' => 'disabled'])
        ->executeUpdate();

    $this->statement(
        'INSERT INTO audit_logs (action, user_id) VALUES (?, ?)',
        ['user.disabled', 10]
    );
});
```

Returns:

```php
null
```

If you prefer, `transaction()` still accepts a callback parameter too:

```php
$zq->transaction(function (ZQuery $db) {
    $db->table('users')->where('status', '=', 'active')->count();
});
```

Returns:

```php
24
```

## Notes

- `grammar` is optional; MySQL is the default.
- `where()` always uses bound parameters to prevent SQL injection.
- `update()` and `delete()` require a WHERE clause.
- `toSql()`, `compileSelect()`, `compileInsert()`, `compileUpdate()`, and `compileDelete()` are available for inspection and testing.
- ZQuery now ships as a query-builder-only package; entity mapping and repository abstractions were removed.
