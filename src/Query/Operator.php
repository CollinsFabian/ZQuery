<?php

declare(strict_types=1);

namespace ZQuery\Query;

use InvalidArgumentException;

/**
 * Whitelist for comparison operators interpolated into compiled SQL.
 */
final class Operator
{
    private const ALLOWED = [
        '=', '<', '>', '<=', '>=', '<>', '!=', '<=>',
        'LIKE', 'NOT LIKE',
    ];

    public static function normalize(string $operator): string
    {
        $normalized = strtoupper(trim((string) preg_replace('/\s+/', ' ', $operator)));

        if (!in_array($normalized, self::ALLOWED, true)) {
            throw new InvalidArgumentException("Unsupported SQL operator: {$operator}");
        }

        return $normalized;
    }
}
