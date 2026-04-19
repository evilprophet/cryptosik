<?php

declare(strict_types=1);

namespace Tests\Integration\Console;

use EvilStudio\Cryptosik\Models\AuthLoginCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtpPruneCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_prune_command_deletes_consumed_and_expired_codes(): void
    {
        AuthLoginCode::query()->create([
            'email' => 'consumed@example.com',
            'code_hash' => 'hash-consumed',
            'expires_at' => now()->addMinutes(10),
            'blocked_until' => null,
            'consumed_at' => now()->subMinute(),
            'attempts' => 0,
            'ip_address' => '127.0.0.1',
        ]);

        AuthLoginCode::query()->create([
            'email' => 'expired@example.com',
            'code_hash' => 'hash-expired',
            'expires_at' => now()->subMinute(),
            'blocked_until' => null,
            'consumed_at' => null,
            'attempts' => 0,
            'ip_address' => '127.0.0.1',
        ]);

        AuthLoginCode::query()->create([
            'email' => 'valid@example.com',
            'code_hash' => 'hash-valid',
            'expires_at' => now()->addMinutes(10),
            'blocked_until' => null,
            'consumed_at' => null,
            'attempts' => 0,
            'ip_address' => '127.0.0.1',
        ]);

        $this->artisan('cryptosik:otp-prune')
            ->expectsOutput('Deleted 2 obsolete OTP records.')
            ->assertSuccessful();

        $this->assertDatabaseCount('auth_login_codes', 1);
        $this->assertDatabaseHas('auth_login_codes', ['email' => 'valid@example.com']);
    }

    public function test_prune_command_dry_run_keeps_rows_untouched(): void
    {
        AuthLoginCode::query()->create([
            'email' => 'consumed@example.com',
            'code_hash' => 'hash-consumed',
            'expires_at' => now()->addMinutes(10),
            'blocked_until' => null,
            'consumed_at' => now()->subMinute(),
            'attempts' => 0,
            'ip_address' => '127.0.0.1',
        ]);

        AuthLoginCode::query()->create([
            'email' => 'expired@example.com',
            'code_hash' => 'hash-expired',
            'expires_at' => now()->subMinute(),
            'blocked_until' => null,
            'consumed_at' => null,
            'attempts' => 0,
            'ip_address' => '127.0.0.1',
        ]);

        $this->artisan('cryptosik:otp-prune', ['--dry-run' => true])
            ->expectsOutput('Dry-run: 2 OTP records would be deleted.')
            ->assertSuccessful();

        $this->assertDatabaseCount('auth_login_codes', 2);
    }
}
