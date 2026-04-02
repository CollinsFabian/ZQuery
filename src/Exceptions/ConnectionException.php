<?php

declare(strict_types=1);

namespace ZQuery\Exceptions;

class ConnectionException extends ZQueryException
{
    public function __construct(string $driverMessage)
    {
        parent::__construct("Connection failed: " . $driverMessage);
    }
}
