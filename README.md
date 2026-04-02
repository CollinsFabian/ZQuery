# ZQuery

ZQuery is a focused fluent SQL query builder for PHP 8.2+.

It gives you a lightweight database layer with:

- fluent `select`, `insert`, `update`, and `delete` queries
- PDO and MySQLi support
- MySQL and PostgreSQL grammar support
- transaction helpers
- SQL compilation helpers for testing and debugging

## Install

```bash
composer require zi/zquery
```

## Quick Start

```php
use ZQuery\ZQuery;
use ZQuery\Query\Grammar\MysqlGrammar;

$pdo = new PDO('mysql:host=127.0.0.1;dbname=app', 'user', 'pass', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$zq = new ZQuery([
    'engine' => 'pdo',
    'pdo' => $pdo,
    'grammar' => new MysqlGrammar(),
]);

$users = $zq->table('users')
    ->select(['id', 'email'])
    ->where('status', '=', 'active')
    ->latest('created_at')
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

## Common Helpers

```php
$user = $zq->table('users')
    ->where('email', '=', 'a@example.com')
    ->first();

$activeCount = $zq->table('users')
    ->where('status', '=', 'active')
    ->count();

$emails = $zq->table('users')
    ->where('status', '=', 'active')
    ->pluck('email');
```

Returns:

```php
$user = ['id' => 7, 'email' => 'a@example.com', 'status' => 'active'];
$activeCount = 24;
$emails = ['a@example.com', 'b@example.com', 'c@example.com'];
```

## SQL Compilation

```php
$compiled = $zq->table('users')
    ->where('users.status', '=', 'active')
    ->latest('users.created_at')
    ->limit(10)
    ->compileSelect();
```

Returns:

```php
[
    'sql' => 'SELECT * FROM `users` WHERE `users`.`status` = ? ORDER BY `users`.`created_at` DESC LIMIT 10',
    'params' => ['active'],
]
```

## Transactions

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

## More Docs

See [USAGE.md](USAGE.md) for the extended usage guide with more examples and result shapes.
