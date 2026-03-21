<?php

declare(strict_types=1);

namespace ZQuery\Entity;

interface EntityInterface
{
    public static function tableName(): string;

    public static function primaryKey(): string;

    public function toArray(): array;
}
