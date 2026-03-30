<?php

declare(strict_types=1);

namespace ZQuery\Mapper;

use ZQuery\Entity\EntityInterface;

class IdentityMap
{
    private array $map = [];

    public function add(EntityInterface $entity): void
    {
        $class = get_class($entity);
        $pk = $entity::primaryKey();
        $data = $entity->toArray();
        $id = $data[$pk] ?? null;
        if ($id === null) return;

        $this->map[$class][$id] = $entity;
    }

    public function get(string $class, int|string $id): ?EntityInterface
    {
        return $this->map[$class][$id] ?? null;
    }

    public function has(string $class, int|string $id): bool
    {
        return isset($this->map[$class][$id]);
    }
}
