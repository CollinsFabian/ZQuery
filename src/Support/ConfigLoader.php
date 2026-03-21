<?php

declare(strict_types=1);

namespace ZQuery\Support;

class ConfigLoader
{
    private array $config;

    public function __construct(string $filePath)
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("Config file not found: $filePath");
        }
        $this->config = require $filePath;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }
}
