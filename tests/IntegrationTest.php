<?php

declare(strict_types=1);

/**
 * Runs the same scenarios against the mysqli and PDO engines on a real MySQL/MariaDB server.
 *
 * Defaults match a local XAMPP install, so `php tests/IntegrationTest.php` works as is.
 * Override with ZQUERY_TEST_HOST, ZQUERY_TEST_USER and ZQUERY_TEST_PASS.
 *
 * Database handling:
 *  - ZQUERY_TEST_DB unset: a throwaway database `zquery_test_<random>` is created and dropped afterwards.
 *  - ZQUERY_TEST_DB set and missing: it is created, then dropped afterwards.
 *  - ZQUERY_TEST_DB set and already existing: it is used, only the `zq_*` tables are dropped afterwards,
 *    and the database itself is never dropped.
 *
 * Skips (exit 0) when the PHP extensions are missing or the server cannot be reached.
 */

require __DIR__ . '/bootstrap.php';

use ZQuery\Exceptions\QueryException;
use ZQuery\Query\RawExpression;
use ZQuery\ZQuery;

$host = getenv('ZQUERY_TEST_HOST') ?: '127.0.0.1';
$user = getenv('ZQUERY_TEST_USER') ?: 'root';
$pass = getenv('ZQUERY_TEST_PASS') ?: '';
$name = getenv('ZQUERY_TEST_DB') ?: 'zquery_test_' . bin2hex(random_bytes(4));

if (!extension_loaded('mysqli') || !extension_loaded('pdo_mysql')) {
    echo 'Skipped: the mysqli and pdo_mysql extensions are required.' . PHP_EOL;
    exit(0);
}

if (preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
    fwrite(STDERR, "ZQUERY_TEST_DB may only contain letters, digits and underscores (got '{$name}')." . PHP_EOL);
    exit(1);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $admin = new mysqli($host, $user, $pass);
} catch (mysqli_sql_exception $e) {
    echo "Skipped: cannot connect to MySQL at {$host} as '{$user}' ({$e->getMessage()})." . PHP_EOL;
    exit(0);
}

$existed = (int) $admin->query("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '{$name}'")->fetch_row()[0] > 0;
$created = false;

if (!$existed) {
    try {
        $admin->query("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4");
        $created = true;
    } catch (mysqli_sql_exception $e) {
        fwrite(STDERR, "Cannot create test database '{$name}': {$e->getMessage()}" . PHP_EOL
            . "Grant CREATE to '{$user}', or create the database yourself and set ZQUERY_TEST_DB." . PHP_EOL);
        exit(1);
    }
}

// Runs on success, failure and uncaught exceptions alike. Only a database created by this run is dropped.
register_shutdown_function(static function () use ($admin, $name, $created): void {
    try {
        if ($created) {
            $admin->query("DROP DATABASE `{$name}`");
            echo "Dropped test database {$name}." . PHP_EOL;
        } else {
            $admin->query("DROP TABLE IF EXISTS `{$name}`.zq_items, `{$name}`.zq_stock");
        }
    } catch (mysqli_sql_exception $e) {
        fwrite(STDERR, "Cleanup failed for '{$name}': {$e->getMessage()}" . PHP_EOL);
    }
});

echo ($created ? "Created test database {$name}." : "Using existing database {$name}.") . PHP_EOL;


function check(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . PHP_EOL . 'Expected: ' . var_export($expected, true) . PHP_EOL . 'Actual: ' . var_export($actual, true)
        );
    }
}

function expectThrows(string $class, callable $fn, string $message): void
{
    try {
        $fn();
    } catch (Throwable $e) {
        check($class, $e::class, $message . ' (' . $e->getMessage() . ')');
        return;
    }
    throw new RuntimeException($message . ' (nothing thrown)');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$engines = [
    'mysqli' => static fn(mixed $log = false): ZQuery => new ZQuery(new mysqli($host, $user, $pass, $name), log: $log),
    'pdo' => static fn(mixed $log = false): ZQuery => new ZQuery(
        new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]),
        log: $log
    ),
];

foreach ($engines as $engine => $factory) {
    $zq = $factory();
    $say = static fn(string $t) => print("[PASS] {$engine}: {$t}" . PHP_EOL);

    $zq->statement('DROP TABLE IF EXISTS zq_items');
    $zq->statement('CREATE TABLE zq_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        price DOUBLE NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        note VARCHAR(50) NULL
    ) ENGINE=InnoDB');

    // insert: bool false, null and float round-trip correctly
    check(1, $zq->table('zq_items')->insert(['name' => 'apple', 'price' => 1.5, 'active' => true, 'note' => null])->executeInsert(), 'insert count');
    $zq->table('zq_items')->insert(['name' => 'pear', 'price' => 2.0, 'active' => false, 'note' => 'ripe'])->executeInsert();
    $zq->table('zq_items')->insert(['name' => 'plum', 'price' => 3.25, 'active' => true, 'note' => null])->executeInsert();
    $pear = $zq->table('zq_items')->where('name', '=', 'pear')->first();
    check('0', (string) $pear['active'], 'false must bind as 0, not an empty string');
    check(true, $zq->table('zq_items')->where('name', '=', 'apple')->first()['note'] === null, 'null must round-trip');
    $say('insert binds bool/null/float correctly');

    // aggregates and helpers
    check(3, $zq->table('zq_items')->count(), 'count all');
    check(2, $zq->table('zq_items')->where('active', '=', true)->count(), 'count filtered');
    check(2, $zq->table('zq_items')->orderBy('id')->limit(1, 5)->whereNull('note')->count(), 'count ignores order/limit');
    check(true, $zq->table('zq_items')->where('name', '=', 'plum')->exists(), 'exists true');
    check(false, $zq->table('zq_items')->where('name', '=', 'nope')->exists(), 'exists false');
    check(['apple', 'pear', 'plum'], $zq->table('zq_items')->orderBy('id')->pluck('name'), 'pluck');
    check('plum', $zq->table('zq_items')->latest('id')->value('name'), 'value');
    $say('count/exists/pluck/value');

    // first() must not leave LIMIT 1 behind
    $qb = $zq->table('zq_items')->where('price', '>', 0);
    $qb->first();
    check(3, count($qb->get()), 'get() after first() returns all rows');
    $say('first() does not mutate the builder');

    // where forms, whereIn, having order
    check(1, count($zq->table('zq_items')->where(['active' => 1, 'price' => 3.25])->get()), 'assoc where');
    check(1, count($zq->table('zq_items')->where([['active', '=', 1], ['price', '<', 3]])->get()), 'list where');
    check(2, count($zq->table('zq_items')->whereIn('name', ['apple', 'plum'])->get()), 'whereIn');
    $rows = $zq->table('zq_items')
        ->select(['active', new RawExpression('COUNT(*) AS n')])
        ->groupBy('active')
        ->having('n', '>', 1)
        ->where('price', '>', 0)
        ->get();
    check(1, count($rows), 'having before where keeps bindings aligned');
    $say('where forms / whereIn / binding order');

    // OR / grouped where on a real database
    check(2, count($zq->table('zq_items')->where('name', '=', 'apple')->orWhere('name', '=', 'plum')->get()), 'orWhere');
    $grouped = $zq->table('zq_items')
        ->where('active', '=', 1)
        ->where(fn($q) => $q->where('name', '=', 'apple')->orWhere('name', '=', 'pear'))
        ->get();
    check(1, count($grouped), 'grouped where respects precedence (pear is inactive)');
    check(2, count($zq->table('zq_items')->where('name', '=', 'zzz')->orWhere(['active' => 1, 'price' => 3.25])->orWhereIn('name', ['apple'])->get()), 'orWhere array + orWhereIn');
    $say('orWhere and grouped conditions');

    // bulk insert and insertGetId
    check(3, $zq->table('zq_items')->insert([
        ['name' => 'bulk1', 'price' => 1.0],
        ['price' => 2.0, 'name' => 'bulk2'],
        ['name' => 'bulk3', 'price' => null],
    ])->executeInsert(), 'bulk insert affected rows');
    check(3, $zq->table('zq_items')->where('name', 'like', 'bulk%')->count(), 'bulk rows present');
    $id = $zq->table('zq_items')->insert(['name' => 'idcheck'])->insertGetId();
    check(true, is_int($id) && $id > 0, 'insertGetId returns a positive int');
    check($id, (int) $zq->table('zq_items')->where('name', '=', 'idcheck')->value('id'), 'insertGetId matches the stored id');
    $zq->table('zq_items')->where('name', 'like', 'bulk%')->executeDelete();
    $zq->table('zq_items')->where('name', '=', 'idcheck')->executeDelete();
    $say('bulk insert and insertGetId');

    // limit/offset through the grammar
    check(['pear'], array_column($zq->table('zq_items')->orderBy('id')->limit(1, 1)->get(['name']), 'name'), 'limit with offset');
    $say('limit and offset');

    // same statement: fetch() then fetchAll()
    $stmt = $zq->statement('SELECT name FROM zq_items ORDER BY id');
    check('apple', $stmt->fetch()['name'], 'fetch first row');
    $rest = $stmt->fetchAll();
    check(true, count($rest) >= 2, 'fetchAll after fetch returns remaining rows');
    $say('fetch/fetchAll on one statement');

    // update/delete require WHERE and report affected rows
    check(1, $zq->table('zq_items')->where('name', '=', 'pear')->update(['price' => 9.5])->executeUpdate(), 'update count');
    expectThrows(RuntimeException::class, fn() => $zq->table('zq_items')->update(['price' => 1])->executeUpdate(), 'update without where');
    expectThrows(RuntimeException::class, fn() => $zq->table('zq_items')->executeDelete(), 'delete without where');
    $say('update/delete guards');

    // errors keep SQL context and are not double-wrapped
    expectThrows(QueryException::class, fn() => $zq->statement('SELECT * FROM zq_missing'), 'bad SQL raises QueryException');
    $say('QueryException on failure');

    // transactions: commit, rollback, nested savepoint
    $zq->transaction(fn(ZQuery $db) => $db->table('zq_items')->insert(['name' => 'kiwi'])->executeInsert());
    check(1, $zq->table('zq_items')->where('name', '=', 'kiwi')->count(), 'committed row present');

    expectThrows(LogicException::class, function () use ($zq): void {
        $zq->transaction(function (ZQuery $db): void {
            $db->table('zq_items')->insert(['name' => 'ghost'])->executeInsert();
            throw new LogicException('abort');
        });
    }, 'transaction rethrows');
    check(0, $zq->table('zq_items')->where('name', '=', 'ghost')->count(), 'rolled-back row absent');

    $zq->transaction(function (ZQuery $db): void {
        $db->table('zq_items')->insert(['name' => 'outer'])->executeInsert();
        try {
            $db->transaction(function (ZQuery $inner): void {
                $inner->table('zq_items')->insert(['name' => 'inner'])->executeInsert();
                throw new LogicException('inner abort');
            });
        } catch (LogicException) {
        }
    });
    check(1, $zq->table('zq_items')->where('name', '=', 'outer')->count(), 'outer survives inner rollback');
    check(0, $zq->table('zq_items')->where('name', '=', 'inner')->count(), 'inner rolled back via savepoint');
    $say('transactions, rollback and nested savepoints');

    // query logging on a real connection
    $logged = [];
    $loggedDb = $factory(function (string $sql, array $params, float $ms) use (&$logged): void {
        $logged[] = [$sql, $params, $ms];
    });
    $loggedDb->table('zq_items')->where('name', '=', 'apple')->first();
    check('SELECT * FROM `zq_items` WHERE `name` = ? LIMIT 1', $logged[0][0], 'logged SQL');
    check(['apple'], $logged[0][1], 'logged params');
    $say('query logging');

    // manual transactions on a real connection
    $zq->beginTransaction();
    $zq->table('zq_items')->insert(['name' => 'manual-commit'])->executeInsert();
    $zq->commit();
    check(1, $zq->table('zq_items')->where('name', '=', 'manual-commit')->count(), 'manual commit persists');

    $zq->beginTransaction();
    $zq->table('zq_items')->insert(['name' => 'manual-rollback'])->executeInsert();
    $zq->rollBack();
    check(0, $zq->table('zq_items')->where('name', '=', 'manual-rollback')->count(), 'manual rollback discards');

    $zq->beginTransaction();
    $zq->table('zq_items')->insert(['name' => 'manual-outer'])->executeInsert();
    $zq->beginTransaction();
    $zq->table('zq_items')->insert(['name' => 'manual-inner'])->executeInsert();
    $zq->rollBack();                                   // inner savepoint only
    $zq->transaction(fn(ZQuery $db) => $db->table('zq_items')->insert(['name' => 'manual-closure'])->executeInsert());
    $zq->commit();
    check(1, $zq->table('zq_items')->where('name', '=', 'manual-outer')->count(), 'outer manual insert survives');
    check(0, $zq->table('zq_items')->where('name', '=', 'manual-inner')->count(), 'inner manual insert rolled back');
    check(1, $zq->table('zq_items')->where('name', '=', 'manual-closure')->count(), 'closure inside a manual transaction commits with it');
    expectThrows(LogicException::class, fn() => $zq->commit(), 'commit without a transaction');
    $say('manual transactions, nesting and mixing with transaction()');
}

$cleanup = $engines['pdo']();
$cleanup->statement('DROP TABLE IF EXISTS zq_items');

echo PHP_EOL . 'Integration tests passed.' . PHP_EOL;
