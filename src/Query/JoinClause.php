<?php

declare(strict_types=1);

namespace ZQuery\Query;

use InvalidArgumentException;
use ZQuery\Query\Grammar\GrammarInterface;

/**
 * @internal Created by QueryBuilder::join(); not part of the public API.
 */
final class JoinClause
{
    public const INNER = 'INNER';
    public const LEFT = 'LEFT';
    public const RIGHT = 'RIGHT';
    public const FULL = 'FULL';

    private const TYPES = [self::INNER, self::LEFT, self::RIGHT, self::FULL];

    private readonly string $type;
    private readonly string $operator;

    public function __construct(
        private readonly string $table,
        private readonly string $first,
        string $operator,
        private readonly string $second,
        private readonly GrammarInterface $grammar,
        string $type = self::INNER
    ) {
        $type = strtoupper(trim($type));
        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException("Unsupported join type: {$type}");
        }

        $this->type = $type;
        $this->operator = Operator::normalize($operator);
    }

    public function toSql(): string
    {
        return sprintf(
            '%s JOIN %s ON %s %s %s',
            $this->type,
            $this->grammar->escapeIdentifier($this->table),
            $this->grammar->escapeIdentifier($this->first),
            $this->operator,
            $this->grammar->escapeIdentifier($this->second)
        );
    }
}
