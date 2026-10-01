<?php

declare(strict_types=1);

namespace ZQuery\Tests\Doubles;

use ZQuery\Connection\ConnectionInterface;
use ZQuery\Connection\StatementInterface;

final class NullConnection implements ConnectionInterface
{
    /** @var string[] SQL of every execute() call, in order. */
    public array $executed = [];

    /** @var string|int Value returned by lastInsertId(). */
    public string|int $lastId = 0;

    public function prepare(string $sql): StatementInterface
    {
        return new NullStatement();
    }

    public function execute(string $sql, array $params = []): StatementInterface
    {
        $this->executed[] = $sql;

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
        return $this->lastId;
    }

    public function isConnected(): bool
    {
        return true;
    }
}
