<?php

declare(strict_types=1);

namespace Tests\Integration\Admin;

use EvilStudio\Cryptosik\Models\Admin;
use EvilStudio\Cryptosik\Models\AuditLog;
use EvilStudio\Cryptosik\Models\User;
use EvilStudio\Cryptosik\Models\Vault;
use EvilStudio\Cryptosik\Models\VaultMember;
use EvilStudio\Cryptosik\Services\Vault\VaultAccessService;
use EvilStudio\Cryptosik\Support\SessionKeys;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminVaultMemberNotificationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_create_vault_with_owner_notification_sends_email_and_sets_timestamp(): void
    {
        Mail::fake();

        $admin = $this->createAdmin();
        $owner = $this->createUser('new-owner@example.com', 'NewOwner', 'en');

        $response = $this
            ->withSession($this->adminSession($admin))
            ->from(route('admin.vaults.index'))
            ->post(route('admin.vaults.store'), [
                'owner_user_id' => $owner->id,
                'vault_key' => 'owner-notify-create-key-001',
                'name' => 'Owner Notify Vault',
                'description' => 'owner notification on create',
                'send_owner_notification_now' => 1,
            ]);

        $response->assertRedirect(route('admin.vaults.index'));
        Mail::assertSentCount(1);

        $vault = Vault::query()->latest('created_at')->first();
        $this->assertNotNull($vault);

        $ownerMembership = VaultMember::query()
            ->where('vault_id', $vault?->id)
            ->where('user_id', $owner->id)
            ->first();

        $this->assertNotNull($ownerMembership);
        $this->assertNotNull($ownerMembership?->membership_notified_at);

        $sentLog = AuditLog::query()
            ->where('action', 'admin.vault.member.notification.sent')
            ->latest('id')
            ->first();

        $this->assertNotNull($sentLog);
        $this->assertSame('immediate_owner_create', $sentLog?->metadata_json['mode'] ?? null);
        $this->assertSame($owner->id, $sentLog?->metadata_json['user_id'] ?? null);
    }

    public function test_create_vault_without_owner_notification_can_be_notified_manually_later(): void
    {
        Mail::fake();

        $admin = $this->createAdmin();
        $owner = $this->createUser('manual-owner@example.com', 'ManualOwner', 'pl');

        $createResponse = $this
            ->withSession($this->adminSession($admin))
            ->from(route('admin.vaults.index'))
            ->post(route('admin.vaults.store'), [
                'owner_user_id' => $owner->id,
                'vault_key' => 'owner-notify-create-key-002',
                'name' => 'Owner Notify Later Vault',
                'description' => null,
                'send_owner_notification_now' => 0,
            ]);

        $createResponse->assertRedirect(route('admin.vaults.index'));
        Mail::assertNothingSent();

        $vault = Vault::query()->latest('created_at')->first();
        $this->assertNotNull($vault);

        $ownerMembership = VaultMember::query()
            ->where('vault_id', $vault?->id)
            ->where('user_id', $owner->id)
            ->first();

        $this->assertNotNull($ownerMembership);
        $this->assertNull($ownerMembership?->membership_notified_at);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.vault.member.notification.skipped',
            'target_type' => 'vault',
            'target_id' => (string) $vault?->id,
        ]);

        $notifyResponse = $this
            ->withSession($this->adminSession($admin))
            ->from(route('admin.vaults.index'))
            ->post(route('admin.vaults.members.notify', ['vault' => $vault, 'user' => $owner]));

        $notifyResponse->assertRedirect(route('admin.vaults.index'));
        Mail::assertSentCount(1);

        $ownerMembership->refresh();
        $this->assertNotNull($ownerMembership->membership_notified_at);
    }

    public function test_assign_member_with_send_now_sends_email_and_sets_timestamp(): void
    {
        Mail::fake();

        [$admin, $member, $vault] = $this->createFixture();

        $response = $this
            ->withSession($this->adminSession($admin))
            ->from(route('admin.vaults.index'))
            ->post(route('admin.vaults.members.assign', ['vault' => $vault]), [
                'user_id' => $member->id,
                'send_notification_now' => 1,
            ]);

        $response->assertRedirect(route('admin.vaults.index'));
        Mail::assertSentCount(1);

        $membership = VaultMember::query()
            ->where('vault_id', $vault->id)
            ->where('user_id', $member->id)
            ->first();

        $this->assertNotNull($membership);
        $this->assertNotNull($membership?->membership_notified_at);

        $sentLog = AuditLog::query()
            ->where('action', 'admin.vault.member.notification.sent')
            ->latest('id')
            ->first();

        $this->assertNotNull($sentLog);
        $this->assertSame('immediate', $sentLog?->metadata_json['mode'] ?? null);
    }

    public function test_assign_member_without_send_now_can_be_notified_manually_later(): void
    {
        Mail::fake();

        [$admin, $member, $vault] = $this->createFixture();

        $assignResponse = $this
            ->withSession($this->adminSession($admin))
            ->from(route('admin.vaults.index'))
            ->post(route('admin.vaults.members.assign', ['vault' => $vault]), [
                'user_id' => $member->id,
                'send_notification_now' => 0,
            ]);

        $assignResponse->assertRedirect(route('admin.vaults.index'));
        Mail::assertNothingSent();

        $membership = VaultMember::query()
            ->where('vault_id', $vault->id)
            ->where('user_id', $member->id)
            ->first();

        $this->assertNotNull($membership);
        $this->assertNull($membership?->membership_notified_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.vault.member.notification.skipped',
            'target_type' => 'vault',
            'target_id' => (string) $vault->id,
        ]);

        $notifyResponse = $this
            ->withSession($this->adminSession($admin))
            ->from(route('admin.vaults.index'))
            ->post(route('admin.vaults.members.notify', ['vault' => $vault, 'user' => $member]));

        $notifyResponse->assertRedirect(route('admin.vaults.index'));
        Mail::assertSentCount(1);

        $membership->refresh();
        $this->assertNotNull($membership->membership_notified_at);
    }

    /**
     * @return array{Admin, User, Vault}
     */
    private function createFixture(): array
    {
        $admin = $this->createAdmin();

        $owner = $this->createUser('owner@example.com', 'Owner', 'en');

        $member = $this->createUser('member@example.com', 'Member', 'pl');

        $vault = app(VaultAccessService::class)->createVault(
            owner: $owner,
            vaultKey: 'member-notify-fixture-key',
            name: 'Member Notification Vault',
            description: 'Fixture vault',
        );

        return [$admin, $member, $vault];
    }

    private function createAdmin(): Admin
    {
        return Admin::query()->create([
            'login' => 'root',
            'password_hash' => Hash::make('secret-password'),
            'is_active' => true,
        ]);
    }

    private function createUser(string $email, string $nickname, string $locale): User
    {
        return User::query()->create([
            'email' => $email,
            'nickname' => $nickname,
            'locale' => $locale,
            'notifications_enabled' => true,
            'is_active' => true,
        ]);
    }

    private function adminSession(Admin $admin): array
    {
        return [
            SessionKeys::ADMIN_ID => $admin->id,
            SessionKeys::ADMIN_LOGIN => $admin->login,
        ];
    }
}
