<?php

declare(strict_types=1);

namespace ZQuery\Entity\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Column
{
    public string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }
}
