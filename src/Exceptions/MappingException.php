<?php

namespace ZQuery\Exceptions;

class MappingException extends ORMException
{
    public function __construct(string $entityClass, string $detail)
    {
        $message = "Mapping error in '$entityClass': $detail";
        parent::__construct($message);
    }
}
