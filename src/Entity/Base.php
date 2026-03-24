<?php

declare(strict_types=1);

namespace ZQuery\Entity;

abstract class Base implements EntityInterface
{
    protected array $attributes = [];

    public function __construct(array $data = [])
    {
        $this->fill($data);
    }

    public static function tableName(): string
    {
        return static::$table ?? strtolower((new \ReflectionClass(static::class))->getShortName());
    }

    public static function primaryKey(): string
    {
        return static::$primaryKey ?? 'id';
    }

    public function fill(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->attributes[$key] = $value;

            // Allow dynamic property fill
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }

    public function toArray(): array
    {
        return $this->attributes;
    }
}
