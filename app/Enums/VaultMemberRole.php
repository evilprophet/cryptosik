<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Enums;

enum VaultMemberRole: string
{
    case Owner = 'owner';
    case Member = 'member';
}
