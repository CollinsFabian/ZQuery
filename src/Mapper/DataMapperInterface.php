<?php

declare(strict_types=1);

namespace ZQuery\Mapper;

use ZQuery\Entity\EntityInterface;

interface DataMapperInterface
{
    public function find(int|string $id): ?EntityInterface;
    public function findAll(): array;
    public function save(EntityInterface $entity): void;
    public function delete(EntityInterface $entity): void;
}
