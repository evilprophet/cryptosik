<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Auth;

use Carbon\CarbonImmutable;
use EvilStudio\Cryptosik\Models\AuthLoginCode;
use EvilStudio\Cryptosik\Services\Auth\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OtpServiceTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'member@example.com';

    private const CODE = '123456';

    private const IP = '127.0.0.1';

    protected function setUp(): void
    {
        parent::setUp();

        // The HTTP-level limiter is covered separately; these tests drive the per-code state
        // machine, so it must not trip first.
        config([
            'cryptosik.throttle.otp_verify.max_attempts' => 1000,
            'cryptosik.otp.max_attempts' => 5,
            'cryptosik.otp.lock_minutes' => 15,
        ]);
    }

    public function test_correct_code_is_accepted_and_consumed(): void
    {
        $record = $this->seedCode();

        $this->assertTrue(app(OtpService::class)->verify(self::EMAIL, self::CODE, self::IP));

        $this->assertNotNull($record->fresh()?->consumed_at);
    }

    public function test_a_consumed_code_cannot_be_reused(): void
    {
        $this->seedCode(['consumed_at' => CarbonImmutable::now()->subMinute()]);

        $this->assertFalse(app(OtpService::class)->verify(self::EMAIL, self::CODE, self::IP));
    }

    public function test_a_correct_code_cannot_be_replayed_after_a_successful_login(): void
    {
        $this->seedCode();

        $otpService = app(OtpService::class);

        $this->assertTrue($otpService->verify(self::EMAIL, self::CODE, self::IP));
        $this->assertFalse($otpService->verify(self::EMAIL, self::CODE, self::IP));
    }

    public function test_an_expired_code_is_rejected(): void
    {
        $this->seedCode(['expires_at' => CarbonImmutable::now()->subMinute()]);

        $this->assertFalse(app(OtpService::class)->verify(self::EMAIL, self::CODE, self::IP));
    }

    public function test_a_code_stops_working_once_its_ttl_elapses(): void
    {
        config(['cryptosik.otp.ttl_minutes' => 10]);

        $this->seedCode(['expires_at' => CarbonImmutable::now()->addMinutes(10)]);

        $otpService = app(OtpService::class);

        $this->travel(9)->minutes();
        $this->assertTrue($otpService->verify(self::EMAIL, self::CODE, self::IP));

        $this->seedCode(['expires_at' => CarbonImmutable::now()->addMinutes(10)]);

        $this->travel(11)->minutes();
        $this->assertFalse($otpService->verify(self::EMAIL, self::CODE, self::IP));

        $this->travelBack();
    }

    public function test_a_lockout_expires_on_its_own_after_the_configured_window(): void
    {
        config(['cryptosik.otp.lock_minutes' => 15]);

        $this->seedCode([
            'attempts' => 5,
            'blocked_until' => CarbonImmutable::now()->addMinutes(15),
            'expires_at' => CarbonImmutable::now()->addHours(2),
        ]);

        $otpService = app(OtpService::class);

        $this->travel(14)->minutes();
        $this->assertFalse($otpService->verify(self::EMAIL, self::CODE, self::IP));

        $this->travel(2)->minutes();
        $this->assertTrue($otpService->verify(self::EMAIL, self::CODE, self::IP));

        $this->travelBack();
    }

    public function test_a_blocked_code_refuses_even_the_correct_code(): void
    {
        $record = $this->seedCode([
            'attempts' => 5,
            'blocked_until' => CarbonImmutable::now()->addMinutes(15),
        ]);

        $this->assertFalse(app(OtpService::class)->verify(self::EMAIL, self::CODE, self::IP));

        $fresh = $record->fresh();

        $this->assertNull($fresh?->consumed_at);
        $this->assertSame(5, $fresh?->attempts, 'A blocked record must not keep counting attempts.');
    }

    public function test_the_lock_is_released_once_blocked_until_has_passed(): void
    {
        $this->seedCode([
            'attempts' => 5,
            'blocked_until' => CarbonImmutable::now()->subMinute(),
        ]);

        $this->assertTrue(app(OtpService::class)->verify(self::EMAIL, self::CODE, self::IP));
    }

    public function test_wrong_codes_increment_attempts_and_lock_at_the_configured_maximum(): void
    {
        $record = $this->seedCode();
        $otpService = app(OtpService::class);

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->assertFalse($otpService->verify(self::EMAIL, '000000', self::IP));

            $fresh = $record->fresh();

            $this->assertSame($attempt, $fresh?->attempts);
            $this->assertNull($fresh?->blocked_until, 'The lock must not engage before the maximum.');
        }

        $this->assertFalse($otpService->verify(self::EMAIL, '000000', self::IP));

        $locked = $record->fresh();

        $this->assertSame(5, $locked?->attempts);
        $this->assertNotNull($locked?->blocked_until);
        $this->assertTrue($locked?->blocked_until?->isFuture() ?? false);

        // And the lock holds against the code that would otherwise have worked.
        $this->assertFalse($otpService->verify(self::EMAIL, self::CODE, self::IP));
    }

    public function test_only_the_latest_code_for_an_email_is_checked(): void
    {
        $this->seedCode(['code_hash' => Hash::make('111111')]);
        $this->seedCode(['code_hash' => Hash::make('222222')]);

        $otpService = app(OtpService::class);

        $this->assertFalse($otpService->verify(self::EMAIL, '111111', self::IP));
        $this->assertTrue($otpService->verify(self::EMAIL, '222222', self::IP));
    }

    public function test_a_code_issued_for_another_email_is_not_accepted(): void
    {
        $this->seedCode();

        $this->assertFalse(app(OtpService::class)->verify('someone-else@example.com', self::CODE, self::IP));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function seedCode(array $overrides = []): AuthLoginCode
    {
        return AuthLoginCode::query()->create(array_merge([
            'email' => self::EMAIL,
            'code_hash' => Hash::make(self::CODE),
            'expires_at' => CarbonImmutable::now()->addMinutes(10),
            'blocked_until' => null,
            'consumed_at' => null,
            'attempts' => 0,
            'ip_address' => self::IP,
        ], $overrides));
    }
}
