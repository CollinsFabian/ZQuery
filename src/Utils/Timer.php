<?php

declare(strict_types=1);

namespace ZQuery\Utils;

class Timer
{
    private float $start;

    public function start(): void
    {
        $this->start = microtime(true);
    }

    public function stop(): float
    {
        return microtime(true) - $this->start;
    }
}
