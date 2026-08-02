<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LoginOtpCodeMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $code,
        public readonly int $ttlMinutes,
    ) {}

    public function build(): self
    {
        return $this
            ->subject((string) __('messages.mail.login_otp.subject'))
            ->view('emails.auth.login-otp')
            ->text('emails.text.auth.login-otp');
    }
}
