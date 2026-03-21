<?php

declare(strict_types=1);

namespace ZQuery\Relations;

interface RelationInterface
{
    public function getTargetClass(): string;
    public function getForeignKey(): string;
}
