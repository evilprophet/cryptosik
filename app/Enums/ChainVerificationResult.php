<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Enums;

enum ChainVerificationResult: string
{
    case Pending = 'pending';
    case Passed = 'passed';
    case Failed = 'failed';
}
