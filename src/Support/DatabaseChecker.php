<?php

declare(strict_types=1);

namespace ZQuery\Support;

class DatabaseChecker
{
    private \PDO|\mysqli $connection;

    public function __construct(\PDO|\mysqli $connection)
    {
        $this->connection = $connection;
    }

    public function ping(): bool
    {
        try {
            $this->connection->query('SELECT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function stats(): array
    {
        $stmt = $this->connection->query("SHOW STATUS LIKE 'Threads_connected'");
        if ($this->connection instanceof \PDO) {
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ?: [];
        } else {
            $row = $stmt->fetch_assoc();
            return $row ?: [];
        }
    }
}
