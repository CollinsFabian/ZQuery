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
        $entity = (new $this->entityClass($this->connection, $this->grammar));
        $pk = $entity->primaryKey();

        $qb = (new QueryBuilder($entity::tableName(), $this->connection, $this->grammar))
            ->where($pk, '=', $id);

        $rows = $qb->get();
        $entities = $this->hydrator->hydrate($rows, $this->entityClass);
        return $entities[0] ?? null;
    }

    public function findAll(): array
    {
        $entity = (new $this->entityClass($this->connection, $this->grammar))::tableName();
        $qb = new QueryBuilder($entity::tableName(), $this->connection, $this->grammar);

        $rows = $qb->get();
        return $this->hydrator->hydrate($rows, $this->entityClass);
    }

    public function save(EntityInterface $entity): void
    {
        // TBD: implement INSERT or UPDATE
    }

    public function delete(EntityInterface $entity): void
    {
        // TBD: implement DELETE
    }
}
