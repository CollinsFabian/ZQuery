<?php

declare(strict_types=1);

namespace ZQuery\Exceptions;

class QueryException extends ORMException
{
    public function __construct(string $sql, array $params, string $driverMessage)
    {
        $message = "Database query failed. Please try again later.";
        // Log the full details if logging is available
        // error_log("Query failed: $driverMessage. SQL: $sql. Params: " . json_encode($params));
        parent::__construct($message);
    }
}
