<?php

declare(strict_types=1);

namespace ZQuery\Connection;

use Closure;

/**
 * Decorator that reports every executed statement to a callback.
 *
 * The callback receives the SQL, its parameters and the duration in milliseconds. It runs
 * whether the statement succeeded or threw. Parameters may contain sensitive values.
 */
final class LoggingConnection implements ConnectionInterface
{
    /**
     * @param Closure(string, array, float): void $logger
     */
    public function __construct(
        private readonly ConnectionInterface $inner,
        private readonly Closure $logger
    ) {
    }

    public function prepare(string $sql): StatementInterface
    {
        return $this->inner->prepare($sql);
    }

    public function execute(string $sql, array $params = []): StatementInterface
    {
        $start = hrtime(true);

        try {
            return $this->inner->execute($sql, $params);
        } finally {
            ($this->logger)($sql, $params, (hrtime(true) - $start) / 1_000_000);
        }
    }

    public function beginTransaction(): void
    {
        $this->inner->beginTransaction();
    }

    public function commit(): void
    {
        $this->inner->commit();
    }

    public function rollBack(): void
    {
        $this->inner->rollBack();
    }

    public function lastInsertId(): string|int
    {
        return $this->inner->lastInsertId();
    }

    public function isConnected(): bool
    {
        return $this->inner->isConnected();
    }
}
