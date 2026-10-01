<?php

declare(strict_types=1);

namespace ZQuery\Connection;

use mysqli;
use ZQuery\Exceptions\QueryException;

class MysqliConnection implements ConnectionInterface
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function prepare(string $sql): StatementInterface
    {
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) throw new QueryException($sql, [], $this->mysqli->error);

        return new MysqliStatement($stmt);
    }

    public function execute(string $sql, array $params = []): StatementInterface
    {
        try {
            $stmt = $this->prepare($sql);
            $stmt->bind($params);
            $stmt->execute();
            return $stmt;
        } catch (QueryException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new QueryException($sql, $params, $e->getMessage());
        }
    }

    public function beginTransaction(): void
    {
        $this->mysqli->begin_transaction();
    }

    public function commit(): void
    {
        $this->mysqli->commit();
    }

    public function rollBack(): void
    {
        $this->mysqli->rollback();
    }

    public function lastInsertId(): string|int
    {
        return $this->mysqli->insert_id;
    }

    public function isConnected(): bool
    {
        try {
            $this->mysqli->query('SELECT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
