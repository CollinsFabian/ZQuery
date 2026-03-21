<?php
declare(strict_types=1);

namespace ZQuery\Connection;

use mysqli_stmt as NativeStatement;
use ZQuery\Exceptions\ConnectionException;

class MysqliStatement implements StatementInterface
{
    private NativeStatement $stmt;
    private array $boundParams = [];

    public function __construct(NativeStatement $stmt)
    {
        $this->stmt = $stmt;
    }

    // MySQLi needs type string, so infer types automatically
    public function bind(array $params): void
    {
        if (empty($params)) return;

        $types = '';
        $values = [];

        foreach ($params as $value) {
            $types .= $this->inferType($value);
            $values[] = $value;
        }

        $this->stmt->bind_param($types, ...$values);
    }

    private function inferType(mixed $v): string
    {
        return match (true) {
            is_int($v) => 'i',
            is_float($v) => 'd',
            default => 's',
        };
    }

    public function execute(): void
    {
        if (!$this->stmt->execute()) {
            throw new ConnectionException($this->stmt->error);
        }
    }

    public function fetch(): ?array
    {
        $result = $this->stmt->get_result();
        $row = $result->fetch_assoc();
        return $row ?: null;
    }

    public function fetchAll(): array
    {
        $result = $this->stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function rowCount(): int
    {
        return $this->stmt->affected_rows;
    }
}
