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

    assertSameValue(['archived', 100], $compiled['params'], 'Postgres update bindings did not match.');
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

echo PHP_EOL . 'SQL compilation tests passed.' . PHP_EOL;
