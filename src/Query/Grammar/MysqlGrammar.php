<?php

declare(strict_types=1);

namespace ZQuery\Query\Grammar;

use ZQuery\Query\QueryBuilder;

class MysqlGrammar extends AbstractGrammar
{
    public function compileUpdate(QueryBuilder $builder): array
    {
        $this->requireWhere($builder, 'UPDATE');

        $sql = 'UPDATE ' . $this->escapeIdentifier($builder->getTable())
            . ' SET ' . $this->compileSet($builder)
            . ' WHERE ' . $builder->getWhere()->toSql($this)
            . $this->orderAndLimit($builder);

        return ['sql' => $sql, 'params' => array_merge(array_values($builder->getUpdateData()), $builder->getBindings())];
    }

    public function compileDelete(QueryBuilder $builder): array
    {
        $this->requireWhere($builder, 'DELETE');

        $sql = 'DELETE FROM ' . $this->escapeIdentifier($builder->getTable())
            . ' WHERE ' . $builder->getWhere()->toSql($this)
            . $this->orderAndLimit($builder);

        return ['sql' => $sql, 'params' => $builder->getBindings()];
    }

    protected function quoteSegment(string $segment): string
    {
        return '`' . str_replace('`', '``', $segment) . '`';
    }

    private function orderAndLimit(QueryBuilder $builder): string
    {
        $sql = '';
        if ($builder->hasOrderBy()) $sql .= ' ORDER BY ' . $builder->getOrderBy()->toSql($this);
        if ($builder->hasLimit()) $sql .= $this->limitSql($builder);

        return $sql;
    }
}
