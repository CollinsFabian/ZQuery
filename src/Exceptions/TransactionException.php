<?php

namespace ZQuery\Exceptions;

class TransactionException extends ORMException
{
    public function __construct(string $detail)
    {
        $message = "Transaction error: $detail";
        parent::__construct($message);
    }
}
