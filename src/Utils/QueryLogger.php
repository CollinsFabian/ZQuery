<?php

declare(strict_types=1);

namespace ZQuery\Utils;

/**
 * Process-wide query log. Enable it with the `log => true` ZQuery option, or call log() yourself.
 */
class QueryLogger
{
    private static array $queries = [];

    public static function log(string $query, array $bindings = [], ?float $durationMs = null): void
    {
        self::$queries[] = [
            'query' => $query,
            'bindings' => $bindings,
            'timestamp' => microtime(true),
            'duration_ms' => $durationMs,
        ];
    }

    /**
     * @return array<int, array{query: string, bindings: array, timestamp: float, duration_ms: ?float}>
     */
    public static function all(): array
    {
        return self::$queries;
    }

    public static function clear(): void
    {
        self::$queries = [];
    }

    public static function dump(): void
    {
        foreach (self::$queries as $q) {
            echo '[' . date('H:i:s', (int) $q['timestamp']) . '] ';
            echo $q['query'] . ' | ' . json_encode($q['bindings']);
            if ($q['duration_ms'] !== null) {
                echo sprintf(' (%.2f ms)', $q['duration_ms']);
            }
            echo "\n";
        }
    }
}
