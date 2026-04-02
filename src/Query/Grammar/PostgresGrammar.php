<?php

declare(strict_types=1);

namespace ZQuery\Query\Grammar;

use ZQuery\Query\QueryBuilder;
use ZQuery\Query\RawExpression;

class PostgresGrammar implements GrammarInterface
{
    public function compileSelect(QueryBuilder $builder): array
    {
        $columns = $builder->getColumns();
        $escapedColumns = [];
        $count = count($columns);
        for ($i = 0; $i < $count; $i++) {
            $col = $columns[$i];
            if ($col instanceof RawExpression) $escapedColumns[] = $col->get();
            else $escapedColumns[] = $col === '*' ? '*' : $this->escapeIdentifier($col);
        }

        $sql = 'SELECT ' . implode(', ', $escapedColumns)
            . ' FROM ' . $this->escapeIdentifier($builder->getTable());

        foreach ($builder->getJoins() as $join) $sql .= ' ' . $join->toSql();
        if ($builder->hasWhere()) $sql .= ' WHERE ' . $builder->getWhere()->toSql($this);
        if ($builder->hasGroupBy()) $sql .= ' GROUP BY ' . $builder->getGroupBy()->toSql($this);
        if ($builder->hasHaving()) $sql .= ' HAVING ' . $builder->getHaving()->toSql($this);
        if ($builder->hasOrderBy()) $sql .= ' ORDER BY ' . $builder->getOrderBy()->toSql($this);
        if ($builder->hasLimit()) {
            $sql .= $this->compileLimitOffset($builder->getLimit()->toSql());
        }

        return ['sql' => $sql, 'params' => $builder->getBindings()];
    }

    public function compileInsert(QueryBuilder $builder): array
    {
        $columns = array_keys($builder->getInsertData());
        $placeholders = array_fill(0, count($columns), '?');

        $escapedColumns = array_map([$this, 'escapeIdentifier'], $columns);

        $sql = 'INSERT INTO ' . $this->escapeIdentifier($builder->getTable()) .
            ' (' . implode(', ', $escapedColumns) . ') VALUES (' . implode(', ', $placeholders) . ')';

        return ['sql' => $sql, 'params' => array_values($builder->getInsertData())];
    }

    public function compileUpdate(QueryBuilder $builder): array
    {
        if (!$builder->hasWhere()) throw new \RuntimeException('Unsafe UPDATE without WHERE clause.');

        $data = $builder->getUpdateData();
        if (empty($data)) throw new \RuntimeException('UPDATE requires at least one column.');
        $setParts = [];
        foreach ($data as $column => $_) {
            $setParts[] = $this->escapeIdentifier($column) . ' = ?';
        }

        $table = $this->escapeIdentifier($builder->getTable());
        $sql = "UPDATE {$table}"
            . " SET " . implode(', ', $setParts)
            . " WHERE " . $builder->getWhere()->toSql($this);

        if ($builder->hasOrderBy() || $builder->hasLimit()) {
            $cteSql = "SELECT ctid FROM {$table} WHERE " . $builder->getWhere()->toSql($this);
            if ($builder->hasOrderBy()) {
                $cteSql .= " ORDER BY " . $builder->getOrderBy()->toSql($this);
            }
            if ($builder->hasLimit()) {
                $cteSql .= $this->compileLimitOffset($builder->getLimit()->toSql());
            }

            $sql = "WITH __zq_limit__ AS ({$cteSql}) "
                . "UPDATE {$table}"
                . " SET " . implode(', ', $setParts)
                . " WHERE ctid IN (SELECT ctid FROM __zq_limit__)";
        }

        $params = array_merge(array_values($data), $builder->getBindings());

        return ['sql' => $sql, 'params' => $params];
    }

    public function compileDelete(QueryBuilder $builder): array
    {
        if (!$builder->hasWhere()) throw new \RuntimeException('Unsafe DELETE without WHERE clause.');

        $table = $this->escapeIdentifier($builder->getTable());
        $sql = 'DELETE FROM ' . $table
            . ' WHERE ' . $builder->getWhere()->toSql($this);

        if ($builder->hasOrderBy() || $builder->hasLimit()) {
            $cteSql = 'SELECT ctid FROM ' . $table . ' WHERE ' . $builder->getWhere()->toSql($this);
            if ($builder->hasOrderBy()) {
                $cteSql .= ' ORDER BY ' . $builder->getOrderBy()->toSql($this);
            }
            if ($builder->hasLimit()) {
                $cteSql .= $this->compileLimitOffset($builder->getLimit()->toSql());
            }

            $sql = 'WITH __zq_limit__ AS (' . $cteSql . ') '
                . 'DELETE FROM ' . $table
                . ' WHERE ctid IN (SELECT ctid FROM __zq_limit__)';
        }

        return ['sql' => $sql, 'params' => $builder->getBindings()];
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
            static fn (string $segment): string => $segment === '*'
                ? '*'
                : '"' . str_replace('"', '""', trim($segment)) . '"',
            explode('.', $trimmed)
        ));
    }

    public function compileLimitOffset(string $limitSql): string
    {
        $commaPos = strpos($limitSql, ',');
        if ($commaPos !== false) {
            $limit = trim(substr($limitSql, $commaPos + 1));
            $offset = trim(substr($limitSql, 0, $commaPos));
            return " LIMIT {$limit} OFFSET {$offset}";
        }

        return " LIMIT {$limitSql}";
    }
}
