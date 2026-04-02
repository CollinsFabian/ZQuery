<?php

declare(strict_types=1);

namespace ZQuery\Query;

use ZQuery\Connection\ConnectionInterface;
use ZQuery\Query\Grammar\GrammarInterface;

class QueryBuilder
{
    private string $table;
    private array $columns = ['*'];
    private array $bindings = [];
    private array $joins = [];
    private ?WhereClause $where = null;
    private ?GroupByClause $groupBy = null;
    private ?HavingClause $having = null;
    private ?OrderByClause $orderBy = null;
    private ?LimitClause $limit = null;
    private array $insertData = [];
    private array $updateData = [];

    private ConnectionInterface $connection;
    private GrammarInterface $grammar;

    public function __construct(string $table, ConnectionInterface $connection, GrammarInterface $grammar)
    {
        $this->table = $table;
        $this->connection = $connection;
        $this->grammar = $grammar;
    }

    public function select(array $columns = ['*']): self
    {
        $this->columns = $columns;
        return $this;
    }

    public function toSql(): string
    {
        return $this->compileSelect()['sql'];
    }

    public function compileSelect(): array
    {
        return $this->grammar->compileSelect($this);
    }

    public function compileInsert(): array
    {
        return $this->grammar->compileInsert($this);
    }

    public function compileUpdate(): array
    {
        return $this->grammar->compileUpdate($this);
    }

    public function compileDelete(): array
    {
        return $this->grammar->compileDelete($this);
    }

    public function addSelect(array $columns): self
    {
        if ($this->columns === ['*']) {
            $this->columns = [];
        }

        $this->columns = [...$this->columns, ...$columns];
        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $this->joins[] = new JoinClause($table, $first, $operator, $second, $this->grammar, $type);
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, JoinClause::LEFT);
    }

    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, JoinClause::RIGHT);
    }

    public function where(string|array $columnOrArray, ?string $operator = null, mixed $value = null): self
    {
        if ($this->where === null) $this->where = new WhereClause();

        // if array of conditions: [['id', '=', 5], ['active', '=', 1]]
        if (is_array($columnOrArray) && isset($columnOrArray[0]) && is_array($columnOrArray[0])) {
            foreach ($columnOrArray as $cond) {
                $this->where->add($cond[0], $cond[1]);
                $this->addBinding([$cond[2]]);
            }
        } else {
            $this->where->add($columnOrArray, $operator);
            $this->addBinding([$value]);
        }

        return $this;
    }

    public function groupBy(string $column): self
    {
        if ($this->groupBy === null) {
            $this->groupBy = new GroupByClause();
        }
        $this->groupBy->add($column);
        return $this;
    }

    public function groupByMany(array $columns): self
    {
        foreach ($columns as $column) {
            $this->groupBy($column);
        }

        return $this;
    }

    public function having(string $column, string $operator, mixed $value): self
    {
        if ($this->having === null) {
            $this->having = new HavingClause();
        }
        $this->having->add($column, $operator);
        $this->addBinding([$value]);
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        if ($this->orderBy === null) {
            $this->orderBy = new OrderByClause();
        }
        $this->orderBy->add($column, $direction);
        return $this;
    }

    public function latest(string $column = 'created_at'): self
    {
        return $this->orderBy($column, 'DESC');
    }

    public function oldest(string $column = 'created_at'): self
    {
        return $this->orderBy($column, 'ASC');
    }

    public function limit(int $limit, ?int $offset = null): self
    {
        $this->limit = new LimitClause($limit, $offset);
        return $this;
    }

    public function insert(array $data): self
    {
        $this->insertData = $data;
        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        if ($values === []) {
            throw new \InvalidArgumentException('whereIn() requires at least one value.');
        }

        if ($this->where === null) {
            $this->where = new WhereClause();
        }

        $this->where->addRaw(sprintf(
            '%s IN (%s)',
            $this->grammar->escapeIdentifier($column),
            implode(', ', array_fill(0, count($values), '?'))
        ));
        $this->addBinding($values);

        return $this;
    }

    public function whereNull(string $column): self
    {
        if ($this->where === null) {
            $this->where = new WhereClause();
        }

        $this->where->addRaw(sprintf('%s IS NULL', $this->grammar->escapeIdentifier($column)));
        return $this;
    }

    public function whereNotNull(string $column): self
    {
        if ($this->where === null) {
            $this->where = new WhereClause();
        }

        $this->where->addRaw(sprintf('%s IS NOT NULL', $this->grammar->escapeIdentifier($column)));
        return $this;
    }

    public function update(array $data): self
    {
        $this->updateData = $data;
        return $this;
    }

    public function executeInsert(): int
    {
        $compiled = $this->compileInsert();
        $stmt = $this->connection->execute($compiled['sql'], $compiled['params']);
        return $stmt->rowCount();
    }

    public function executeUpdate(): int
    {
        $compiled = $this->compileUpdate();
        $stmt = $this->connection->execute($compiled['sql'], $compiled['params']);
        return $stmt->rowCount();
    }

    public function executeDelete(): int
    {
        $compiled = $this->compileDelete();
        $stmt = $this->connection->execute($compiled['sql'], $compiled['params']);
        return $stmt->rowCount();
    }

    public function get(array $columns = ['*']): array
    {
        if ($columns !== ['*'] || $this->columns === ['*']) {
            $this->select($columns);
        }

        $compiled = $this->compileSelect();
        $stmt = $this->connection->execute($compiled['sql'], $compiled['params']);
        return $stmt->fetchAll();
    }

    public function first(array $columns = ['*']): ?array
    {
        if ($columns !== ['*'] || $this->columns === ['*']) {
            $this->select($columns);
        }

        if (!$this->hasLimit()) {
            $this->limit(1);
        }

        $compiled = $this->compileSelect();
        $stmt = $this->connection->execute($compiled['sql'], $compiled['params']);
        return $stmt->fetch();
    }

    public function value(string $column): mixed
    {
        $query = clone $this;
        $row = $query->first([$column]);

        if ($row === null) {
            return null;
        }

        return $row[$column] ?? array_values($row)[0] ?? null;
    }

    public function exists(): bool
    {
        $query = clone $this;
        return $query->first() !== null;
    }

    public function count(string $column = '*'): int
    {
        $query = clone $this;
        $countColumn = $column === '*' ? '*' : $this->grammar->escapeIdentifier($column);
        $value = $query->select([new RawExpression(sprintf('COUNT(%s) AS aggregate', $countColumn))])->value('aggregate');
        return (int) $value;
    }

    public function pluck(string $column): array
    {
        $query = clone $this;
        $rows = $query->get([$column]);

        return array_map(
            static fn (array $row): mixed => $row[$column] ?? array_values($row)[0] ?? null,
            $rows
        );
    }

    // getters
    public function getTable(): string
    {
        return $this->table;
    }
    public function getColumns(): array
    {
        return $this->columns;
    }
    public function getJoins(): array
    {
        return $this->joins;
    }
    public function getWhere(): ?WhereClause
    {
        return $this->where;
    }
    public function getGroupBy(): ?GroupByClause
    {
        return $this->groupBy;
    }
    public function getHaving(): ?HavingClause
    {
        return $this->having;
    }
    public function getOrderBy(): ?OrderByClause
    {
        return $this->orderBy;
    }
    public function getLimit(): ?LimitClause
    {
        return $this->limit;
    }
    public function getInsertData(): array
    {
        return $this->insertData;
    }
    public function getUpdateData(): array
    {
        return $this->updateData;
    }
    public function getBindings(): array
    {
        return $this->bindings;
    }
    public function addBinding(array $params): void
    {
        $this->bindings = array_merge($this->bindings, $params);
    }
    public function hasWhere(): bool
    {
        return $this->where !== null;
    }
    public function hasGroupBy(): bool
    {
        return $this->groupBy !== null;
    }
    public function hasHaving(): bool
    {
        return $this->having !== null;
    }
    public function hasOrderBy(): bool
    {
        return $this->orderBy !== null;
    }
    public function hasLimit(): bool
    {
        return $this->limit !== null;
    }
}
