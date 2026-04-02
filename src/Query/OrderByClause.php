<?php

declare(strict_types=1);

namespace ZQuery\Query;

use ZQuery\Query\Grammar\GrammarInterface;

class OrderByClause
{
    private array $orders = [];

    public function add(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'])) $direction = 'ASC';
        $this->orders[] = [
            'column' => $column,
            'direction' => $direction,
        ];
        return $this;
    }

    public function toSql(GrammarInterface $grammar): string
    {
        return implode(', ', array_map(
            static fn (array $order): string => sprintf(
                '%s %s',
                $grammar->escapeIdentifier($order['column']),
                $order['direction']
            ),
            $this->orders
        ));
    }
}
