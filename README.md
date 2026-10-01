# ZQuery

A fluent SQL query builder for PHP 8.2 or newer.

- Fluent `select`, `insert`, `update` and `delete`
- mysqli and PDO
- MySQL/MariaDB and PostgreSQL
- Transactions with nesting
- SQL compilation for tests and debugging

## Install

```bash
composer require zi/zquery
```

## Quick Start

```php
use ZQuery\ZQuery;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$zq = new ZQuery(new mysqli('127.0.0.1', 'user', 'pass', 'app'));

$users = $zq->table('users')
    ->select(['id', 'email'])
    ->where('status', '=', 'active')
    ->latest('created_at')
    ->limit(10)
    ->get();
// [['id' => 42, 'email' => 'ada@example.com'], ...]
```

Using PDO instead:

```php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=app', 'user', 'pass', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$zq = new ZQuery($pdo);
```

ZQuery detects the engine from the object you pass. For PostgreSQL, pass the grammar as the second argument: `new ZQuery($pdo, new \ZQuery\Query\Grammar\PostgresGrammar())`.

## Common Helpers

```php
$user   = $zq->table('users')->where('email', '=', 'a@example.com')->first();   // row or null
$count  = $zq->table('users')->where('status', '=', 'active')->count();         // int
$emails = $zq->table('users')->where('status', '=', 'active')->pluck('email');  // list
$id     = $zq->table('users')->insert(['email' => 'new@example.com'])->insertGetId();
$zq->table('products')->upsert(['sku' => 'A1', 'qty' => 5], 'sku')->executeUpsert();   // insert or update
```

## Transactions

```php
$zq->transaction(function (ZQuery $db) {
    $db->table('users')->where('id', '=', 10)->update(['status' => 'disabled'])->executeUpdate();
    $db->statement('INSERT INTO audit_logs (action, user_id) VALUES (?, ?)', ['user.disabled', 10]);
});
```

Nested `transaction()` calls use savepoints, so an inner failure rolls back only the inner block.

## Docs

- [USAGE.md](USAGE.md): examples for common tasks
- [docs/API.md](docs/API.md): every method and option
