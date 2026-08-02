<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Exceptions;

class DraftNotFoundException extends CryptosikException
{
    public static function make(): self
    {
        return new self((string) __('messages.vault.errors.draft_not_found'));
    }
}
