<?php

declare(strict_types=1);

namespace ZQuery\Query;

class OrderByClause
{
    private array $orders = [];

    public function add(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'])) $direction = 'ASC';
        $this->orders[] = "$column $direction";
        return $this;
    }

    public function toSql(): string
    {
        return implode(', ', $this->orders);
    }
}
