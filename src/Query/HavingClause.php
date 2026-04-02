<?php

declare(strict_types=1);

namespace ZQuery\Query;

use ZQuery\Query\Grammar\GrammarInterface;

class HavingClause
{
    private array $conditions = [];

    public function add(string $column, string $operator): self
    {
        $this->conditions[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
        ];
        return $this;
    }

    public function addRaw(string $sql): self
    {
        $this->conditions[] = [
            'type' => 'raw',
            'sql' => $sql,
        ];

        return $this;
    }

    public function toSql(GrammarInterface $grammar): string
    {
        return implode(' AND ', array_map(
            static function (array $condition) use ($grammar): string {
                if ($condition['type'] === 'raw') {
                    return $condition['sql'];
                }

                return sprintf(
                    '%s %s ?',
                    $grammar->escapeIdentifier($condition['column']),
                    $condition['operator']
                );
            },
            $this->conditions
        ));
    }
}
