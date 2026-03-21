<?php

declare(strict_types=1);

namespace ZQuery\Query;

class GroupByClause
{
    private array $columns = [];

    public function add(string $column): self
    {
        $this->columns[] = $column;
        return $this;
    }

    public function toSql(): string
    {
        return implode(', ', $this->columns);
    }
}
