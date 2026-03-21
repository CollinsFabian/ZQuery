<?php
declare(strict_types=1);

namespace ZQuery\Query;

class RawExpression
{
    private string $expression;

    public function __construct(string $expression)
    {
        $this->expression = $expression;
    }

    public function get(): string
    {
        return $this->expression;
    }
}
