<?php

declare(strict_types=1);

namespace ZQuery\Repository;

use ZQuery\Entity\EntityInterface;
use ZQuery\Mapper\DataMapperInterface;
use ZQuery\Exceptions\RepositoryException;

abstract class BaseRepository implements RepositoryInterface
{
    protected DataMapperInterface $mapper;

    public function __construct(DataMapperInterface $mapper)
    {
        $this->mapper = $mapper;
    }

    public function find(int|string $id): ?EntityInterface
    {
        try {
            return $this->mapper->find($id);
        } catch (\Throwable $e) {
            throw new RepositoryException(static::class, $e->getMessage());
        }
    }

    public function findAll(): array
    {
        try {
            return $this->mapper->findAll();
        } catch (\Throwable $e) {
            throw new RepositoryException(static::class, $e->getMessage());
        }
    }

    public function save(EntityInterface $entity): void
    {
        try {
            $this->mapper->save($entity);
        } catch (\Throwable $e) {
            throw new RepositoryException(static::class, $e->getMessage());
        }
    }

    public function delete(EntityInterface $entity): void
    {
        try {
            $this->mapper->delete($entity);
        } catch (\Throwable $e) {
            throw new RepositoryException(static::class, $e->getMessage());
        }
    }
}
