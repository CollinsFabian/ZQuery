<?php

declare(strict_types=1);

namespace ZQuery\Query\Grammar;

use ZQuery\Query\QueryBuilder;

class MysqlGrammar implements GrammarInterface
{
    public function compileSelect(QueryBuilder $builder): array
    {
        $columns = array_map(function ($col) {
            return $col === '*' ? '*' : $this->escapeIdentifier($col);
        }, $builder->getColumns());

        $sql = 'SELECT ' . implode(', ', $columns)
            . ' FROM ' . $this->escapeIdentifier($builder->getTable());

        foreach ($builder->getJoins() as $join) {
            $sql .= ' ' . $join->toSql($this);
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
            } else $sql .= " LIMIT {$limitSql}";
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

        $sql = 'UPDATE ' . $this->escapeIdentifier($builder->getTable())
            . ' SET ' . implode(', ', $setParts)
            . ' WHERE ' . $builder->getWhere()->toSql();

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

        $params = array_merge(array_values($data), $builder->getBindings());

        return ['sql' => $sql, 'params' => $params];
    }

    public function compileDelete(QueryBuilder $builder): array
    {
        if (!$builder->hasWhere()) throw new \RuntimeException('Unsafe DELETE without WHERE clause.');

        $sql = 'DELETE FROM ' . $this->escapeIdentifier($builder->getTable())
            . ' WHERE ' . $builder->getWhere()->toSql();

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

    public function escapeIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
