<?php

declare(strict_types=1);

namespace ZQuery\Utils;

class Debugger
{
    private static function dump(mixed $var): void
    {
        echo '<pre style="background:#222;color:#fff;padding:10px;">';
        var_dump($var);
        echo '</pre>';
    }

    public static function dd(mixed $var): void
    {
        self::dump($var);
        exit;
    }
}
