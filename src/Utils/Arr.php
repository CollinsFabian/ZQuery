<?php

declare(strict_types=1);

namespace ZQuery\Utils;

class Arr
{
    public static function pluck(array $array, string $key): array
    {
        return array_map(fn($item) => $item[$key] ?? null, $array);
    }

    public static function keyBy(array $array, string $key): array
    {
        $result = [];
        foreach ($array as $item) {
            if (isset($item[$key])) {
                $result[$item[$key]] = $item;
            }
        }
        return $result;
    }

    public static function flatten(array $array): array
    {
        $flat = [];
        array_walk_recursive($array, fn($a) => $flat[] = $a);
        return $flat;
    }
}
