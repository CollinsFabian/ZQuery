<?php

declare(strict_types=1);

namespace ZQuery\Query\Grammar;

use ZQuery\Query\QueryBuilder;

class PostgresGrammar extends AbstractGrammar
{
    public function compileUpdate(QueryBuilder $builder): array
    {
        $this->requireWhere($builder, 'UPDATE');

        $table = $this->escapeIdentifier($builder->getTable());
        $set = $this->compileSet($builder);
        $values = array_values($builder->getUpdateData());

        if (!$this->needsLimitSubquery($builder)) {
            return [
                'sql' => "UPDATE {$table} SET {$set} WHERE " . $builder->getWhere()->toSql($this),
                'params' => array_merge($values, $builder->getBindings()),
            ];
        }

        // The CTE comes first in the SQL, so its WHERE bindings come before the SET values.
        return [
            'sql' => $this->limitedCte($builder, $table) . " UPDATE {$table} SET {$set} WHERE ctid IN (SELECT ctid FROM __zq_limit__)",
            'params' => array_merge($builder->getBindings(), $values),
        ];
    }

    public function compileDelete(QueryBuilder $builder): array
    {
        $this->requireWhere($builder, 'DELETE');

        $table = $this->escapeIdentifier($builder->getTable());

        if (!$this->needsLimitSubquery($builder)) {
            return [
                'sql' => "DELETE FROM {$table} WHERE " . $builder->getWhere()->toSql($this),
                'params' => $builder->getBindings(),
            ];
        }

        return [
            'sql' => $this->limitedCte($builder, $table) . " DELETE FROM {$table} WHERE ctid IN (SELECT ctid FROM __zq_limit__)",
            'params' => $builder->getBindings(),
        ];
    }

    protected function quoteSegment(string $segment): string
    {
        return '"' . str_replace('"', '""', $segment) . '"';
    }

    // PostgreSQL has no UPDATE/DELETE ... LIMIT, so ORDER BY/LIMIT go through a ctid subquery
    private function needsLimitSubquery(QueryBuilder $builder): bool
    {
        return $builder->hasOrderBy() || $builder->hasLimit();
    }

    private function limitedCte(QueryBuilder $builder, string $table): string
    {
        $sql = "SELECT ctid FROM {$table} WHERE " . $builder->getWhere()->toSql($this);
        if ($builder->hasOrderBy()) $sql .= ' ORDER BY ' . $builder->getOrderBy()->toSql($this);
        if ($builder->hasLimit()) $sql .= $this->limitSql($builder);

        return "WITH __zq_limit__ AS ({$sql})";
    }
}
