<?php

declare(strict_types=1);

namespace Tests\Integration\Auth;

use EvilStudio\Cryptosik\Models\Admin;
use EvilStudio\Cryptosik\Models\Entry;
use EvilStudio\Cryptosik\Models\User;
use EvilStudio\Cryptosik\Models\Vault;
use EvilStudio\Cryptosik\Models\VaultMember;
use EvilStudio\Cryptosik\Services\Vault\VaultAccessService;
use EvilStudio\Cryptosik\Support\SessionKeys;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class SessionRevalidationTest extends TestCase
{
    use RefreshDatabase;

    private const VAULT_KEY = 'revalidation-vault-key-001';

    private User $user;

    private Vault $vault;

    private string $dataKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->user = User::query()->create([
            'email' => 'member@example.com',
            'nickname' => 'member',
            'locale' => 'en',
            'is_active' => true,
        ]);

        $service = app(VaultAccessService::class);
        $this->vault = $service->createVault($this->user, self::VAULT_KEY, 'Revalidation Vault', null);

        $unlockResult = $service->unlockVaultForUser($this->user, self::VAULT_KEY);

        if ($unlockResult === null) {
            throw new RuntimeException('Unable to prepare unlocked vault fixture.');
        }

        $this->dataKey = $unlockResult->dataKey;
    }

    public function test_deactivated_user_loses_workspace_access_on_the_next_request(): void
    {
        $this->withSession($this->unlockedSession())->get('/vault')->assertOk();

        $this->user->is_active = false;
        $this->user->save();

        $response = $this->withSession($this->unlockedSession())->get('/vault');

        $response->assertRedirect(route('auth.user.login.show'));
        $response->assertSessionMissing(SessionKeys::USER_ID);
        $response->assertSessionMissing(SessionKeys::UNLOCKED_VAULT_KEY);
    }

    public function test_deactivated_user_cannot_write_an_immutable_entry(): void
    {
        $this->user->is_active = false;
        $this->user->save();

        $response = $this
            ->withSession($this->unlockedSession())
            ->from('/vault?mode=new')
            ->post('/vault/draft/finalize', [
                'entry_date' => '2026-04-13',
                'title' => 'Wpis po dezaktywacji',
                'content' => 'Nie powinien powstac.',
            ]);

        $response->assertRedirect(route('auth.user.login.show'));

        $this->assertSame(0, Entry::query()->where('vault_id', $this->vault->id)->count());
    }

    public function test_revoked_membership_invalidates_an_already_unlocked_vault(): void
    {
        VaultMember::query()
            ->where('vault_id', $this->vault->id)
            ->where('user_id', $this->user->id)
            ->delete();

        $workspace = $this->withSession($this->unlockedSession())->get('/vault');

        $workspace->assertRedirect(route('auth.vault.unlock.show'));
        $workspace->assertSessionMissing(SessionKeys::UNLOCKED_VAULT_KEY);

        $finalize = $this
            ->withSession($this->unlockedSession())
            ->post('/vault/draft/finalize', [
                'entry_date' => '2026-04-13',
                'title' => 'Wpis bez czlonkostwa',
                'content' => 'Nie powinien powstac.',
            ]);

        $finalize->assertRedirect(route('auth.vault.unlock.show'));

        $this->assertSame(0, Entry::query()->where('vault_id', $this->vault->id)->count());
    }

    public function test_deactivated_admin_loses_panel_access_on_the_next_request(): void
    {
        $admin = Admin::query()->create([
            'login' => 'root',
            'password_hash' => bcrypt('super-secret-password'),
            'is_active' => true,
        ]);

        $session = [
            SessionKeys::ADMIN_ID => $admin->id,
            SessionKeys::ADMIN_LOGIN => 'root',
        ];

        $this->withSession($session)->get(route('admin.dashboard'))->assertOk();

        $admin->is_active = false;
        $admin->save();

        $response = $this->withSession($session)->get(route('admin.dashboard'));

        $response->assertRedirect(route('admin.login.show'));
        $response->assertSessionMissing(SessionKeys::ADMIN_ID);
    }

    public function test_rejecting_one_role_leaves_the_other_role_session_intact(): void
    {
        $admin = Admin::query()->create([
            'login' => 'root',
            'password_hash' => bcrypt('super-secret-password'),
            'is_active' => true,
        ]);

        $this->user->is_active = false;
        $this->user->save();

        $session = $this->unlockedSession() + [
            SessionKeys::ADMIN_ID => $admin->id,
            SessionKeys::ADMIN_LOGIN => 'root',
        ];

        $response = $this->withSession($session)->get('/vault');

        $response->assertRedirect(route('auth.user.login.show'));
        $response->assertSessionMissing(SessionKeys::USER_ID);
        $response->assertSessionHas(SessionKeys::ADMIN_ID, $admin->id);
    }

    /**
     * @return array<string, mixed>
     */
    private function unlockedSession(): array
    {
        return [
            SessionKeys::USER_ID => $this->user->id,
            SessionKeys::USER_EMAIL => $this->user->email,
            SessionKeys::USER_NICKNAME => $this->user->nickname,
            SessionKeys::UNLOCKED_VAULT_ID => $this->vault->id,
            SessionKeys::UNLOCKED_VAULT_KEY => $this->dataKey,
        ];
    }
}
