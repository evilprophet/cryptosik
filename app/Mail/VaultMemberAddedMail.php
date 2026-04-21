<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VaultMemberAddedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $vaultId,
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject((string) __('messages.mail.member_added.subject'))
            ->view('emails.vault.member-added');
    }
}

