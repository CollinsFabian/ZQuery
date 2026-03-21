<?php

declare(strict_types=1);

namespace ZQuery\Support;

class Cache
{
    private array $store = [];

    public function set(string $key, mixed $value): void
    {
        $this->store[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->store[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return isset($this->store[$key]);
    }

    public function delete(string $key): void
    {
        unset($this->store[$key]);
    }
}
