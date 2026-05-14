<?php

declare(strict_types=1);

namespace Tests\Integration\Admin;

use EvilStudio\Cryptosik\Models\Admin;
use EvilStudio\Cryptosik\Models\AuditLog;
use EvilStudio\Cryptosik\Models\User;
use EvilStudio\Cryptosik\Support\SessionKeys;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminLoginAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_admin_base_path_redirects_to_login(): void
    {
        $response = $this->get('/'.trim((string) config('cryptosik.admin.path', 'admin'), '/'));

        $response->assertRedirect(route('admin.login.show'));
    }

    public function test_admin_login_creates_audit_log_without_sensitive_data(): void
    {
        Admin::query()->create([
            'login' => 'root',
            'password_hash' => Hash::make('super-secret-password'),
            'is_active' => true,
        ]);

        $response = $this
            ->from(route('admin.login.show'))
            ->post(route('admin.login.submit'), [
                'login' => 'root',
                'password' => 'super-secret-password',
            ]);

        $response->assertRedirect(route('admin.dashboard'));
        $response->assertSessionHas(SessionKeys::ADMIN_ID);

        $auditLog = AuditLog::query()->where('action', 'admin.login.success')->latest('id')->first();

        $this->assertNotNull($auditLog);
        $this->assertSame('admin', $auditLog?->actor_type);
        $this->assertSame($response->getSession()->get(SessionKeys::ADMIN_ID), $auditLog?->actor_id);
        $this->assertSame('admin', $auditLog?->target_type);
        $this->assertSame((string) $response->getSession()->get(SessionKeys::ADMIN_ID), $auditLog?->target_id);
        $this->assertSame('password', $auditLog?->metadata_json['auth_method'] ?? null);
    }

    public function test_admin_login_failure_creates_failed_audit_log(): void
    {
        Admin::query()->create([
            'login' => 'root',
            'password_hash' => Hash::make('super-secret-password'),
            'is_active' => true,
        ]);

        $response = $this
            ->from(route('admin.login.show'))
            ->post(route('admin.login.submit'), [
                'login' => 'root',
                'password' => 'wrong-password',
            ]);

        $response->assertRedirect(route('admin.login.show'));
        $response->assertSessionHasErrors('login');

        $auditLog = AuditLog::query()->where('action', 'admin.login.failed')->latest('id')->first();

        $this->assertNotNull($auditLog);
        $this->assertSame('admin', $auditLog?->actor_type);
        $this->assertSame('admin', $auditLog?->target_type);
        $this->assertSame('password', $auditLog?->metadata_json['auth_method'] ?? null);
    }

    public function test_admin_login_and_logout_do_not_clear_user_session(): void
    {
        $user = User::query()->create([
            'email' => 'member@example.com',
            'nickname' => 'Member',
            'locale' => 'en',
            'is_active' => true,
        ]);

        Admin::query()->create([
            'login' => 'root',
            'password_hash' => Hash::make('super-secret-password'),
            'is_active' => true,
        ]);

        $loginResponse = $this
            ->withSession([
                SessionKeys::USER_ID => $user->id,
                SessionKeys::USER_EMAIL => $user->email,
                SessionKeys::USER_NICKNAME => $user->nickname,
            ])
            ->post(route('admin.login.submit'), [
                'login' => 'root',
                'password' => 'super-secret-password',
            ]);

        $loginResponse->assertRedirect(route('admin.dashboard'));
        $loginResponse->assertSessionHas(SessionKeys::ADMIN_ID);
        $loginResponse->assertSessionHas(SessionKeys::USER_ID, $user->id);

        $logoutResponse = $this
            ->withSession([
                SessionKeys::ADMIN_ID => $loginResponse->getSession()->get(SessionKeys::ADMIN_ID),
                SessionKeys::ADMIN_LOGIN => 'root',
                SessionKeys::USER_ID => $user->id,
                SessionKeys::USER_EMAIL => $user->email,
                SessionKeys::USER_NICKNAME => $user->nickname,
            ])
            ->post(route('admin.logout'));

        $logoutResponse->assertRedirect(route('admin.login.show'));
        $logoutResponse->assertSessionMissing(SessionKeys::ADMIN_ID);
        $logoutResponse->assertSessionHas(SessionKeys::USER_ID, $user->id);
    }
}
