<?php

declare(strict_types=1);

namespace ZQuery\UnitOfWork;

enum EntityState: string
{
    case NEW = 'new';
    case MANAGED = 'managed';
    case REMOVED = 'removed';
    case DETACHED = 'detached';
}
