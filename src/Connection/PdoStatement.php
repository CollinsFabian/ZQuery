<?php

declare(strict_types=1);

namespace ZQuery\Connection;

use PDO;
use PDOStatement as NativeStatement;
use ZQuery\Exceptions\ConnectionException;

/**
 * @internal Returned via StatementInterface; not part of the public API.
 */
final class PdoStatement implements StatementInterface
{
    private NativeStatement $stmt;

    public function __construct(NativeStatement $stmt)
    {
        $this->stmt = $stmt;
    }

    public function bind(array $params): void
    {
        foreach ($params as $key => $value) {
            $this->stmt->bindValue(is_int($key) ? $key + 1 : $key, $value, $this->inferType($value));
        }
    }

    // Explicit types keep null, bool and int values from degrading to strings
    private function inferType(mixed $value): int
    {
        return match (true) {
            $value === null => PDO::PARAM_NULL,
            is_bool($value) => PDO::PARAM_BOOL,
            is_int($value) => PDO::PARAM_INT,
            default => PDO::PARAM_STR,
        };
    }

    public function execute(): void
    {
        if (!$this->stmt->execute()) {
            throw new ConnectionException($this->stmt->errorInfo()[2] ?? 'Statement execution failed.');
        }
    }

    public function fetch(): ?array
    {
        $row = $this->stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function fetchAll(): array
    {
        return $this->stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function rowCount(): int
    {
        return $this->stmt->rowCount();
    }
}
