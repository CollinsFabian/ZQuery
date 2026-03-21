<?php
namespace ZQuery\Exceptions;

class ValidationException extends ORMException
{
    public function __construct(string $entityClass, array $errors)
    {
        $message = "Validation failed for '$entityClass': " . json_encode($errors);
        parent::__construct($message);
    }
}
