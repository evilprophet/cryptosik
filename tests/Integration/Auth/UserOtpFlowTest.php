<?php

declare(strict_types=1);

namespace Tests\Integration\Auth;

use EvilStudio\Cryptosik\Mail\LoginOtpCodeMail;
use EvilStudio\Cryptosik\Models\AuthLoginCode;
use EvilStudio\Cryptosik\Models\AuditLog;
use EvilStudio\Cryptosik\Models\User;
use EvilStudio\Cryptosik\Support\SessionKeys;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UserOtpFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_login_page_hides_admin_panel_entry_for_guests(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertDontSee('Admin Dashboard');
    }

    public function test_active_user_can_request_login_code(): void
    {
        User::query()->create([
            'email' => 'member@example.com',
            'nickname' => 'member',
            'locale' => 'en',
            'is_active' => true,
        ]);

        $response = $this
            ->from('/login')
            ->post('/login/request-code', ['email' => 'member@example.com'], ['REMOTE_ADDR' => '127.0.0.1']);

        $response->assertRedirect('/login');
        $response->assertSessionHas('status');
        $response->assertSessionHas('auth.pending_email', 'member@example.com');

        $this->assertDatabaseHas('auth_login_codes', [
            'email' => 'member@example.com',
            'ip_address' => '127.0.0.1',
        ]);
    }

    public function test_inactive_user_request_does_not_create_login_code(): void
    {
        User::query()->create([
            'email' => 'inactive@example.com',
            'nickname' => 'inactive',
            'locale' => 'en',
            'is_active' => false,
        ]);

        $response = $this
            ->from('/login')
            ->post('/login/request-code', ['email' => 'inactive@example.com'], ['REMOTE_ADDR' => '127.0.0.1']);

        $response->assertRedirect('/login');
        $response->assertSessionHas('status');
        $response->assertSessionHas('auth.pending_email', 'inactive@example.com');

        $this->assertDatabaseCount('auth_login_codes', 0);
    }
    public function test_production_mode_sends_login_code_email(): void
    {
        config([
            'app.mode' => 'prod',
        ]);

        Mail::fake();

        User::query()->create([
            'email' => 'member@example.com',
            'nickname' => 'member',
            'locale' => 'en',
            'is_active' => true,
        ]);

        $response = $this
            ->from('/login')
            ->post('/login/request-code', ['email' => 'member@example.com'], ['REMOTE_ADDR' => '127.0.0.1']);

        $response->assertRedirect('/login');
        $response->assertSessionHas('status');
        $response->assertSessionHas('auth.pending_email', 'member@example.com');
        $response->assertSessionMissing('dev_code');

        Mail::assertSent(LoginOtpCodeMail::class, function (LoginOtpCodeMail $mail): bool {
            return $mail->hasTo('member@example.com');
        });

        $this->assertDatabaseHas('auth_login_codes', [
            'email' => 'member@example.com',
            'ip_address' => '127.0.0.1',
        ]);
    }

    public function test_login_page_switches_to_otp_step_after_requesting_code(): void
    {
        User::query()->create([
            'email' => 'member@example.com',
            'nickname' => 'member',
            'locale' => 'en',
            'is_active' => true,
        ]);

        $this->post('/login/request-code', ['email' => 'member@example.com'], ['REMOTE_ADDR' => '127.0.0.1']);

        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee((string) __('messages.auth.user_login.step2_title'));
        $response->assertDontSee((string) __('messages.auth.user_login.step1_title'));
    }

    public function test_user_can_verify_code_and_start_authenticated_session_even_if_ip_changes(): void
    {
        User::query()->create([
            'email' => 'member@example.com',
            'nickname' => 'member',
            'locale' => 'en',
            'is_active' => true,
        ]);

        $this->post('/login/request-code', ['email' => 'member@example.com'], ['REMOTE_ADDR' => '127.0.0.1']);

        $response = $this
            ->from('/login')
            ->post('/login/verify-code', [
                'email' => 'member@example.com',
                'code' => (string) config('cryptosik.otp.dev_code'),
            ], ['REMOTE_ADDR' => '127.0.0.2']);

        $response->assertStatus(302);
        $this->assertSame('/vault/unlock', parse_url((string) $response->headers->get('Location'), PHP_URL_PATH));
        $response->assertSessionHas(SessionKeys::USER_EMAIL, 'member@example.com');
        $response->assertSessionHas(SessionKeys::USER_ID);
        $response->assertSessionHas(SessionKeys::USER_NICKNAME, 'member');

        $codeRecord = AuthLoginCode::query()->latest('id')->first();

        $this->assertNotNull($codeRecord);
        $this->assertNotNull($codeRecord?->consumed_at);

        $auditLog = AuditLog::query()->where('action', 'user.login.success')->latest('id')->first();

        $this->assertNotNull($auditLog);
        $this->assertSame('user', $auditLog?->actor_type);
        $this->assertSame($response->getSession()->get(SessionKeys::USER_ID), $auditLog?->actor_id);
        $this->assertSame('user', $auditLog?->target_type);
        $this->assertSame((string) $response->getSession()->get(SessionKeys::USER_ID), $auditLog?->target_id);
        $this->assertSame('email_otp', $auditLog?->metadata_json['auth_method'] ?? null);
    }

    public function test_verify_code_failure_creates_failed_audit_log(): void
    {
        User::query()->create([
            'email' => 'member@example.com',
            'nickname' => 'member',
            'locale' => 'en',
            'is_active' => true,
        ]);

        $this->post('/login/request-code', ['email' => 'member@example.com'], ['REMOTE_ADDR' => '127.0.0.1']);

        $response = $this
            ->from('/login')
            ->post('/login/verify-code', [
                'email' => 'member@example.com',
                'code' => '000000',
            ], ['REMOTE_ADDR' => '127.0.0.1']);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('code');

        $auditLog = AuditLog::query()->where('action', 'user.login.failed')->latest('id')->first();

        $this->assertNotNull($auditLog);
        $this->assertSame('user', $auditLog?->actor_type);
        $this->assertSame('user', $auditLog?->target_type);
        $this->assertSame('email_otp', $auditLog?->metadata_json['auth_method'] ?? null);
    }

    public function test_verify_code_requires_pending_email_state(): void
    {
        User::query()->create([
            'email' => 'member@example.com',
            'nickname' => 'member',
            'locale' => 'en',
            'is_active' => true,
        ]);

        $response = $this
            ->from('/login')
            ->post('/login/verify-code', [
                'email' => 'member@example.com',
                'code' => (string) config('cryptosik.otp.dev_code'),
            ], ['REMOTE_ADDR' => '127.0.0.1']);

        $response->assertRedirect('/login?reset=1');
        $response->assertSessionHasErrors('email');

        $auditLog = AuditLog::query()->where('action', 'user.login.failed')->latest('id')->first();

        $this->assertNotNull($auditLog);
    }

    public function test_request_rate_limit_blocks_excessive_code_issues(): void
    {
        config([
            'cryptosik.otp.request_max_per_window' => 1,
            'cryptosik.otp.request_window_minutes' => 15,
        ]);

        User::query()->create([
            'email' => 'member@example.com',
            'nickname' => 'member',
            'locale' => 'en',
            'is_active' => true,
        ]);

        $this->post('/login/request-code', ['email' => 'member@example.com'], ['REMOTE_ADDR' => '127.0.0.1']);

        $response = $this
            ->from('/login')
            ->post('/login/request-code', ['email' => 'member@example.com'], ['REMOTE_ADDR' => '127.0.0.1']);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');

        $this->assertSame(1, AuthLoginCode::query()->count());
    }
}
