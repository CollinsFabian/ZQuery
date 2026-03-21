<?php

declare(strict_types=1);

namespace ZQuery\Mapper;

use ZQuery\Entity\User;

class UserMapper extends BaseMapper
{
    protected string $entityClass = User::class;

    // Custom queries can be added here
}
