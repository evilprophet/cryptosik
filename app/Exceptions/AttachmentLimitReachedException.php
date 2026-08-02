<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Exceptions;

class AttachmentLimitReachedException extends CryptosikException
{
    public static function make(): self
    {
        return new self((string) __('messages.vault.errors.attachment_limit_reached'));
    }
}
