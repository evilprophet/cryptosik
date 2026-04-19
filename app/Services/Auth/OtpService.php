<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Services\Auth;

use Carbon\CarbonImmutable;
use EvilStudio\Cryptosik\Mail\LoginOtpCodeMail;
use EvilStudio\Cryptosik\Models\AuthLoginCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Throwable;

class OtpService
{
    public function issueForEmail(string $email, string $ipAddress): ?string
    {
        $requestLimit = (int) config('cryptosik.otp.request_max_per_window');
        $windowMinutes = (int) config('cryptosik.otp.request_window_minutes');
        $rateLimitKey = sprintf('otp:request:%s:%s', mb_strtolower($email), $ipAddress);

        if (RateLimiter::tooManyAttempts($rateLimitKey, $requestLimit)) {
            throw ValidationException::withMessages([
                'email' => 'Too many code requests. Try again later.',
            ]);
        }

        RateLimiter::hit($rateLimitKey, $windowMinutes * 60);

        $code = $this->buildCode();
        $ttlMinutes = (int) config('cryptosik.otp.ttl_minutes');
        $now = CarbonImmutable::now();

        $record = AuthLoginCode::create([
            'email' => mb_strtolower($email),
            'code_hash' => Hash::make($code),
            'expires_at' => $now->addMinutes($ttlMinutes),
            'blocked_until' => null,
            'consumed_at' => null,
            'attempts' => 0,
            'ip_address' => $ipAddress,
        ]);

        if (!$this->isDevOtpMode()) {
            try {
                Mail::to($email)->send(new LoginOtpCodeMail($code, $ttlMinutes));
            } catch (Throwable $exception) {
                report($exception);
                $record->delete();

                throw ValidationException::withMessages([
                    'email' => __('messages.auth.user_login.errors.delivery_failed'),
                ]);
            }
        }

        return $this->isDevOtpMode() ? $code : null;
    }

    public function verify(string $email, string $inputCode, string $_ipAddress): bool
    {
        $record = AuthLoginCode::query()
            ->where('email', mb_strtolower($email))
            ->latest('id')
            ->first();

        if ($record === null) {
            return false;
        }

        $now = CarbonImmutable::now();

        if ($record->consumed_at !== null || $record->expires_at->isPast()) {
            return false;
        }

        if ($record->blocked_until !== null && $record->blocked_until->isFuture()) {
            return false;
        }

        if (!Hash::check($inputCode, $record->code_hash)) {
            $record->attempts += 1;

            if ($record->attempts >= (int) config('cryptosik.otp.max_attempts')) {
                $record->blocked_until = $now->addMinutes((int) config('cryptosik.otp.lock_minutes'));
            }

            $record->save();

            return false;
        }

        $record->consumed_at = $now;
        $record->save();

        return true;
    }

    private function buildCode(): string
    {
        if ($this->isDevOtpMode()) {
            return (string) config('cryptosik.otp.dev_code');
        }

        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function isDevOtpMode(): bool
    {
        return mb_strtolower((string) config('app.mode', 'prod')) === 'dev';
    }
}
