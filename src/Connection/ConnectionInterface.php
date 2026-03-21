<?php
declare(strict_types=1);

namespace ZQuery\Connection;

interface ConnectionInterface
{
    public function prepare(string $sql): StatementInterface;

    public function execute(string $sql, array $params = []): StatementInterface;

    public function beginTransaction(): void;

    public function commit(): void;

    public function rollBack(): void;

    public function lastInsertId(): string|int;

    public function isConnected(): bool;

    public function close(): void;
}
