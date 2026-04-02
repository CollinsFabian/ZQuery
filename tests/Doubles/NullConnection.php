<?php

declare(strict_types=1);

namespace ZQuery\Tests\Doubles;

use ZQuery\Connection\ConnectionInterface;
use ZQuery\Connection\StatementInterface;

final class NullConnection implements ConnectionInterface
{
    public function prepare(string $sql): StatementInterface
    {
        return new NullStatement();
    }

    public function execute(string $sql, array $params = []): StatementInterface
    {
        return new NullStatement();
    }

    public function beginTransaction(): void
    {
    }

    public function commit(): void
    {
    }

    public function rollBack(): void
    {
    }

    public function lastInsertId(): string|int
    {
        return 0;
    }

    public function isConnected(): bool
    {
        return true;
    }

    public function close(): void
    {
    }
}
