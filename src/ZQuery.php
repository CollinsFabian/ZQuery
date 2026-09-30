<?php

namespace ZQuery;

use Closure;
use ZQuery\Connection\PdoConnection;
use ZQuery\Connection\MysqliConnection;
use ZQuery\Connection\ConnectionInterface;
use ZQuery\Query\Grammar\GrammarInterface;
use ZQuery\Query\Grammar\MysqlGrammar;
use ZQuery\Query\QueryBuilder;

class ZQuery
{
    protected ConnectionInterface $connection;
    protected GrammarInterface $grammar;
    protected string $prefix = '';
    private int $transactionDepth = 0;

    /**
     * ZQuery
     *
     * @param array $config sets configuration to establish connection,
     *
     * example
     * ```
     * $config = [
     *  "prefix" => "DB_TABLES_PREFIX_" ?? "", // optional table prefix string, and connection params
     *  "engine" => "pdo|mysqli",
     *  "pdo|mysqli" => "", //instance of used class
     *  "grammar" => interface GrammarInterface,
     * ];
     * ```
     */
    public function __construct(array $config)
    {
        $this->prefix = $config['prefix'] ?? '';

        $engine = strtolower((string) ($config['engine'] ?? ''));
        if (!in_array($engine, ['pdo', 'mysqli'], true)) {
            throw new \InvalidArgumentException("ZQuery config 'engine' must be 'pdo' or 'mysqli'.");
        }
        if (!isset($config[$engine])) {
            throw new \InvalidArgumentException("ZQuery config is missing the '{$engine}' connection instance.");
        }

        $this->connection = $engine === 'pdo' ? new PdoConnection($config['pdo']) : new MysqliConnection($config['mysqli']);

        // Set grammar (default: MySQL)
        $this->grammar = $config['grammar'] ?? new MysqlGrammar();
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
