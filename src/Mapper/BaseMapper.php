<?php

declare(strict_types=1);

namespace ZQuery\Mapper;

use ZQuery\Entity\EntityInterface;
use ZQuery\Connection\ConnectionInterface;
use ZQuery\Query\Grammar\GrammarInterface;
use ZQuery\Query\QueryBuilder;

abstract class BaseMapper implements DataMapperInterface
{
    protected ConnectionInterface $connection;
    protected GrammarInterface $grammar;
    protected Hydrator $hydrator;
    protected IdentityMap $identityMap;

    protected string $entityClass;

    public function __construct(ConnectionInterface $connection, GrammarInterface $grammar, IdentityMap $identityMap)
    {
        $this->connection = $connection;
        $this->grammar = $grammar;
        $this->identityMap = $identityMap;
        $this->hydrator = new Hydrator($identityMap);
    }

    public function find(int|string $id): ?EntityInterface
    {
        $entityClass = $this->entityClass;
        $pk = $entityClass::primaryKey();
        $table = $entityClass::tableName();

        $qb = (new QueryBuilder($table, $this->connection, $this->grammar))
            ->where($pk, '=', $id);

        $rows = $qb->get();
        $entities = $this->hydrator->hydrate($rows, $this->entityClass);
        return $entities[0] ?? null;
    }

    public function findAll(): array
    {
        $entityClass = $this->entityClass;
        $table = $entityClass::tableName();
        $qb = new QueryBuilder($table, $this->connection, $this->grammar);

        $rows = $qb->get();
        return $this->hydrator->hydrate($rows, $this->entityClass);
    }

    public function save(EntityInterface $entity): void
    {
        $entityClass = $this->entityClass;
        $table = $entityClass::tableName();
        $pk = $entityClass::primaryKey();
        $data = $entity->toArray();

        $id = $data[$pk] ?? null;
        $qb = new QueryBuilder($table, $this->connection, $this->grammar);

        if ($id === null) {
            $qb->insert($data)->executeInsert();
            $newId = $this->connection->lastInsertId();
            $this->setEntityKey($entity, $pk, $newId);
        } else {
            $update = $data;
            unset($update[$pk]);
            if (!empty($update)) {
                $qb->where($pk, '=', $id)->update($update)->executeUpdate();
            }
        }
    }

    public function delete(EntityInterface $entity): void
    {
        $entityClass = $this->entityClass;
        $table = $entityClass::tableName();
        $pk = $entityClass::primaryKey();
        $data = $entity->toArray();
        $id = $data[$pk] ?? null;
        if ($id === null) return;

        $qb = new QueryBuilder($table, $this->connection, $this->grammar);
        $qb->where($pk, '=', $id)->executeDelete();
    }

    private function setEntityKey(EntityInterface $entity, string $key, int|string $value): void
    {
        if (method_exists($entity, 'fill')) {
            $entity->fill([$key => $value]);
            return;
        }

        $ref = new \ReflectionClass($entity);
        if ($ref->hasProperty($key)) {
            $prop = $ref->getProperty($key);
            if ($prop->isPublic()) {
                $prop->setValue($entity, $value);
            }
        }
    }
}
