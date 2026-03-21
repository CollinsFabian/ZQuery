<?php

declare(strict_types=1);

namespace ZQuery\Support;

class Environment
{
    public static function get(string $key, mixed $default = null): mixed
    {
        $val = getenv($key);
        return $val !== false ? $val : $default;
    }

    public static function load(string $path): void
    {
        if (!file_exists($path)) return;
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, '#') || strpos($line, '=') === false) continue;
            [$key, $val] = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($val));
        }
    }
}
