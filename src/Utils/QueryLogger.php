<?php

declare(strict_types=1);

namespace ZQuery\Utils;

class QueryLogger
{
    private static array $queries = [];

    public static function log(string $query, array $bindings = []): void
    {
        self::$queries[] = [
            'query' => $query,
            'bindings' => $bindings,
            'timestamp' => microtime(true),
        ];
    }

    public static function all(): array
    {
        return self::$queries;
    }

    public static function dump(): void
    {
        foreach (self::$queries as $q) {
            echo "[" . date('H:i:s', (int)$q['timestamp']) . "] ";
            echo $q['query'] . ' | ' . json_encode($q['bindings']) . "\n";
        }
    }
}
