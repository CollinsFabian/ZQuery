<?php

declare(strict_types=1);

namespace ZQuery\Connection;

use PDO;
use ZQuery\Exceptions\ConnectionException;
use ZQuery\Exceptions\QueryException;

class PdoConnection implements ConnectionInterface
{
    private ?PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function prepare(string $sql): StatementInterface
    {
        if (!$this->pdo) throw new ConnectionException("PDO connection not established.");

        $stmt = $this->pdo->prepare($sql);
        if (!$stmt) throw new QueryException($sql, [], implode(";", $this->pdo->errorInfo()));
        return new PdoStatement($stmt);
    }

    public function execute(string $sql, array $params = []): StatementInterface
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $statement = new PdoStatement($stmt);
            $statement->bind($params);
            $statement->execute();
            return $statement;
        } catch (\Throwable $e) {
            throw new QueryException($sql, $params, $e->getMessage());
        }
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollBack(): void
    {
        $this->pdo->rollBack();
    }

    public function lastInsertId(): string|int
    {
        return $this->pdo->lastInsertId();
    }

    public function isConnected(): bool
    {
        try {
            $this->pdo->query("SELECT 1");
            return true;
        } catch (\PDOException) {
            return false;
        }
    }

    public function close(): void
    {
        $this->pdo = null;
    }
}
