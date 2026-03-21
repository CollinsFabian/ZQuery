<?php

declare(strict_types=1);

namespace ZQuery\Support;

class ConfigLoader
{
    public static function get(string $key, mixed $default = null): mixed
    {
        return Environment::get($key, $default);
    }
}
