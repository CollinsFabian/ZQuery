<?php

declare(strict_types=1);

namespace ZQuery;

use Closure;
use ZQuery\Connection\PdoConnection;
use ZQuery\Connection\MysqliConnection;
use ZQuery\Connection\ConnectionInterface;
use ZQuery\Connection\LoggingConnection;
use ZQuery\Query\Grammar\GrammarInterface;
use ZQuery\Query\Grammar\MysqlGrammar;
use ZQuery\Query\QueryBuilder;
use ZQuery\Utils\QueryLogger;

class ZQuery
{
    private readonly ConnectionInterface $connection;
    private readonly GrammarInterface $grammar;
    private readonly string $prefix;
    private int $transactionDepth = 0;

    /**
     * The engine is chosen from the type of $connection:
     *
     *  - `\mysqli`              wrapped in the mysqli connection
     *  - `\PDO`                 wrapped in the PDO connection
     *  - `ConnectionInterface`  used as is (custom or decorated connections)
     *
     * ZQuery never opens or closes connections; create and close your own `mysqli`/`PDO`.
     *
     * @param GrammarInterface|null $grammar SQL dialect. Defaults to MysqlGrammar.
     * @param string $prefix Prepended to every table name passed to table().
     * @param bool|callable(string, array, float): void $log `true` records every statement in
     *        QueryLogger; a callable receives (sql, params, durationMs).
     */
    public function __construct(
        \PDO|\mysqli|ConnectionInterface $connection,
        ?GrammarInterface $grammar = null,
        string $prefix = '',
        bool|callable $log = false
    ) {
        $connection = match (true) {
            $connection instanceof ConnectionInterface => $connection,
            $connection instanceof \PDO => new PdoConnection($connection),
            default => new MysqliConnection($connection),
        };

        if ($log !== false) {
            $connection = new LoggingConnection(
                $connection,
                $log === true ? QueryLogger::log(...) : Closure::fromCallable($log)
            );
        }

        $this->connection = $connection;
        $this->grammar = $grammar ?? new MysqlGrammar();
        $this->prefix = $prefix;
    }

    public function table(string $table): QueryBuilder
    {
        return new QueryBuilder(
            table: $this->prefix . $table,
            connection: $this->connection,
            grammar: $this->grammar
        );
    }

    public function raw(string $expression): Query\RawExpression
    {
        return new Query\RawExpression($expression);
    }

    public function statement(string $sql, array $params = []): Connection\StatementInterface
    {
        return $this->connection->execute($sql, $params);
    }

    /**
     * Start a transaction, or a savepoint when one is already open.
     * Every call needs a matching commit() or rollBack(). Prefer transaction(), which pairs them for you.
     */
    public function beginTransaction(): void
    {
        if ($this->transactionDepth === 0) {
            $this->connection->beginTransaction();
        } else {
            $this->connection->execute("SAVEPOINT zq_sp_{$this->transactionDepth}");
        }

        $this->transactionDepth++;
    }

    /**
     * Commit the transaction, or release the innermost savepoint.
     *
     * @throws \LogicException When no transaction is open.
     */
    public function commit(): void
    {
        $depth = $this->openTransactionDepth('commit');

        if ($depth === 0) {
            $this->connection->commit();
        } else {
            $this->connection->execute("RELEASE SAVEPOINT zq_sp_{$depth}");
        }

        $this->transactionDepth = $depth;
    }

    /**
     * Roll back the transaction, or back to the innermost savepoint.
     *
     * @throws \LogicException When no transaction is open.
     */
    public function rollBack(): void
    {
        $depth = $this->openTransactionDepth('roll back');

        if ($depth === 0) {
            $this->connection->rollBack();
        } else {
            $this->connection->execute("ROLLBACK TO SAVEPOINT zq_sp_{$depth}");
        }

        $this->transactionDepth = $depth;
    }

    /**
     * Run $callback inside a transaction and return its result. The callback receives this
     * instance and is never rebound, so `$this` inside it stays whatever it was where you wrote it.
     * It commits when the callback returns and rolls back, then rethrows, when it throws.
     * Nested calls use savepoints.
     *
     * @param callable(self): mixed $callback
     * @throws \LogicException When the callback leaves its transaction unbalanced.
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        $level = $this->transactionDepth;

        try {
            $result = $callback($this);

            if ($this->transactionDepth !== $level) {
                throw new \LogicException('The transaction callback left its transaction unbalanced.');
            }

            $this->commit();

            return $result;
        } catch (\Throwable $e) {
            // Also unwinds any transaction the callback opened and did not close
            while ($this->transactionDepth >= $level) {
                $this->rollBack();
            }

            throw $e;
        }
    }

    /**
     * @return int The depth the transaction state returns to after the current level ends.
     */
    private function openTransactionDepth(string $action): int
    {
        if ($this->transactionDepth === 0) {
            throw new \LogicException("Cannot {$action}: no transaction is open.");
        }

        return $this->transactionDepth - 1;
    }

    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    public function getGrammar(): GrammarInterface
    {
        return $this->grammar;
    }
}
