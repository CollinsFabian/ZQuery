<?php
declare(strict_types=1);

namespace ZQuery\Connection;

use mysqli_result;
use mysqli_stmt as NativeStatement;
use ZQuery\Exceptions\ConnectionException;

class MysqliStatement implements StatementInterface
{
    private NativeStatement $stmt;
    private ?mysqli_result $result = null;
    private bool $resultFetched = false;
    private int $affectedRows = 0;

    public function __construct(NativeStatement $stmt)
    {
        $this->stmt = $stmt;
    }

    public function __destruct()
    {
        $this->free();
        $this->stmt->close();
    }

    // MySQLi needs a type string, so infer types automatically
    public function bind(array $params): void
    {
        if (empty($params)) return;

        $types = '';
        $values = [];

        foreach ($params as $value) {
            $types .= $this->inferType($value);
            // false would otherwise bind as an empty string
            $values[] = is_bool($value) ? (int) $value : $value;
        }

        $this->stmt->bind_param($types, ...$values);
    }

    private function inferType(mixed $v): string
    {
        return match (true) {
            is_int($v), is_bool($v) => 'i',
            is_float($v) => 'd',
            default => 's',
        };
    }

    public function execute(): void
    {
        if (!$this->stmt->execute()) {
            throw new ConnectionException($this->stmt->error);
        }

        // Read before the statement is reused or closed; SELECTs report -1
        $this->affectedRows = (int) $this->stmt->affected_rows;
        $this->free();
    }

    public function fetch(): ?array
    {
        $result = $this->result();
        if ($result === null) return null;

        $row = $result->fetch_assoc();
        return $row ?: null;
    }

    public function fetchAll(): array
    {
        $result = $this->result();
        if ($result === null) return [];

        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $this->free();
        return $rows;
    }

    public function rowCount(): int
    {
        return $this->affectedRows;
    }

    /**
     * The result set is buffered once and shared by fetch()/fetchAll(),
     * since calling get_result() a second time on a statement fails.
     */
    private function result(): ?mysqli_result
    {
        if (!$this->resultFetched) {
            $result = $this->stmt->get_result();
            $this->result = $result === false ? null : $result;
            $this->resultFetched = true;
        }

        return $this->result;
    }

    private function free(): void
    {
        $this->result?->free();
        $this->result = null;
        $this->resultFetched = false;
    }
}
