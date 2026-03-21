<?php

declare(strict_types=1);

namespace ZQuery\Exceptions;

class ConnectionException extends ORMException
{
    public function __construct(string $driverMessage)
    {
        parent::__construct("ConnectionException: " . $driverMessage);
    }
}
