<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use ZQuery\Query\Grammar\MysqlGrammar;
use ZQuery\Query\Grammar\PostgresGrammar;
use ZQuery\Query\QueryBuilder;
use ZQuery\Query\RawExpression;
use ZQuery\Tests\Doubles\NullConnection;

function builder(object $grammar, string $table = 'users'): QueryBuilder
{
    return new QueryBuilder($table, new NullConnection(), $grammar);
}

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . PHP_EOL . 'Expected: ' . var_export($expected, true) . PHP_EOL . 'Actual: ' . var_export($actual, true)
        );
    }
}

function run(string $name, callable $test): void
{
    $test();
    echo "[PASS] {$name}" . PHP_EOL;
}

run('MySQL select compilation wraps dotted identifiers consistently', function (): void {
    $query = builder(new MysqlGrammar(), 'users')
        ->select(['users.id', 'profiles.display_name as profile_name', new RawExpression('COUNT(*) AS total')])
        ->leftJoin('profiles', 'profiles.user_id', '=', 'users.id')
        ->where('users.status', '=', 'active')
        ->whereIn('users.role', ['admin', 'owner'])
        ->whereNull('users.deleted_at')
        ->groupByMany(['users.id', 'profiles.display_name'])
        ->having('users.id', '>', 10)
        ->orderBy('profiles.display_name')
        ->limit(5, 10);

    $compiled = $query->compileSelect();

    assertSameValue(
        'SELECT `users`.`id`, `profiles`.`display_name` AS `profile_name`, COUNT(*) AS total FROM `users` LEFT JOIN `profiles` ON `profiles`.`user_id` = `users`.`id` WHERE `users`.`status` = ? AND `users`.`role` IN (?, ?) AND `users`.`deleted_at` IS NULL GROUP BY `users`.`id`, `profiles`.`display_name` HAVING `users`.`id` > ? ORDER BY `profiles`.`display_name` ASC LIMIT 5 OFFSET 10',
        $compiled['sql'],
        'MySQL select SQL did not match.'
    );

    assertSameValue(['active', 'admin', 'owner', 10], $compiled['params'], 'MySQL select bindings did not match.');
});

run('Postgres update compilation uses cte without quoting the inner query', function (): void {
    $query = builder(new PostgresGrammar(), 'users')
        ->where('users.id', '>', 100)
        ->orderBy('users.created_at', 'DESC')
        ->limit(2)
        ->update([
            'status' => 'archived',
        ]);

    $compiled = $query->compileUpdate();

    assertSameValue(
        'WITH __zq_limit__ AS (SELECT ctid FROM "users" WHERE "users"."id" > ? ORDER BY "users"."created_at" DESC LIMIT 2) UPDATE "users" SET "status" = ? WHERE ctid IN (SELECT ctid FROM __zq_limit__)',
        $compiled['sql'],
        'Postgres update SQL did not match.'
    );

    assertSameValue([100, 'archived'], $compiled['params'], 'Postgres update bindings must follow SQL order (CTE WHERE first, then SET).');
});

run('Postgres delete compilation preserves ordering and limit', function (): void {
    $query = builder(new PostgresGrammar(), 'audit_logs')
        ->where('audit_logs.level', '=', 'debug')
        ->latest('audit_logs.created_at')
        ->limit(50);

    $compiled = $query->compileDelete();

    assertSameValue(
        'WITH __zq_limit__ AS (SELECT ctid FROM "audit_logs" WHERE "audit_logs"."level" = ? ORDER BY "audit_logs"."created_at" DESC LIMIT 50) DELETE FROM "audit_logs" WHERE ctid IN (SELECT ctid FROM __zq_limit__)',
        $compiled['sql'],
        'Postgres delete SQL did not match.'
    );

    assertSameValue(['debug'], $compiled['params'], 'Postgres delete bindings did not match.');
});

run('Bindings follow SQL order even when having() is called before where()', function (): void {
    $compiled = builder(new MysqlGrammar(), 'orders')
        ->groupBy('orders.user_id')
        ->having('orders.total', '>', 100)
        ->where('orders.status', '=', 'paid')
        ->compileSelect();

    assertSameValue(['paid', 100], $compiled['params'], 'Bindings were not in SQL order.');
});

run('Unsupported operators are rejected in where, having and join', function (): void {
    $attempts = [
        fn() => builder(new MysqlGrammar())->where('id', '= 1 OR 1=1 --', 5),
        fn() => builder(new MysqlGrammar())->groupBy('id')->having('id', '; DROP TABLE users', 1),
        fn() => builder(new MysqlGrammar())->join('posts', 'posts.user_id', 'OR 1=1', 'users.id'),
    ];

    foreach ($attempts as $attempt) {
        $rejected = false;
        try {
            $attempt();
        } catch (InvalidArgumentException) {
            $rejected = true;
        }
        assertSameValue(true, $rejected, 'Malicious operator was not rejected.');
    }
});

run('Operators are normalised and associative where() is supported', function (): void {
    $compiled = builder(new MysqlGrammar())
        ->where('name', ' like ', 'a%')
        ->where(['status' => 'active', 'role' => 'admin'])
        ->compileSelect();

    assertSameValue(
        'SELECT * FROM `users` WHERE `name` LIKE ? AND `status` = ? AND `role` = ?',
        $compiled['sql'],
        'Normalised operator / associative where SQL did not match.'
    );
    assertSameValue(['a%', 'active', 'admin'], $compiled['params'], 'Associative where bindings did not match.');
});

run('ZQuery infers the engine from the connection it receives', function (): void {
    $pdo = new ZQuery\ZQuery(unconnectedPdo());
    assertSameValue(true, $pdo->getConnection() instanceof ZQuery\Connection\PdoConnection, 'PDO should use PdoConnection.');

    $custom = new NullConnection();
    $zq = new ZQuery\ZQuery($custom, new PostgresGrammar(), 'app_');
    assertSameValue($custom, $zq->getConnection(), 'A ConnectionInterface should be used as is.');
    assertSameValue(true, $zq->getGrammar() instanceof PostgresGrammar, 'Grammar argument should be used.');

    $zq->table('users')->where('id', '=', 1)->first();
    assertSameValue('SELECT * FROM "app_users" WHERE "id" = ? LIMIT 1', $custom->executed[0], 'Prefix should apply to table().');
});

run('first() and get() do not mutate the builder', function (): void {
    $connection = new NullConnection();
    $query = new QueryBuilder('users', $connection, new MysqlGrammar());
    $query->where('status', '=', 'active');

    $query->first();
    $query->get();

    assertSameValue('SELECT * FROM `users` WHERE `status` = ? LIMIT 1', $connection->executed[0], 'first() SQL did not match.');
    assertSameValue('SELECT * FROM `users` WHERE `status` = ?', $connection->executed[1], 'get() inherited first()\'s LIMIT.');
});

run('count() compiles an aggregate and ignores ORDER BY and LIMIT', function (): void {
    $connection = new NullConnection();
    (new QueryBuilder('users', $connection, new MysqlGrammar()))
        ->where('status', '=', 'active')
        ->orderBy('id')
        ->limit(5, 10)
        ->count();

    assertSameValue(
        'SELECT COUNT(*) AS aggregate FROM `users` WHERE `status` = ? LIMIT 1',
        $connection->executed[0],
        'count() SQL did not match.'
    );
});

run('exists() selects a constant instead of every column', function (): void {
    $connection = new NullConnection();
    (new QueryBuilder('users', $connection, new MysqlGrammar()))->whereNull('deleted_at')->exists();

    assertSameValue('SELECT 1 FROM `users` WHERE `deleted_at` IS NULL LIMIT 1', $connection->executed[0], 'exists() SQL did not match.');
});

run('Clones are independent of the original builder', function (): void {
    $original = builder(new MysqlGrammar())->where('a', '=', 1);
    $copy = (clone $original)->where('b', '=', 2);

    assertSameValue(['a' => 1], ['a' => $original->compileSelect()['params'][0]], 'Original lost its binding.');
    assertSameValue(1, count($original->compileSelect()['params']), 'Clone leaked a binding into the original.');
    assertSameValue([1, 2], $copy->compileSelect()['params'], 'Clone bindings did not match.');
});

run('Unsupported join types are rejected', function (): void {
    $rejected = false;
    try {
        builder(new MysqlGrammar())->join('posts', 'posts.user_id', '=', 'users.id', 'INNER JOIN evil ON 1=1 --');
    } catch (InvalidArgumentException) {
        $rejected = true;
    }
    assertSameValue(true, $rejected, 'Malicious join type was not rejected.');
});

function expectInvalid(callable $fn, string $message): void
{
    try {
        $fn();
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException($message);
}

/**
 * A PDO that was never connected. ZQuery only wraps it, so unit tests need no database driver.
 */
function unconnectedPdo(): PDO
{
    return (new ReflectionClass(PDO::class))->newInstanceWithoutConstructor();
}

/**
 * A connection whose statements always fail, to check failure paths without a database.
 */
function failingConnection(): ZQuery\Connection\ConnectionInterface
{
    return new class implements ZQuery\Connection\ConnectionInterface {
        public function prepare(string $sql): ZQuery\Connection\StatementInterface
        {
            throw new ZQuery\Exceptions\QueryException($sql, [], 'forced failure');
        }

        public function execute(string $sql, array $params = []): ZQuery\Connection\StatementInterface
        {
            throw new ZQuery\Exceptions\QueryException($sql, $params, 'forced failure');
        }

        public function beginTransaction(): void {}

        public function commit(): void {}

        public function rollBack(): void {}

        public function lastInsertId(): string|int
        {
            return 0;
        }

        public function isConnected(): bool
        {
            return false;
        }
    };
}

run('orWhere joins with OR and keeps bindings in SQL order', function (): void {
    $compiled = builder(new MysqlGrammar())
        ->where('status', '=', 'active')
        ->orWhere('role', '=', 'admin')
        ->orWhereIn('id', [1, 2])
        ->orWhereNull('deleted_at')
        ->compileSelect();

    assertSameValue(
        'SELECT * FROM `users` WHERE `status` = ? OR `role` = ? OR `id` IN (?, ?) OR `deleted_at` IS NULL',
        $compiled['sql'],
        'orWhere SQL did not match.'
    );
    assertSameValue(['active', 'admin', 1, 2], $compiled['params'], 'orWhere bindings did not match.');
});

run('Closures create parenthesised groups', function (): void {
    $compiled = builder(new MysqlGrammar())
        ->where('tenant_id', '=', 7)
        ->where(fn(QueryBuilder $q) => $q->where('role', '=', 'admin')->orWhere('role', '=', 'owner'))
        ->orWhere(fn(QueryBuilder $q) => $q->where('vip', '=', 1)->where('age', '>', 30))
        ->where(fn(QueryBuilder $q) => null)
        ->compileSelect();

    assertSameValue(
        'SELECT * FROM `users` WHERE `tenant_id` = ? AND (`role` = ? OR `role` = ?) OR (`vip` = ? AND `age` > ?)',
        $compiled['sql'],
        'Grouped where SQL did not match.'
    );
    assertSameValue([7, 'admin', 'owner', 1, 30], $compiled['params'], 'Grouped where bindings did not match.');
});

run('orWhere with an array becomes one AND-ed group', function (): void {
    $compiled = builder(new MysqlGrammar())
        ->where('a', '=', 1)
        ->orWhere(['b' => 2, 'c' => 3])
        ->compileSelect();

    assertSameValue('SELECT * FROM `users` WHERE `a` = ? OR (`b` = ? AND `c` = ?)', $compiled['sql'], 'orWhere array SQL did not match.');
    assertSameValue([1, 2, 3], $compiled['params'], 'orWhere array bindings did not match.');
});

run('Groups work in UPDATE and DELETE and bind in order', function (): void {
    $update = builder(new MysqlGrammar())
        ->where(fn(QueryBuilder $q) => $q->where('a', '=', 1)->orWhere('b', '=', 2))
        ->update(['x' => 9])
        ->compileUpdate();

    assertSameValue('UPDATE `users` SET `x` = ? WHERE (`a` = ? OR `b` = ?)', $update['sql'], 'Grouped update SQL did not match.');
    assertSameValue([9, 1, 2], $update['params'], 'Grouped update bindings did not match.');
});

run('Malformed where conditions are rejected', function (): void {
    expectInvalid(fn() => builder(new MysqlGrammar())->where([['id', '=']]), 'Short condition array accepted.');
    expectInvalid(fn() => builder(new MysqlGrammar())->orWhere('id', 'DROP', 1), 'Bad operator accepted in orWhere.');
    expectInvalid(fn() => builder(new MysqlGrammar())->where(fn(QueryBuilder $q) => $q->where('id', 'x', 1)), 'Bad operator accepted in group.');
});

run('Bulk insert compiles one multi-row statement', function (): void {
    $compiled = builder(new MysqlGrammar())
        ->insert([
            ['email' => 'a@x.com', 'name' => 'A'],
            ['name' => 'B', 'email' => 'b@x.com'],
        ])
        ->compileInsert();

    assertSameValue('INSERT INTO `users` (`email`, `name`) VALUES (?, ?), (?, ?)', $compiled['sql'], 'Bulk insert SQL did not match.');
    assertSameValue(['a@x.com', 'A', 'b@x.com', 'B'], $compiled['params'], 'Bulk insert params follow the first row column order.');

    $single = builder(new PostgresGrammar())->insert(['email' => 'a@x.com'])->compileInsert();
    assertSameValue('INSERT INTO "users" ("email") VALUES (?)', $single['sql'], 'Single-row insert SQL did not match.');
});

run('Bulk insert validates its rows', function (): void {
    expectInvalid(fn() => builder(new MysqlGrammar())->insert([]), 'Empty insert accepted.');
    expectInvalid(fn() => builder(new MysqlGrammar())->insert([['a' => 1], ['b' => 2]]), 'Mismatched rows accepted.');
    expectInvalid(fn() => builder(new MysqlGrammar())->insert([['a' => 1], ['a' => 1, 'b' => 2]]), 'Extra column accepted.');

    $threw = false;
    try {
        builder(new MysqlGrammar())->compileInsert();
    } catch (RuntimeException) {
        $threw = true;
    }
    assertSameValue(true, $threw, 'compileInsert() without data should throw.');
});

run('insertGetId returns an int id and rejects multi-row inserts', function (): void {
    $connection = new NullConnection();
    $connection->lastId = '42';
    $id = (new QueryBuilder('users', $connection, new MysqlGrammar()))->insert(['name' => 'A'])->insertGetId();
    assertSameValue(42, $id, 'Numeric id should be returned as int.');

    $connection->lastId = 'abc-uuid';
    $id = (new QueryBuilder('users', $connection, new MysqlGrammar()))->insert(['name' => 'A'])->insertGetId();
    assertSameValue('abc-uuid', $id, 'Non-numeric id should stay a string.');

    $threw = false;
    try {
        (new QueryBuilder('users', $connection, new MysqlGrammar()))->insert([['name' => 'A'], ['name' => 'B']])->insertGetId();
    } catch (LogicException) {
        $threw = true;
    }
    assertSameValue(true, $threw, 'insertGetId() with several rows should throw.');
});

run('Limit and offset compile through the grammar', function (): void {
    foreach ([new MysqlGrammar(), new PostgresGrammar()] as $grammar) {
        assertSameValue(' LIMIT 10', $grammar->compileLimitOffset(10, null), 'Limit without offset.');
        assertSameValue(' LIMIT 10 OFFSET 20', $grammar->compileLimitOffset(10, 20), 'Limit with offset.');
    }

    $compiled = builder(new MysqlGrammar())->limit(5, 15)->compileSelect();
    assertSameValue('SELECT * FROM `users` LIMIT 5 OFFSET 15', $compiled['sql'], 'Select limit/offset SQL did not match.');
});

run('Logging connection reports sql, params and duration, even on failure', function (): void {
    $log = [];
    $inner = new NullConnection();
    $connection = new ZQuery\Connection\LoggingConnection($inner, function (string $sql, array $params, float $ms) use (&$log): void {
        $log[] = [$sql, $params, $ms];
    });

    (new QueryBuilder('users', $connection, new MysqlGrammar()))->where('id', '=', 1)->first();

    assertSameValue('SELECT * FROM `users` WHERE `id` = ? LIMIT 1', $log[0][0], 'Logged SQL did not match.');
    assertSameValue([1], $log[0][1], 'Logged params did not match.');
    assertSameValue(true, $log[0][2] >= 0.0, 'Duration should be a non-negative float.');

    // log: true feeds QueryLogger
    ZQuery\Utils\QueryLogger::clear();
    $zq = new ZQuery\ZQuery(new NullConnection(), log: true);
    $zq->statement('SELECT 1');
    $entries = ZQuery\Utils\QueryLogger::all();
    assertSameValue('SELECT 1', $entries[0]['query'], 'log => true should feed QueryLogger.');
    assertSameValue(true, $entries[0]['duration_ms'] >= 0.0, 'QueryLogger should record a duration.');

    // A failing statement is still logged
    ZQuery\Utils\QueryLogger::clear();
    $failing = new ZQuery\ZQuery(failingConnection(), log: true);
    $threw = false;
    try {
        $failing->statement('SELECT * FROM table_that_does_not_exist');
    } catch (ZQuery\Exceptions\QueryException) {
        $threw = true;
    }
    assertSameValue(true, $threw, 'The failing connection should throw.');
    assertSameValue(1, count(ZQuery\Utils\QueryLogger::all()), 'Failed statement should still be logged.');
    ZQuery\Utils\QueryLogger::clear();

    $rejected = false;
    try {
        new ZQuery\ZQuery(new NullConnection(), log: 'not-callable');
    } catch (TypeError) {
        $rejected = true;
    }
    assertSameValue(true, $rejected, 'A non-callable log option should be rejected.');
});

run('Upsert compiles for MySQL and PostgreSQL', function (): void {
    $rows = [['sku' => 'A1', 'name' => 'Apple', 'price' => 2], ['sku' => 'B2', 'name' => 'Berry', 'price' => 3]];

    $mysql = builder(new MysqlGrammar())->upsert($rows, 'sku')->compileUpsert();
    assertSameValue(
        'INSERT INTO `users` (`sku`, `name`, `price`) VALUES (?, ?, ?), (?, ?, ?) ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `price` = VALUES(`price`)',
        $mysql['sql'],
        'MySQL upsert SQL did not match.'
    );
    assertSameValue(['A1', 'Apple', 2, 'B2', 'Berry', 3], $mysql['params'], 'MySQL upsert params did not match.');

    $pg = builder(new PostgresGrammar())->upsert($rows, ['sku'], ['price'])->compileUpsert();
    assertSameValue(
        'INSERT INTO "users" ("sku", "name", "price") VALUES (?, ?, ?), (?, ?, ?) ON CONFLICT ("sku") DO UPDATE SET "price" = EXCLUDED."price"',
        $pg['sql'],
        'PostgreSQL upsert SQL did not match.'
    );
});

run('Upsert supports bound and raw update values in SQL order', function (): void {
    $compiled = builder(new MysqlGrammar())
        ->upsert(['sku' => 'A1', 'hits' => 1], 'sku', ['hits' => new RawExpression('`hits` + 1'), 'seen_at' => '2026-10-01'])
        ->compileUpsert();

    assertSameValue(
        'INSERT INTO `users` (`sku`, `hits`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `hits` = `hits` + 1, `seen_at` = ?',
        $compiled['sql'],
        'Upsert set-values SQL did not match.'
    );
    assertSameValue(['A1', 1, '2026-10-01'], $compiled['params'], 'VALUES params must come before SET params.');
});

run('Upsert with nothing to update stays valid', function (): void {
    $mysql = builder(new MysqlGrammar())->upsert(['sku' => 'A1'], 'sku')->compileUpsert();
    assertSameValue('INSERT INTO `users` (`sku`) VALUES (?) ON DUPLICATE KEY UPDATE `sku` = `sku`', $mysql['sql'], 'MySQL no-op upsert did not match.');

    $pg = builder(new PostgresGrammar())->upsert(['sku' => 'A1'], 'sku')->compileUpsert();
    assertSameValue('INSERT INTO "users" ("sku") VALUES (?) ON CONFLICT ("sku") DO NOTHING', $pg['sql'], 'PostgreSQL DO NOTHING did not match.');
});

run('Upsert validates its arguments', function (): void {
    expectInvalid(fn() => builder(new MysqlGrammar())->upsert(['a' => 1], []), 'Empty uniqueBy accepted.');
    expectInvalid(fn() => builder(new MysqlGrammar())->upsert(['a' => 1], 'missing'), 'Unknown unique column accepted.');
    expectInvalid(fn() => builder(new MysqlGrammar())->upsert(['a' => 1, 'b' => 2], 'a', ['nope']), 'Unknown update column accepted.');
    expectInvalid(fn() => builder(new MysqlGrammar())->upsert([['a' => 1], ['b' => 2]], 'a'), 'Mismatched rows accepted.');

    $threw = false;
    try {
        builder(new MysqlGrammar())->insert(['a' => 1])->compileUpsert();
    } catch (RuntimeException) {
        $threw = true;
    }
    assertSameValue(true, $threw, 'compileUpsert() without upsert() should throw.');
});

run('transaction() passes the ZQuery instance and keeps $this intact', function (): void {
    $zq = new ZQuery\ZQuery(new NullConnection());

    $service = new class($zq) {
        public string $marker = 'service';

        public function __construct(private ZQuery\ZQuery $zq) {}

        public function run(): array
        {
            return $this->zq->transaction(function (ZQuery\ZQuery $db): array {
                return [$this->marker, $db === $this->zq];
            });
        }

        public function runWithoutParameter(): string
        {
            return $this->zq->transaction(fn() => $this->marker);
        }
    };

    assertSameValue(['service', true], $service->run(), 'Closure $this must stay the service and receive the ZQuery instance.');
    assertSameValue('service', $service->runWithoutParameter(), 'A callback without parameters must keep $this too.');
});

run('Manual transactions commit, roll back and nest with savepoints', function (): void {
    $connection = new NullConnection();
    $zq = new ZQuery\ZQuery($connection);

    $zq->beginTransaction();
    $zq->beginTransaction();
    $zq->rollBack();
    $zq->commit();

    assertSameValue(['begin', 'commit'], $connection->transactionCalls, 'Only the outermost level uses real transaction calls.');
    assertSameValue(['SAVEPOINT zq_sp_1', 'ROLLBACK TO SAVEPOINT zq_sp_1'], $connection->executed, 'Nested levels must use savepoints.');

    $zq->beginTransaction();
    $zq->rollBack();
    assertSameValue(['begin', 'commit', 'begin', 'rollback'], $connection->transactionCalls, 'A new transaction can start after the previous one ended.');
});

run('commit() and rollBack() without an open transaction throw', function (): void {
    $zq = new ZQuery\ZQuery(new NullConnection());

    foreach ([fn() => $zq->commit(), fn() => $zq->rollBack()] as $attempt) {
        $threw = false;
        try {
            $attempt();
        } catch (LogicException) {
            $threw = true;
        }
        assertSameValue(true, $threw, 'Closing a transaction that is not open should throw.');
    }
});

run('transaction() shares nesting with manual calls and unwinds unbalanced callbacks', function (): void {
    $connection = new NullConnection();
    $zq = new ZQuery\ZQuery($connection);

    // transaction() inside a manual transaction becomes a savepoint
    $zq->beginTransaction();
    $zq->transaction(fn() => null);
    $zq->commit();
    assertSameValue(['begin', 'commit'], $connection->transactionCalls, 'Nested transaction() must not start a second real transaction.');
    assertSameValue(['SAVEPOINT zq_sp_1', 'RELEASE SAVEPOINT zq_sp_1'], $connection->executed, 'transaction() inside a manual transaction should use a savepoint.');

    // a callback that opens a transaction and forgets to close it
    $connection = new NullConnection();
    $zq = new ZQuery\ZQuery($connection);
    $threw = false;
    try {
        $zq->transaction(function (ZQuery\ZQuery $db): void {
            $db->beginTransaction();
        });
    } catch (LogicException) {
        $threw = true;
    }
    assertSameValue(true, $threw, 'An unbalanced callback should throw.');
    assertSameValue(['begin', 'rollback'], $connection->transactionCalls, 'The unbalanced transaction must be fully rolled back.');

    // state is clean afterwards: a new transaction starts normally
    $zq->transaction(fn() => null);
    assertSameValue(['begin', 'rollback', 'begin', 'commit'], $connection->transactionCalls, 'Depth must reset after an unbalanced callback.');
});

echo PHP_EOL . 'SQL compilation tests passed.' . PHP_EOL;
