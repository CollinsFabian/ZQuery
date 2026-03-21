<?php

declare(strict_types=1);

namespace ZQuery\Relations;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class BelongsTo implements RelationInterface
{
    public string $target;
    public string $foreignKey;

    public function __construct(string $target, string $foreignKey)
    {
        $this->target = $target;
        $this->foreignKey = $foreignKey;
    }

    public function getTargetClass(): string
    {
        return $this->target;
    }

    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }
}
