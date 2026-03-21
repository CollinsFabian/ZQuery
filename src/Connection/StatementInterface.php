<?php
declare(strict_types=1);

namespace ZQuery\Connection;

interface StatementInterface
{
    public function bind(array $params): void;

    public function execute(): void;

    public function fetch(): array|null;

    public function fetchAll(): array;

    public function rowCount(): int;
}