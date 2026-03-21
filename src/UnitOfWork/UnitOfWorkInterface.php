<?php

declare(strict_types=1);

namespace ZQuery\UnitOfWork;

use ZQuery\Entity\EntityInterface;

interface UnitOfWorkInterface
{
    public function registerNew(EntityInterface $entity): void;
    public function registerDirty(EntityInterface $entity): void;
    public function registerRemoved(EntityInterface $entity): void;
    public function commit(): void;
    public function rollback(): void;
}
