<?php

declare(strict_types=1);

namespace ZQuery\Query\Grammar;

use ZQuery\Query\QueryBuilder;

interface GrammarInterface
{
    /** @return array{sql: string, params: array} */
    public function compileSelect(QueryBuilder $builder): array;

    /** @return array{sql: string, params: array} */
    public function compileInsert(QueryBuilder $builder): array;

    /** @return array{sql: string, params: array} */
    public function compileUpdate(QueryBuilder $builder): array;

    /** @return array{sql: string, params: array} */
    public function compileDelete(QueryBuilder $builder): array;

    public function escapeIdentifier(string $identifier): string;

    /**
     * @return string The dialect's limit clause with a leading space, e.g. " LIMIT 10 OFFSET 20".
     */
    public function compileLimitOffset(int $limit, ?int $offset): string;
}
