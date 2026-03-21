<?php

declare(strict_types=1);

namespace ZQuery\Utils;

class Str
{
    public static function camel(string $value): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value))));
    }

    public static function snake(string $value): string
    {
        return strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($value)));
    }

    public static function plural(string $value): string
    {
        // naive pluralization
        if (str_ends_with($value, 'y')) {
            return substr($value, 0, -1) . 'ies';
        }
        return $value . 's';
    }
}
