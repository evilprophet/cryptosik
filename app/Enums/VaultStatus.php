<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Enums;

enum VaultStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
    case SoftDeleted = 'soft_deleted';
}
