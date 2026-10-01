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

    public function transaction(callable $callback): mixed
    {
        $depth = $this->transactionDepth;

        // Outermost call opens a real transaction; nested calls use savepoints.
        if ($depth === 0) {
            $this->connection->beginTransaction();
        } else {
            $this->connection->execute("SAVEPOINT zq_sp_{$depth}");
        }
        $this->transactionDepth++;

        try {
            $result = $this->invokeTransactionCallback($callback);
            $this->transactionDepth--;

            if ($depth === 0) {
                $this->connection->commit();
            } else {
                $this->connection->execute("RELEASE SAVEPOINT zq_sp_{$depth}");
            }

            return $result;
        } catch (\Throwable $e) {
            $this->transactionDepth = $depth;

            if ($depth === 0) {
                $this->connection->rollBack();
            } else {
                $this->connection->execute("ROLLBACK TO SAVEPOINT zq_sp_{$depth}");
            }

            throw $e;
        }
    }

    private function invokeTransactionCallback(callable $callback): mixed
    {
        if ($callback instanceof Closure) {
            $reflection = new \ReflectionFunction($callback);

            if (!$reflection->isStatic()) {
                return $reflection->getNumberOfParameters() > 0
                    ? $callback->call($this, $this)
                    : $callback->call($this);
            }

            return $reflection->getNumberOfParameters() > 0
                ? $callback($this)
                : $callback();
        }

        $reflection = is_array($callback)
            ? new \ReflectionMethod($callback[0], $callback[1])
            : new \ReflectionFunction(Closure::fromCallable($callback));

        return $reflection->getNumberOfParameters() > 0
            ? $callback($this)
            : $callback();
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
