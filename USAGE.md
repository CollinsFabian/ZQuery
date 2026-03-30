# ZQuery Usage

This document shows how to configure ZQuery, run queries, and use the mapper/repository/unit-of-work pieces.

## Install (Composer)

```bash
composer require zi/zquery
```

## Configure And Build A Query

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

## Entities

Create an entity that extends `ZQuery\Entity\Base`.

```php
namespace App\Entity;

use ZQuery\Entity\Base;
use ZQuery\Entity\Attributes\Column;

class User extends Base
{
    protected static string $table = 'users';
    protected static string $primaryKey = 'id';

    public int $id;

    #[Column('email')]
    public string $email;

    public string $status;
}
```

## Mapper And Repository

```php
namespace App\Mapper;

use ZQuery\Mapper\BaseMapper;
use App\Entity\User;

class UserMapper extends BaseMapper
{
    protected string $entityClass = User::class;
}
```

```php
namespace App\Repository;

use ZQuery\Repository\BaseRepository;
use App\Mapper\UserMapper;

class UserRepository extends BaseRepository
{
    public function __construct(UserMapper $mapper)
    {
        parent::__construct($mapper);
    }
}
```

### Usage
```php
use ZQuery\Mapper\IdentityMap;
use App\Mapper\UserMapper;
use App\Repository\UserRepository;

$identityMap = new IdentityMap();
$mapper = new UserMapper($zq->getConnection(), $zq->getGrammar(), $identityMap);
$repo = new UserRepository($mapper);

$user = $repo->find(1);
$all = $repo->findAll();

$user->fill(['status' => 'disabled']);
$repo->save($user);

$repo->delete($user);
```

## Unit Of Work

`UnitOfWork` requires a mapper factory. The factory receives the entity and returns the correct mapper.

```php
use ZQuery\UnitOfWork\UnitOfWork;
use ZQuery\Mapper\IdentityMap;
use App\Mapper\UserMapper;

$identityMap = new IdentityMap();

$uow = new UnitOfWork($pdo, function ($entity) use ($zq, $identityMap) {
    $class = get_class($entity);
    return match ($class) {
        \App\Entity\User::class => new UserMapper($zq->getConnection(), $zq->getGrammar(), $identityMap),
        default => throw new \RuntimeException("No mapper for {$class}"),
    };
});

$user = new \App\Entity\User(['email' => 'a@example.com', 'status' => 'active']);
$uow->registerNew($user);
$uow->commit();
```

## Notes

- `grammar` is optional; MySQL is the default.
- `where()` always uses bound parameters to prevent SQL injection.
- `update()` and `delete()` require a WHERE clause.
