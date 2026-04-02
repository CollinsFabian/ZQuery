<?php

declare(strict_types=1);

namespace ZQuery\Exceptions;

class QueryException extends ZQueryException
{
    public function __construct(string $sql, array $params, string $driverMessage)
    {
        parent::__construct("Query failed: $driverMessage. SQL: $sql. Params: " . json_encode($params));
    }
}
