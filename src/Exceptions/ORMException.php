<?php

declare(strict_types=1);

namespace ZQuery\Exceptions;

use Exception;

class ORMException extends Exception
{
    public function __construct(string $message, int $code = 0, ?Exception $previous = null)
    {
        parent::__construct("ORMException: " . $message, $code, $previous);
    }
}
