<?php

declare(strict_types=1);

namespace ZQuery\Entity\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class HasMany
{
    public string $target;
    public string $foreignKey;

    public function __construct(string $target, string $foreignKey)
    {
        $this->target = $target;
        $this->foreignKey = $foreignKey;
    }
}
