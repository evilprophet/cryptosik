<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Exceptions;

class VaultKeyAlreadyExistsException extends CryptosikException
{
    public static function make(): self
    {
        return new self((string) __('messages.vault.errors.duplicate_vault_key'));
    }
}
