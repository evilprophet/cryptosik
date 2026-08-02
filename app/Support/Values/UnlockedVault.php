<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Support\Values;

use EvilStudio\Cryptosik\Models\Vault;

final class UnlockedVault
{
    public function __construct(
        public readonly Vault $vault,
        public readonly string $dataKey,
    ) {}
}
