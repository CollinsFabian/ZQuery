<?php

declare(strict_types=1);

namespace ZQuery\Query;

class LimitClause
{
    private int $limit;
    private ?int $offset;

    public function __construct(int $limit, ?int $offset = null)
    {
        $this->limit = $limit;
        $this->offset = $offset;
    }

    public function toSql(): string
    {
        if ($this->offset !== null) {
            return "{$this->offset}, {$this->limit}";
        }
        return (string)$this->limit;
    }
}
