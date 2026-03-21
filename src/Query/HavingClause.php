<?php

declare(strict_types=1);

namespace ZQuery\Query;

class HavingClause
{
    private array $conditions = [];
    private array $bindings = [];

    public function add(string $column, string $operator, mixed $value): self
    {
        $this->conditions[] = "$column $operator ?";
        $this->bindings[] = $value;
        return $this;
    }

    public function toSql(): string
    {
        return implode(' AND ', $this->conditions);
    }

    public function getBindings(): array
    {
        return $this->bindings;
    }
}
