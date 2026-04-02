<?php

declare(strict_types=1);

namespace ZQuery\Query;

use ZQuery\Query\Grammar\GrammarInterface;

class JoinClause
{
    public const INNER = 'INNER';
    public const LEFT = 'LEFT';
    public const RIGHT = 'RIGHT';
    public const FULL = 'FULL';

    public string $table;
    public string $first;
    public string $operator;
    public string $second;
    public string $type;
    private GrammarInterface $grammar;

    public function __construct(string $table, string $first, string $operator, string $second, GrammarInterface $grammar, string $type = self::INNER)
    {
        $this->table = $table;
        $this->first = $first;
        $this->operator = $operator;
        $this->second = $second;
        $this->type = $type;
        $this->grammar = $grammar;
    }

    public function toSql(): string
    {
        return sprintf("%s JOIN %s ON %s %s %s", 
            $this->type, 
            $this->grammar->escapeIdentifier($this->table), 
            $this->grammar->escapeIdentifier($this->first), 
            $this->operator, 
            $this->grammar->escapeIdentifier($this->second)
        );
    }
}
