<?php

declare(strict_types=1);

namespace ZQuery\Query\Grammar;

use ZQuery\Query\QueryBuilder;
use ZQuery\Query\RawExpression;

/**
 * Shared compilation for the built-in grammars. A dialect only supplies its
 * identifier quoting and its UPDATE/DELETE strategy.
 */
abstract class AbstractGrammar implements GrammarInterface
{
    /**
     * Quote a single identifier segment (no dots), doubling embedded quote characters.
     */
    abstract protected function quoteSegment(string $segment): string;

    public function compileSelect(QueryBuilder $builder): array
    {
        $columns = array_map(
            fn($column): string => $column instanceof RawExpression ? $column->get() : $this->escapeIdentifier($column),
            $builder->getColumns()
        );

        $sql = 'SELECT ' . implode(', ', $columns)
            . ' FROM ' . $this->escapeIdentifier($builder->getTable());

        foreach ($builder->getJoins() as $join) $sql .= ' ' . $join->toSql();
        if ($builder->hasWhere()) $sql .= ' WHERE ' . $builder->getWhere()->toSql($this);
        if ($builder->hasGroupBy()) $sql .= ' GROUP BY ' . $builder->getGroupBy()->toSql($this);
        if ($builder->hasHaving()) $sql .= ' HAVING ' . $builder->getHaving()->toSql($this);
        if ($builder->hasOrderBy()) $sql .= ' ORDER BY ' . $builder->getOrderBy()->toSql($this);
        if ($builder->hasLimit()) $sql .= $this->limitSql($builder);

        return ['sql' => $sql, 'params' => $builder->getBindings()];
    }

    public function compileInsert(QueryBuilder $builder): array
    {
        $rows = $builder->getInsertRows();
        if ($rows === []) {
            throw new \RuntimeException('INSERT requires data; call insert() first.');
        }

        $columns = array_map([$this, 'escapeIdentifier'], $builder->getInsertColumns());
        $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';

        $sql = 'INSERT INTO ' . $this->escapeIdentifier($builder->getTable())
            . ' (' . implode(', ', $columns) . ') VALUES '
            . implode(', ', array_fill(0, count($rows), $placeholders));

        return ['sql' => $sql, 'params' => array_merge(...$rows)];
    }

    /**
     * @param callable(string): string $copyExpression Turns an escaped column into the dialect's "value being inserted" expression.
     * @return array{0: string[], 1: array} Assignments and their bound params.
     */
    protected function upsertAssignments(QueryBuilder $builder, callable $copyExpression): array
    {
        $upsert = $builder->getUpsert();
        if ($upsert === null) {
            throw new \RuntimeException('UPSERT requires data; call upsert() first.');
        }

        $parts = [];
        $params = [];

        foreach ($upsert['copy'] as $column) {
            $escaped = $this->escapeIdentifier($column);
            $parts[] = "{$escaped} = " . $copyExpression($escaped);
        }

        foreach ($upsert['set'] as $column => $value) {
            $escaped = $this->escapeIdentifier((string) $column);
            if ($value instanceof RawExpression) {
                $parts[] = "{$escaped} = " . $value->get();
            } else {
                $parts[] = "{$escaped} = ?";
                $params[] = $value;
            }
        }

        return [$parts, $params];
    }

    public function escapeIdentifier(string $identifier): string
    {
        $trimmed = trim($identifier);

        if ($trimmed === '*') {
            return '*';
        }

        if (preg_match('/\s+as\s+/i', $trimmed) === 1) {
            [$base, $alias] = preg_split('/\s+as\s+/i', $trimmed, 2);
            return $this->escapeIdentifier($base) . ' AS ' . $this->escapeIdentifier($alias);
        }

        return implode('.', array_map(
            fn(string $segment): string => $segment === '*' ? '*' : $this->quoteSegment(trim($segment)),
            explode('.', $trimmed)
        ));
    }

    public function compileLimitOffset(int $limit, ?int $offset): string
    {
        return " LIMIT {$limit}" . ($offset !== null ? " OFFSET {$offset}" : '');
    }

    protected function limitSql(QueryBuilder $builder): string
    {
        $limit = $builder->getLimit();

        return $this->compileLimitOffset($limit->getLimit(), $limit->getOffset());
    }

    /**
     * @throws \RuntimeException When the builder has no WHERE clause.
     */
    protected function requireWhere(QueryBuilder $builder, string $verb): void
    {
        if (!$builder->hasWhere()) {
            throw new \RuntimeException("Unsafe {$verb} without WHERE clause.");
        }
    }

    /**
     * @return string `col = ?, col2 = ?` for the builder's update data.
     * @throws \RuntimeException When there is nothing to update.
     */
    protected function compileSet(QueryBuilder $builder): string
    {
        $data = $builder->getUpdateData();
        if ($data === []) {
            throw new \RuntimeException('UPDATE requires at least one column.');
        }

        return implode(', ', array_map(
            fn(string $column): string => $this->escapeIdentifier($column) . ' = ?',
            array_keys($data)
        ));
    }
}
