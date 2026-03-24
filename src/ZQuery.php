<?php

namespace ZQuery;

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
     *  "grammer" => interface GrammarInterface,
     * ];
     * ```
     */
    public function __construct(array $config)
    {
        $this->prefix = $config['prefix'] ?? '';
        $this->connection = strtolower($config['engine']) === 'pdo' ? new PdoConnection($config['pdo']) : new MysqliConnection($config['mysqli']);

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

    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    public function getGrammar(): GrammarInterface
    {
        return $this->grammar;
    }
}
