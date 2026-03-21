<?php

declare(strict_types=1);

namespace ZQuery\Entity\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ManyToMany
{
    public string $target;
    public string $pivot;
    public string $foreignKey;
    public string $relatedKey;

    public function __construct(string $target, string $pivot, string $foreignKey, string $relatedKey)
    {
        $this->target = $target;
        $this->pivot = $pivot;
        $this->foreignKey = $foreignKey;
        $this->relatedKey = $relatedKey;
    }
}
