<?php

declare(strict_types=1);

namespace ZQuery\Connection;

use \PDOStatement as NativeStatement;
use ZQuery\Exceptions\ConnectionException;

class PdoStatement implements StatementInterface
{
    private NativeStatement $stmt;

    public function __construct(NativeStatement $stmt)
    {
        $this->stmt = $stmt;
    }

    public function bind(array $params): void
    {
        foreach ($params as $key => $value) {
            $this->stmt->bindValue(is_int($key) ? $key + 1 : $key, $value);
        }
    }

    public function execute(): void
    {
        if (!$this->stmt->execute()) {
            throw new ConnectionException($this->stmt->errorInfo()[2]);
        }
    }

    public function fetch(): ?array
    {
        $res = $this->stmt->fetch(\PDO::FETCH_ASSOC);
        return $res === false ? null : $res;
    }

    public function fetchAll(): array
    {
        return $this->stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function rowCount(): int
    {
        return $this->stmt->rowCount();
    }
}
