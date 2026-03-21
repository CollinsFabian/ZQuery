<?php

declare(strict_types=1);

namespace ZQuery\Query\Grammar;

use ZQuery\Query\QueryBuilder;

class PostgresGrammar implements GrammarInterface
{
    public function compileSelect(QueryBuilder $builder): array
    {
        $columns = $builder->getColumns();
        $escapedColumns = [];
        $count = count($columns);
        for ($i = 0; $i < $count; $i++) {
            $col = $columns[$i];
            $escapedColumns[] = $col === '*' ? '*' : $this->escapeIdentifier($col);
        }

        $sql = 'SELECT ' . implode(', ', $escapedColumns)
            . ' FROM ' . $this->escapeIdentifier($builder->getTable());

        foreach ($builder->getJoins() as $join) {
            $sql .= ' ' . $join->toSql();
        }

        if ($builder->hasWhere()) {
            $sql .= ' WHERE ' . $builder->getWhere()->toSql();
        }

        if ($builder->hasGroupBy()) {
            $sql .= ' GROUP BY ' . $builder->getGroupBy()->toSql();
        }

        if ($builder->hasHaving()) {
            $sql .= ' HAVING ' . $builder->getHaving()->toSql();
        }

        if ($builder->hasOrderBy()) {
            $sql .= ' ORDER BY ' . $builder->getOrderBy()->toSql();
        }

        if ($builder->hasLimit()) {
            $limitSql = $builder->getLimit()->toSql();
            $commaPos = strpos($limitSql, ',');
            if ($commaPos !== false) {
                $limit = trim(substr($limitSql, $commaPos + 1));
                $offset = trim(substr($limitSql, 0, $commaPos));
                $sql .= " LIMIT {$limit} OFFSET {$offset}";
            } else {
                $sql .= " LIMIT {$limitSql}";
            }
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

        $data = $builder->getInsertData();
        $setParts = [];
        foreach ($data as $column => $_) {
            $setParts[] = $this->escapeIdentifier($column) . ' = ?';
        }

        $table = $this->escapeIdentifier($builder->getTable());
        $sql = 'UPDATE ' . $table
            . ' SET ' . implode(', ', $setParts)
            . ' WHERE ' . $builder->getWhere()->toSql();

        if ($builder->hasOrderBy() || $builder->hasLimit()) {
            $cteSql = 'SELECT ctid FROM ' . $table . ' WHERE ' . $builder->getWhere()->toSql();
            if ($builder->hasOrderBy()) {
                $cteSql .= ' ORDER BY ' . $builder->getOrderBy()->toSql();
            }
            if ($builder->hasLimit()) {
                $limitSql = $builder->getLimit()->toSql();
                $commaPos = strpos($limitSql, ',');
                if ($commaPos !== false) {
                    $limit = trim(substr($limitSql, $commaPos + 1));
                    $offset = trim(substr($limitSql, 0, $commaPos));
                    $cteSql .= " LIMIT {$limit} OFFSET {$offset}";
                } else {
                    $cteSql .= " LIMIT {$limitSql}";
                }
            }

            $sql = 'WITH __zq_limit__ AS (' . $cteSql . ') '
                . 'UPDATE ' . $table
                . ' SET ' . implode(', ', $setParts)
                . ' WHERE ctid IN (SELECT ctid FROM __zq_limit__)';
        }

        $params = array_merge(array_values($data), $builder->getBindings());

        return ['sql' => $sql, 'params' => $params];
    }

    public function compileDelete(QueryBuilder $builder): array
    {
        if (!$builder->hasWhere()) throw new \RuntimeException('Unsafe DELETE without WHERE clause.');

        $table = $this->escapeIdentifier($builder->getTable());
        $sql = 'DELETE FROM ' . $table
            . ' WHERE ' . $builder->getWhere()->toSql();

        if ($builder->hasOrderBy() || $builder->hasLimit()) {
            $cteSql = 'SELECT ctid FROM ' . $table . ' WHERE ' . $builder->getWhere()->toSql();
            if ($builder->hasOrderBy()) {
                $cteSql .= ' ORDER BY ' . $builder->getOrderBy()->toSql();
            }
            if ($builder->hasLimit()) {
                $limitSql = $builder->getLimit()->toSql();
                $commaPos = strpos($limitSql, ',');
                if ($commaPos !== false) {
                    $limit = trim(substr($limitSql, $commaPos + 1));
                    $offset = trim(substr($limitSql, 0, $commaPos));
                    $cteSql .= " LIMIT {$limit} OFFSET {$offset}";
                } else {
                    $cteSql .= " LIMIT {$limitSql}";
                }
            }

            $sql = 'WITH __zq_limit__ AS (' . $cteSql . ') '
                . 'DELETE FROM ' . $table
                . ' WHERE ctid IN (SELECT ctid FROM __zq_limit__)';
        }

        return ['sql' => $sql, 'params' => $builder->getBindings()];
    }

    public function escapeIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}
