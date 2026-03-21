<?php

declare(strict_types=1);

namespace ZQuery\Query\Grammar;

use ZQuery\Query\QueryBuilder;

interface GrammarInterface
{
    public function compileSelect(QueryBuilder $builder): array; // ['sql' => string, 'params' => array]
    public function compileInsert(QueryBuilder $builder): array;
    public function compileUpdate(QueryBuilder $builder): array;
    public function compileDelete(QueryBuilder $builder): array;
    public function escapeIdentifier(string $identifier): string;
}
