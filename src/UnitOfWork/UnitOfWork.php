<?php

declare(strict_types=1);

namespace ZQuery\UnitOfWork;

use ZQuery\Entity\EntityInterface;
use ZQuery\Exceptions\UnitOfWorkException;
use ZQuery\Mapper\DataMapperInterface;
use Exception;

class UnitOfWork implements UnitOfWorkInterface
{
    private array $newEntities = [];
    private array $dirtyEntities = [];
    private array $removedEntities = [];

    private \PDO|\mysqli $connection;
    private $mapperFactory;

    public function __construct(\PDO|\mysqli $connection, ?callable $mapperFactory = null)
    {
        $this->connection = $connection;
        $this->mapperFactory = $mapperFactory;
    }

    public function registerNew(EntityInterface $entity): void
    {
        $this->newEntities[spl_object_hash($entity)] = $entity;
    }

    public function registerDirty(EntityInterface $entity): void
    {
        $this->dirtyEntities[spl_object_hash($entity)] = $entity;
    }

    public function registerRemoved(EntityInterface $entity): void
    {
        $this->removedEntities[spl_object_hash($entity)] = $entity;
    }

    public function commit(): void
    {
        try {
            if ($this->connection instanceof \PDO) {
                $this->connection->beginTransaction();
            } else {
                $this->connection->autocommit(false);
            }

            // Handle NEW entities
            foreach ($this->newEntities as $entity) {
                $mapper = $this->getMapperFor($entity);
                $mapper->save($entity);
            }

            // Handle DIRTY entities
            foreach ($this->dirtyEntities as $entity) {
                $mapper = $this->getMapperFor($entity);
                $mapper->save($entity);
            }

            // Handle REMOVED entities
            foreach ($this->removedEntities as $entity) {
                $mapper = $this->getMapperFor($entity);
                $mapper->delete($entity);
            }

            if ($this->connection instanceof \PDO) {
                $this->connection->commit();
            } else {
                $this->connection->commit();
                $this->connection->autocommit(true);
            }

            // Clear all
            $this->newEntities = [];
            $this->dirtyEntities = [];
            $this->removedEntities = [];
        } catch (Exception $e) {
            $this->rollback();
            throw new UnitOfWorkException($e->getMessage());
        }
    }

    public function rollback(): void
    {
        if ($this->connection instanceof \PDO) {
            $this->connection->rollBack();
        } else {
            $this->connection->rollback();
            $this->connection->autocommit(true);
        }
    }

    private function getMapperFor(EntityInterface $entity): DataMapperInterface
    {
        if ($this->mapperFactory !== null) {
            $mapper = ($this->mapperFactory)($entity);
            if ($mapper instanceof DataMapperInterface) {
                return $mapper;
            }
        }

        throw new UnitOfWorkException('No mapper factory configured for UnitOfWork.');
    }
}
