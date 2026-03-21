<?php

namespace ZQuery\Exceptions;

class RepositoryException extends ORMException
{
    public function __construct(string $repositoryClass, string $detail)
    {
        $message = "Repository error in '$repositoryClass': $detail";
        parent::__construct($message);
    }
}
