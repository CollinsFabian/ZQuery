<?php

declare(strict_types=1);

namespace ZQuery\Connection;

use mysqli;
use ZQuery\Exceptions\ConnectionException;
use ZQuery\Exceptions\QueryException;

class MysqliConnection implements ConnectionInterface
{
    private ?mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    private function handle(): mysqli
    {
        if ($this->mysqli === null) {
            throw new ConnectionException('MySQL connection not established.');
        }

        return $this->mysqli;
    }

    public function prepare(string $sql): StatementInterface
    {
        $mysqli = $this->handle();

        $stmt = $mysqli->prepare($sql);
        if (!$stmt) throw new QueryException($sql, [], $mysqli->error);

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
        $this->handle()->begin_transaction();
    }

    public function commit(): void
    {
        $this->handle()->commit();
    }

    public function rollBack(): void
    {
        $this->handle()->rollback();
    }

    public function lastInsertId(): string|int
    {
        return $this->handle()->insert_id;
    }

    public function isConnected(): bool
    {
        if ($this->mysqli === null) return false;

        try {
            $this->mysqli->query("SELECT 1");
            return true;
        } catch (\mysqli_sql_exception) {
            return false;
        }
    }

    public function close(): void
    {
        if ($this->mysqli === null) return;

        $this->mysqli->close();
        $this->mysqli = null;
    }
}
