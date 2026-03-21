<?php

declare(strict_types=1);

namespace ZQuery\Relations;

use ReflectionProperty;

class RelationFactory
{
    public static function fromProperty(ReflectionProperty $prop): ?RelationInterface
    {
        $attrs = $prop->getAttributes();

        foreach ($attrs as $attr) {
            $instance = $attr->newInstance();

            if ($instance instanceof RelationInterface) {
                return $instance;
            }
        }

        return null;
    }
}
