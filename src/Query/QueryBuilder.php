<?php

declare(strict_types=1);

namespace ZQuery\Query;

use Closure;
use InvalidArgumentException;
use ZQuery\Connection\ConnectionInterface;
use ZQuery\Query\Grammar\GrammarInterface;

class QueryBuilder
{
    private string $table;
    private array $columns = ['*'];
    private array $whereBindings = [];
    private array $havingBindings = [];
    private array $joins = [];
    private ?WhereClause $where = null;
    private ?GroupByClause $groupBy = null;
    private ?HavingClause $having = null;
    private ?OrderByClause $orderBy = null;
    private ?LimitClause $limit = null;
    private array $insertColumns = [];
    private array $insertRows = [];
    /** @var array{conflict: string[], copy: string[], set: array<string, mixed>}|null */
    private ?array $upsert = null;
    private array $updateData = [];

    private ConnectionInterface $connection;
    private GrammarInterface $grammar;

    public function __construct(string $table, ConnectionInterface $connection, GrammarInterface $grammar)
    {
        $this->table = $table;
        $this->connection = $connection;
        $this->grammar = $grammar;
    }

    /**
     * Clones are independent: changing a clone's clauses never affects the original.
     */
    public function __clone()
    {
        if ($this->where !== null) $this->where = clone $this->where;
        if ($this->groupBy !== null) $this->groupBy = clone $this->groupBy;
        if ($this->having !== null) $this->having = clone $this->having;
        if ($this->orderBy !== null) $this->orderBy = clone $this->orderBy;
    }

    // ---------------------------------------------------------------------
    // Fluent API
    // ---------------------------------------------------------------------

    /**
     * Replace the selected columns. Strings are escaped as identifiers;
     * RawExpression instances are emitted verbatim.
     *
     * @param array<int, string|RawExpression> $columns
     */
    public function select(array $columns = ['*']): self
    {
        $this->columns = $columns;
        return $this;
    }

    /**
     * Append columns to the current selection (replaces the default `*`).
     *
     * @param array<int, string|RawExpression> $columns
     */
    public function addSelect(array $columns): self
    {
        if ($this->columns === ['*']) {
            $this->columns = [];
        }

        $this->columns = [...$this->columns, ...$columns];
        return $this;
    }

    /**
     * @param string $type One of JoinClause::INNER, LEFT, RIGHT, FULL
     * @throws InvalidArgumentException On an unsupported operator or join type.
     */
    public function join(string $table, string $first, string $operator, string $second, string $type = JoinClause::INNER): self
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

    /**
     * Add a condition, combined with the previous ones using AND. Accepted forms:
     *
     *  - where('age', '>', 18)
     *  - where('status', null, 'active')                 operator defaults to '='
     *  - where([['id', '>', 5], ['role', '=', 'admin']])  list of [column, operator, value]
     *  - where(['status' => 'active', 'role' => 'admin']) equality on each key
     *  - where(fn (QueryBuilder $q) => $q->where(...)->orWhere(...))  parenthesised group
     *
     * Values are always bound as parameters.
     *
     * @param string|array|Closure $column
     * @throws InvalidArgumentException On an unsupported operator or malformed condition.
     */
    public function where(string|array|Closure $column, ?string $operator = null, mixed $value = null): self
    {
        return $this->addWhere(WhereClause::AND, $column, $operator, $value);
    }

    /**
     * Same forms as where(), but combined with the previous conditions using OR.
     * An array or closure becomes one parenthesised group:
     * `orWhere(['a' => 1, 'b' => 2])` gives `OR (a = ? AND b = ?)`.
     *
     * @param string|array|Closure $column
     */
    public function orWhere(string|array|Closure $column, ?string $operator = null, mixed $value = null): self
    {
        return $this->addWhere(WhereClause::OR, $column, $operator, $value);
    }

    /**
     * @param array<int, mixed> $values Must not be empty.
     * @throws InvalidArgumentException When $values is empty.
     */
    public function whereIn(string $column, array $values): self
    {
        return $this->addWhereIn(WhereClause::AND, $column, $values);
    }

    public function orWhereIn(string $column, array $values): self
    {
        return $this->addWhereIn(WhereClause::OR, $column, $values);
    }

    public function whereNull(string $column): self
    {
        return $this->addRawWhere(WhereClause::AND, $this->grammar->escapeIdentifier($column) . ' IS NULL');
    }

    public function orWhereNull(string $column): self
    {
        return $this->addRawWhere(WhereClause::OR, $this->grammar->escapeIdentifier($column) . ' IS NULL');
    }

    public function whereNotNull(string $column): self
    {
        return $this->addRawWhere(WhereClause::AND, $this->grammar->escapeIdentifier($column) . ' IS NOT NULL');
    }

    public function orWhereNotNull(string $column): self
    {
        return $this->addRawWhere(WhereClause::OR, $this->grammar->escapeIdentifier($column) . ' IS NOT NULL');
    }

    public function groupBy(string $column): self
    {
        $this->groupBy ??= new GroupByClause();
        $this->groupBy->add($column);
        return $this;
    }

    /**
     * @param array<int, string> $columns
     */
    public function groupByMany(array $columns): self
    {
        foreach ($columns as $column) {
            $this->groupBy($column);
        }

        return $this;
    }

    /**
     * @throws InvalidArgumentException On an unsupported operator.
     */
    public function having(string $column, string $operator, mixed $value): self
    {
        $this->having ??= new HavingClause();
        $this->having->add($column, $operator);
        $this->addBinding([$value], 'having');
        return $this;
    }

    /**
     * Any direction other than DESC (case-insensitive) is treated as ASC.
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy ??= new OrderByClause();
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

    /**
     * Replaces any previously set limit.
     */
    public function limit(int $limit, ?int $offset = null): self
    {
        $this->limit = new LimitClause($limit, $offset);
        return $this;
    }

    /**
     * Set the data to insert. Pass one row (`['col' => value]`) or a list of rows
     * (`[['col' => 1], ['col' => 2]]`) for a multi-row INSERT. Every row must have
     * the same columns; their order may differ. Run with executeInsert() or insertGetId().
     *
     * @param array<string, mixed>|array<int, array<string, mixed>> $data
     * @throws InvalidArgumentException On empty data, or rows with different columns.
     */
    public function insert(array $data): self
    {
        $this->upsert = null;

        $rows = array_is_list($data) && is_array($data[0] ?? null) ? $data : [$data];

        $columns = array_keys($rows[0]);
        if ($columns === []) {
            throw new InvalidArgumentException('insert() requires at least one column.');
        }

        $sorted = $columns;
        sort($sorted);

        $values = [];
        foreach ($rows as $i => $row) {
            $rowColumns = is_array($row) ? array_keys($row) : [];
            sort($rowColumns);

            if ($rowColumns !== $sorted) {
                throw new InvalidArgumentException("insert() row {$i} does not have the same columns as row 0.");
            }

            $values[] = array_map(static fn(string|int $column): mixed => $row[$column], $columns);
        }

        $this->insertColumns = $columns;
        $this->insertRows = $values;

        return $this;
    }

    /**
     * Insert rows, or update them when a unique key already exists. Takes the same row data as
     * insert(). Run with executeUpsert().
     *
     * @param string|array<int, string> $uniqueBy Column(s) that identify an existing row.
     * @param array<int|string, mixed>|null $update A list of column names copies the inserted value,
     *        `column => value` sets a bound value, `column => RawExpression` sets raw SQL.
     *        Omitted, every inserted column except $uniqueBy is copied.
     * @throws InvalidArgumentException On empty or mismatched data, or unknown columns.
     */
    public function upsert(array $data, string|array $uniqueBy, ?array $update = null): self
    {
        $this->insert($data);

        $conflict = array_values((array) $uniqueBy);
        if ($conflict === []) {
            throw new InvalidArgumentException('upsert() requires at least one unique column.');
        }
        foreach ($conflict as $column) {
            if (!in_array($column, $this->insertColumns, true)) {
                throw new InvalidArgumentException("upsert() unique column '{$column}' is not in the inserted data.");
            }
        }

        $copy = [];
        $set = [];
        if ($update === null) {
            $copy = array_values(array_diff($this->insertColumns, $conflict));
        } else {
            foreach ($update as $key => $value) {
                if (!is_int($key)) {
                    $set[$key] = $value;
                } elseif (is_string($value) && in_array($value, $this->insertColumns, true)) {
                    $copy[] = $value;
                } else {
                    throw new InvalidArgumentException('upsert() update list entries must be inserted column names.');
                }
            }
        }

        $this->upsert = ['conflict' => $conflict, 'copy' => $copy, 'set' => $set];

        return $this;
    }

    /**
     * Set the columns to change (column => value). Requires a WHERE clause.
     * Run with executeUpdate().
     *
     * @param array<string, mixed> $data
     */
    public function update(array $data): self
    {
        $this->updateData = $data;
        return $this;
    }

    // ---------------------------------------------------------------------
    // Compilation (no database access)
    // ---------------------------------------------------------------------

    public function toSql(): string
    {
        return $this->compileSelect()['sql'];
    }

    /** @return array{sql: string, params: array} */
    public function compileSelect(): array
    {
        return $this->grammar->compileSelect($this);
    }

    /** @return array{sql: string, params: array} */
    public function compileInsert(): array
    {
        return $this->grammar->compileInsert($this);
    }

    /** @return array{sql: string, params: array} */
    public function compileUpsert(): array
    {
        return $this->grammar->compileUpsert($this);
    }

    /** @return array{sql: string, params: array} */
    public function compileUpdate(): array
    {
        return $this->grammar->compileUpdate($this);
    }

    /** @return array{sql: string, params: array} */
    public function compileDelete(): array
    {
        return $this->grammar->compileDelete($this);
    }

    // ---------------------------------------------------------------------
    // Execution
    // ---------------------------------------------------------------------

    /**
     * @return int Affected rows (the number of inserted rows).
     */
    public function executeInsert(): int
    {
        return $this->run($this->compileInsert())->rowCount();
    }

    /**
     * @return int Affected rows (MySQL: 1 per insert, 2 per update, 0 unchanged; PostgreSQL: 1 each).
     */
    public function executeUpsert(): int
    {
        return $this->run($this->compileUpsert())->rowCount();
    }

    /**
     * Insert a single row and return its auto-generated id.
     *
     * Returns an int when the id is numeric, otherwise the driver's string. The id is
     * read straight after the INSERT, so no other query can reset it.
     * On PostgreSQL via PDO this needs a default sequence name; use a raw
     * `INSERT ... RETURNING` through statement() there.
     *
     * @throws \LogicException When insert() was not given exactly one row.
     */
    public function insertGetId(): int|string
    {
        if (count($this->insertRows) !== 1) {
            throw new \LogicException('insertGetId() requires insert() with exactly one row.');
        }

        $this->executeInsert();
        $id = $this->connection->lastInsertId();

        return is_string($id) && ctype_digit($id) && strlen($id) < 19 ? (int) $id : $id;
    }

    /**
     * @return int Affected rows.
     * @throws \RuntimeException When no WHERE clause or no columns were set.
     */
    public function executeUpdate(): int
    {
        return $this->run($this->compileUpdate())->rowCount();
    }

    /**
     * @return int Affected rows.
     * @throws \RuntimeException When no WHERE clause was set.
     */
    public function executeDelete(): int
    {
        return $this->run($this->compileDelete())->rowCount();
    }

    /**
     * Fetch all matching rows. Does not modify the builder.
     *
     * @param array<int, string|RawExpression> $columns Overrides the selection when not ['*'].
     * @return array<int, array<string, mixed>>
     */
    public function get(array $columns = ['*']): array
    {
        $query = clone $this;
        if ($columns !== ['*']) {
            $query->select($columns);
        }

        return $this->run($query->compileSelect())->fetchAll();
    }

    /**
     * Fetch the first matching row, or null. Applies LIMIT 1 when no limit is
     * set. Does not modify the builder.
     *
     * @param array<int, string|RawExpression> $columns Overrides the selection when not ['*'].
     * @return array<string, mixed>|null
     */
    public function first(array $columns = ['*']): ?array
    {
        $query = clone $this;
        if ($columns !== ['*']) {
            $query->select($columns);
        }

        return $query->fetchFirstRow();
    }

    /**
     * Value of a single column from the first matching row, or null.
     */
    public function value(string $column): mixed
    {
        $row = $this->first([$column]);

        if ($row === null) {
            return null;
        }

        return $row[$column] ?? array_values($row)[0] ?? null;
    }

    /**
     * Whether at least one row matches. Runs `SELECT 1 ... LIMIT 1`.
     */
    public function exists(): bool
    {
        $query = clone $this;
        $query->select([new RawExpression('1')]);

        return $query->fetchFirstRow() !== null;
    }

    /**
     * Number of matching rows. ORDER BY and LIMIT are ignored. With GROUP BY
     * this returns the count of the first group, so use get() for grouped counts.
     *
     * @param string $column Column to count; '*' counts rows.
     */
    public function count(string $column = '*'): int
    {
        $query = clone $this;
        $query->orderBy = null;
        $query->limit = null;

        $countColumn = $column === '*' ? '*' : $this->grammar->escapeIdentifier($column);
        $query->select([new RawExpression(sprintf('COUNT(%s) AS aggregate', $countColumn))]);

        $row = $query->fetchFirstRow();

        return (int) ($row['aggregate'] ?? 0);
    }

    /**
     * @return array<int, mixed> Values of one column across all matching rows.
     */
    public function pluck(string $column): array
    {
        return array_map(
            static fn(array $row): mixed => $row[$column] ?? array_values($row)[0] ?? null,
            $this->get([$column])
        );
    }

    // ---------------------------------------------------------------------
    // Grammar-facing accessors (used by GrammarInterface implementations)
    // ---------------------------------------------------------------------

    public function getTable(): string
    {
        return $this->table;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    /** @return JoinClause[] */
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

    /**
     * @return array<int, string> Column names of the pending INSERT.
     */
    public function getInsertColumns(): array
    {
        return $this->insertColumns;
    }

    /**
     * @return array<int, array<int, mixed>> Pending INSERT rows as value lists in getInsertColumns() order.
     */
    public function getInsertRows(): array
    {
        return $this->insertRows;
    }


    /** @return array{conflict: string[], copy: string[], set: array<string, mixed>}|null */
    public function getUpsert(): ?array
    {
        return $this->upsert;
    }

    /**
     * First pending INSERT row as `column => value`.
     *
     * @deprecated Use getInsertColumns() and getInsertRows().
     */
    public function getInsertData(): array
    {
        return $this->insertRows === [] ? [] : array_combine($this->insertColumns, $this->insertRows[0]);
    }

    public function getUpdateData(): array
    {
        return $this->updateData;
    }

    /**
     * Bindings in the order their placeholders appear in compiled SELECT SQL
     * (WHERE first, then HAVING), regardless of fluent call order.
     */
    public function getBindings(): array
    {
        return array_merge($this->whereBindings, $this->havingBindings);
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

    // ---------------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------------

    private function addWhere(string $boolean, string|array|Closure $column, ?string $operator, mixed $value): self
    {
        if ($column instanceof Closure) {
            return $this->addWhereGroup($boolean, $column);
        }

        if (is_array($column)) {
            // An OR-ed array is one group, so its members stay AND-ed together
            if ($boolean === WhereClause::OR) {
                return $this->addWhereGroup($boolean, static fn(self $group) => $group->where($column));
            }

            foreach ($column as $key => $condition) {
                if (!is_array($condition)) {
                    $this->whereClause()->add((string) $key, '=', $boolean);
                    $this->addBinding([$condition]);
                    continue;
                }

                if (count($condition) !== 3) {
                    throw new InvalidArgumentException('Each where() condition array must be [column, operator, value].');
                }

                [$name, $op, $val] = array_values($condition);
                $this->whereClause()->add($name, $op, $boolean);
                $this->addBinding([$val]);
            }

            return $this;
        }

        $this->whereClause()->add($column, $operator ?? '=', $boolean);
        $this->addBinding([$value]);

        return $this;
    }

    private function addWhereGroup(string $boolean, Closure $callback): self
    {
        $scope = new self($this->table, $this->connection, $this->grammar);
        $callback($scope);

        if ($scope->where !== null) {
            $this->whereClause()->addGroup($scope->where, $boolean);
            $this->addBinding($scope->whereBindings);
        }

        return $this;
    }

    private function addWhereIn(string $boolean, string $column, array $values): self
    {
        if ($values === []) {
            throw new InvalidArgumentException('whereIn() requires at least one value.');
        }

        return $this->addRawWhere(
            $boolean,
            sprintf(
                '%s IN (%s)',
                $this->grammar->escapeIdentifier($column),
                implode(', ', array_fill(0, count($values), '?'))
            ),
            array_values($values)
        );
    }

    private function addRawWhere(string $boolean, string $sql, array $bindings = []): self
    {
        $this->whereClause()->addRaw($sql, $boolean);
        if ($bindings !== []) {
            $this->addBinding($bindings);
        }

        return $this;
    }

    private function whereClause(): WhereClause
    {
        return $this->where ??= new WhereClause();
    }

    private function addBinding(array $params, string $section = 'where'): void
    {
        if ($section === 'having') {
            $this->havingBindings = array_merge($this->havingBindings, $params);
            return;
        }

        $this->whereBindings = array_merge($this->whereBindings, $params);
    }

    /**
     * @param array{sql: string, params: array} $compiled
     */
    private function run(array $compiled): \ZQuery\Connection\StatementInterface
    {
        return $this->connection->execute($compiled['sql'], $compiled['params']);
    }

    /**
     * Runs this builder's SELECT with a default LIMIT 1. Mutates $this, so only
     * call it on a clone.
     */
    private function fetchFirstRow(): ?array
    {
        if (!$this->hasLimit()) {
            $this->limit(1);
        }

        return $this->run($this->compileSelect())->fetch();
    }
}
