<?php

declare(strict_types=1);

namespace ZQuery\Exceptions;

class EntityNotFoundException extends ORMException
{
    public function __construct(string $entityClass, mixed $id)
    {
        $idString = is_array($id)
            ? json_encode($id, JSON_UNESCAPED_SLASHES)
            : (string) $id;

        parent::__construct(
            "Entity '{$entityClass}' with ID '{$idString}' not found."
        );
    }

    /**
     * Static factory for convenience.
     */
    public static function forEntity(string $entityClass, mixed $id): self
    {
        return new self($entityClass, $id);
    }
}
