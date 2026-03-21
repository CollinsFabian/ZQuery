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
     * @param array $config accepts 'engine' => 'pdo'|'mysqli', 'grammar' => GrammarInterface, 'prefix' => optional table prefix string, and connection params
     */
    public function __construct(array $config)
    {
        $this->prefix = $config['prefix'] ?? '';

        if ($config['engine'] === 'pdo') {
            $this->connection = new PdoConnection($config['pdo']);
        } else {
            $this->connection = new MysqliConnection($config['mysqli']);
        }

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
