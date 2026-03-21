<?php

declare(strict_types=1);

namespace ZQuery\Entity\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Table
{
    public string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }
}
