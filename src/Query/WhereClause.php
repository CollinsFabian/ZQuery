<?php

declare(strict_types=1);

namespace ZQuery\Query;

use ZQuery\Query\Grammar\GrammarInterface;

/**
 * @internal Built by QueryBuilder; not part of the public API.
 */
final class WhereClause
{
    public const AND = 'AND';
    public const OR = 'OR';

    /** @var array<int, array{type: string, boolean: string, column?: string, operator?: string, sql?: string, group?: WhereClause}> */
    private array $conditions = [];

    public function add(string $column, string $operator, string $boolean = self::AND): self
    {
        $this->conditions[] = [
            'type' => 'basic',
            'boolean' => $boolean,
            'column' => $column,
            'operator' => Operator::normalize($operator),
        ];
        return $this;
    }

    public function addRaw(string $sql, string $boolean = self::AND): self
    {
        $this->conditions[] = ['type' => 'raw', 'boolean' => $boolean, 'sql' => $sql];
        return $this;
    }

    public function addGroup(WhereClause $group, string $boolean = self::AND): self
    {
        $this->conditions[] = ['type' => 'group', 'boolean' => $boolean, 'group' => $group];
        return $this;
    }

    public function toSql(GrammarInterface $grammar): string
    {
        $sql = '';

        foreach ($this->conditions as $i => $condition) {
            $piece = match ($condition['type']) {
                'raw' => $condition['sql'],
                'group' => '(' . $condition['group']->toSql($grammar) . ')',
                default => sprintf('%s %s ?', $grammar->escapeIdentifier($condition['column']), $condition['operator']),
            };

            // The first condition has nothing to connect to
            $sql .= $i === 0 ? $piece : " {$condition['boolean']} {$piece}";
        }

        return $sql;
    }
}
