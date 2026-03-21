<?php

declare(strict_types=1);

namespace ZQuery\Exceptions;

class UnitOfWorkException extends ORMException
{
    public function __construct(string $message)
    {
        parent::__construct("UnitOfWorkException: " . $message);
    }
}
