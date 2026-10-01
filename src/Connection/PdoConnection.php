<?php

declare(strict_types=1);

namespace ZQuery\Connection;

use PDO;
use ZQuery\Exceptions\QueryException;

class PdoConnection implements ConnectionInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function prepare(string $sql): StatementInterface
    {
        $stmt = $this->pdo->prepare($sql);
        if ($stmt === false) throw new QueryException($sql, [], implode(';', $this->pdo->errorInfo()));

        return new PdoStatement($stmt);
    }

    public function execute(string $sql, array $params = []): StatementInterface
    {
        try {
            $statement = $this->prepare($sql);
            $statement->bind($params);
            $statement->execute();
            return $statement;
        } catch (QueryException $e) {
            throw $e;
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
        $id = $this->pdo->lastInsertId();
        return $id === false ? 0 : $id;
    }

    public function isConnected(): bool
    {
        try {
            return $this->pdo->query('SELECT 1') !== false;
        } catch (\Throwable) {
            return false;
        }
    }
}
