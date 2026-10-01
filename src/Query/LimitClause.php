<?php

declare(strict_types=1);

namespace ZQuery\Query;

/**
 * @internal Built by QueryBuilder; not part of the public API.
 */
final class LimitClause
{
    public function __construct(
        private readonly int $limit,
        private readonly ?int $offset = null
    ) {
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getOffset(): ?int
    {
        return $this->offset;
    }
}
