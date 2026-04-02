<?php

declare(strict_types=1);

namespace ZQuery\Tests\Doubles;

use ZQuery\Connection\StatementInterface;

final class NullStatement implements StatementInterface
{
    public function bind(array $params): void
    {
    }

    public function execute(): void
    {
    }

    public function fetch(): ?array
    {
        return null;
    }

    public function fetchAll(): array
    {
        return [];
    }

    public function rowCount(): int
    {
        return 0;
    }
}
