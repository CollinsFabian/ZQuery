<?php

declare(strict_types=1);

namespace ZQuery\Query;

use ZQuery\Query\Grammar\GrammarInterface;

class GroupByClause
{
    private array $columns = [];

    public function add(string $column): self
    {
        $this->columns[] = $column;
        return $this;
    }

    public function toSql(GrammarInterface $grammar): string
    {
        return implode(', ', array_map(
            static fn (string $column): string => $grammar->escapeIdentifier($column),
            $this->columns
        ));
    }
}
