<?php

declare(strict_types=1);

namespace ZQuery\Exceptions;

class QueryException extends ORMException
{
    public function __construct(string $sql, array $params, string $driverMessage)
    {
        // Log the full details if logging is available
        parent::__construct("Query failed: $driverMessage. SQL: $sql. Params: " . json_encode($params));
    }
}
