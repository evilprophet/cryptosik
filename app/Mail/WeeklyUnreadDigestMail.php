<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WeeklyUnreadDigestMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param list<array{vault_id:string, unread_count:int, entries:list<array{entry_id:int, sequence_no:int, entry_date:string, finalized_at:string}>}> $vaults
     */
    public function __construct(
        public readonly array $vaults,
        public readonly int $totalUnread,
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject((string) __('messages.mail.weekly_unread.subject'))
            ->view('emails.vault.weekly-unread-digest');
    }
}

