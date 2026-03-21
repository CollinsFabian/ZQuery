<?php

declare(strict_types=1);

namespace ZQuery\Mapper;

use ZQuery\Entity\EntityInterface;
use ReflectionClass;

class Hydrator
{
    private IdentityMap $identityMap;

    public function __construct(IdentityMap $identityMap)
    {
        $this->identityMap = $identityMap;
    }

    public function hydrate(array $rows, string $class): array
    {
        $result = [];

        foreach ($rows as $row) {
            $pkProp = (new $class(null, null))->primaryKey();
            $id = $row[$pkProp] ?? null;

            if ($id === null) continue;

            if ($this->identityMap->has($class, $id)) {
                $entity = $this->identityMap->get($class, $id);
            } else {
                $entity = $this->mapRowToEntity($row, $class);
                $this->identityMap->add($entity);
            }

            $this->hydrateRelations($entity, $row);
            $result[$id] = $entity;
        }

        return array_values($result);
    }

    private function mapRowToEntity(array $row, string $class): EntityInterface
    {
        $ref = new ReflectionClass($class);
        $instance = $ref->newInstanceWithoutConstructor();

        foreach ($ref->getProperties() as $prop) {
            $colAttr = $prop->getAttributes(\ZQuery\Entity\Attributes\Column::class);
            $name = !empty($colAttr) ? $colAttr[0]->newInstance()->name : $prop->getName();
            if (array_key_exists($name, $row)) {
                $prop->setValue($instance, $row[$name]);
            }
        }

        return $instance;
    }

    private function hydrateRelations(EntityInterface $entity, array $row): void
    {
        $ref = new ReflectionClass($entity);

        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $attrs = $prop->getAttributes();

            foreach ($attrs as $attr) {
                $instance = $attr->newInstance();

                // BelongsTo
                if ($attr->getName() === \ZQuery\Entity\Attributes\BelongsTo::class) {
                    $relatedClass = $instance->target;
                    $foreignKey = $instance->foreignKey;

                    if (isset($row[$foreignKey])) {
                        $related = $this->identityMap->get($relatedClass, $row[$foreignKey])
                            ?? $this->mapRowToEntity([$foreignKey => $row[$foreignKey]], $relatedClass);
                        $prop->setValue($entity, $related);
                    }
                }

                // HasMany
                if ($attr->getName() === \ZQuery\Entity\Attributes\HasMany::class) {
                    $relatedClass = $instance->target;
                    $foreignKey = $instance->foreignKey;

                    if (!isset($propValue)) $propValue = [];
                    if (isset($row[$foreignKey])) {
                        $related = $this->identityMap->get($relatedClass, $row[$foreignKey])
                            ?? $this->mapRowToEntity([$foreignKey => $row[$foreignKey]], $relatedClass);
                        $propValue[] = $related;
                        $prop->setValue($entity, $propValue);
                    }
                }

                // ManyToMany - optional: needs pivot logic
                // TBD: implement when you handle pivot tables
            }
        }
    }
}
