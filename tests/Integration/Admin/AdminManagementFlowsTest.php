<?php

declare(strict_types=1);

namespace Tests\Integration\Admin;

use EvilStudio\Cryptosik\Enums\ChainVerificationResult;
use EvilStudio\Cryptosik\Enums\VaultStatus;
use EvilStudio\Cryptosik\Models\Admin;
use EvilStudio\Cryptosik\Models\AuditLog;
use EvilStudio\Cryptosik\Models\ChainVerificationRun;
use EvilStudio\Cryptosik\Models\Entry;
use EvilStudio\Cryptosik\Models\User;
use EvilStudio\Cryptosik\Models\Vault;
use EvilStudio\Cryptosik\Services\Vault\VaultAccessService;
use EvilStudio\Cryptosik\Support\SessionKeys;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminManagementFlowsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_admin_middleware_redirects_guests_from_protected_pages(): void
    {
        $routes = [
            route('admin.dashboard'),
            route('admin.users.index'),
            route('admin.vaults.index'),
            route('admin.logs.index'),
        ];

        foreach ($routes as $route) {
            $this->get($route)->assertRedirect(route('admin.login.show'));
        }
    }

    public function test_dashboard_renders_counts_and_latest_lists(): void
    {
        $admin = $this->createAdmin();
        $owner = $this->createUser('owner@example.com', 'Owner');
        $secondUser = $this->createUser('member@example.com', 'Member');

        $vault = app(VaultAccessService::class)->createVault(
            owner: $owner,
            vaultKey: 'dashboard-vault-key-123',
            name: 'Dashboard Vault',
            description: 'Dashboard coverage fixture',
        );

        Entry::query()->create([
            'vault_id' => $vault->id,
            'sequence_no' => 1,
            'entry_date' => now()->toDateString(),
            'title_enc' => 'enc-title',
            'content_enc' => 'enc-content',
            'content_format' => 'markdown',
            'prev_hash' => null,
            'entry_hash' => hash('sha256', 'dashboard-entry-1'),
            'attachment_hash' => hash('sha256', 'dashboard-attachments-1'),
            'created_by' => $owner->id,
            'finalized_at' => Carbon::now(),
        ]);

        $response = $this
            ->withSession($this->adminSession($admin))
            ->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('usersCount', 2);
        $response->assertViewHas('vaultsCount', 1);
        $response->assertViewHas('entriesCount', 1);
        $response->assertViewHas('latestUsers', static function ($users) use ($owner, $secondUser): bool {
            return $users->pluck('email')->contains($owner->email)
                && $users->pluck('email')->contains($secondUser->email);
        });
        $response->assertViewHas('latestVaults', static function ($vaults) use ($vault): bool {
            return $vaults->pluck('id')->contains($vault->id);
        });
    }

    public function test_admin_user_management_actions_and_validations(): void
    {
        $admin = $this->createAdmin();
        $existingUser = $this->createUser('existing@example.com', 'Existing', true);

        $createResponse = $this
            ->withSession($this->adminSession($admin))
            ->from(route('admin.users.index'))
            ->post(route('admin.users.store'), [
                'email' => 'NEW.MEMBER@EXAMPLE.COM',
                'nickname' => '  Mystic  ',
                'is_active' => false,
            ]);

        $createResponse->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', [
            'email' => 'new.member@example.com',
            'nickname' => 'Mystic',
            'is_active' => false,
        ]);

        $createdUser = User::query()->where('email', 'new.member@example.com')->first();

        $this->assertNotNull($createdUser);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.user.created',
            'target_type' => 'user',
            'target_id' => (string) $createdUser?->id,
        ]);

        $sameNicknameResponse = $this
            ->withSession($this->adminSession($admin))
            ->from(route('admin.users.index'))
            ->post(route('admin.users.nickname.update', ['user' => $createdUser?->id]), [
                'edit_nickname' => 'Mystic',
                'edit_locale' => 'en',
            ]);

        $sameNicknameResponse->assertRedirect(route('admin.users.index'));

        $updateNicknameResponse = $this
            ->withSession($this->adminSession($admin))
            ->from(route('admin.users.index'))
            ->post(route('admin.users.nickname.update', ['user' => $createdUser?->id]), [
                'edit_nickname' => 'Mystic Prime',
                'edit_locale' => 'de',
            ]);

        $updateNicknameResponse->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', [
            'id' => $createdUser?->id,
            'nickname' => 'Mystic Prime',
            'locale' => 'de',
        ]);

        $deactivateResponse = $this
            ->withSession($this->adminSession($admin))
            ->from(route('admin.users.index'))
            ->post(route('admin.users.deactivate', ['user' => $existingUser->id]));

        $deactivateResponse->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', [
            'id' => $existingUser->id,
            'is_active' => false,
        ]);

        $secondDeactivateResponse = $this
            ->withSession($this->adminSession($admin))
            ->from(route('admin.users.index'))
            ->post(route('admin.users.deactivate', ['user' => $existingUser->id]));

        $secondDeactivateResponse->assertRedirect(route('admin.users.index'));

        $activateResponse = $this
            ->withSession($this->adminSession($admin))
            ->from(route('admin.users.index'))
            ->post(route('admin.users.activate', ['user' => $existingUser->id]));

        $activateResponse->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', [
            'id' => $existingUser->id,
            'is_active' => true,
        ]);

        $secondActivateResponse = $this
            ->withSession($this->adminSession($admin))
            ->from(route('admin.users.index'))
            ->post(route('admin.users.activate', ['user' => $existingUser->id]));

        $secondActivateResponse->assertRedirect(route('admin.users.index'));

        $duplicateEmailResponse = $this
            ->withSession($this->adminSession($admin))
            ->from(route('admin.users.index'))
            ->post(route('admin.users.store'), [
                'email' => 'new.member@example.com',
                'nickname' => 'Another',
                'is_active' => true,
            ]);

        $duplicateEmailResponse->assertRedirect(route('admin.users.index'));
        $duplicateEmailResponse->assertSessionHasErrors('email');

        $nicknameTooLongResponse = $this
            ->withSession($this->adminSession($admin))
            ->from(route('admin.users.index'))
            ->post(route('admin.users.nickname.update', ['user' => $createdUser?->id]), [
                'edit_nickname' => str_repeat('a', ((int) config('cryptosik.limits.user_nickname_chars')) + 1),
                'edit_locale' => 'en',
            ]);

        $nicknameTooLongResponse->assertRedirect(route('admin.users.index'));
        $nicknameTooLongResponse->assertSessionHasErrors('edit_nickname');
    }

    public function test_admin_vault_management_actions_and_validations(): void
    {
        $admin = $this->createAdmin();
        $owner = $this->createUser('vault-owner@example.com', 'VaultOwner', true);
        $inactiveOwner = $this->createUser('inactive-owner@example.com', 'InactiveOwner', false);
        $activeMember = $this->createUser('vault-member@example.com', 'VaultMember', true);
        $inactiveMember = $this->createUser('inactive-member@example.com', 'InactiveMember', false);

        $inactiveOwnerResponse = $this
            ->withSession($this->adminSession($admin))
            ->from(route('admin.vaults.index'))
            ->post(route('admin.vaults.store'), [
                'owner_user_id' => $inactiveOwner->id,
                'vault_key' => 'inactive-owner-key-123',
                'name' => 'Inactive owner vault',
                'description' => 'Should fail',
            ]);

        $inactiveOwnerResponse->assertRedirect(route('admin.vaults.index'));
        $inactiveOwnerResponse->assertSessionHasErrors('owner_user_id');

        $createVaultResponse = $this
            ->withSession($this->adminSession($admin))
            ->from(route('admin.vaults.index'))
            ->post(route('admin.vaults.store'), [
                'owner_user_id' => $owner->id,
                'vault_key' => 'vault-key-admin-flow-001',
                'name' => 'Primary Vault',
                'description' => 'Primary vault description',
            ]);

        $createVaultResponse->assertRedirect(route('admin.vaults.index'));

        $createdVault = Vault::query()->latest('created_at')->first();

        $this->assertNotNull($createdVault);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.vault.created',
            'target_type' => 'vault',
            'target_id' => (string) $createdVault?->id,
        ]);

        ChainVerificationRun::query()->create([
            'vault_id' => $createdVault?->id,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
            'result' => ChainVerificationResult::Failed,
            'broken_sequence_no' => 7,
            'details_json' => ['error' => 'Integrity mismatch'],
            'initiated_by_system' => true,
        ]);

        $vaultListResponse = $this
            ->withSession($this->adminSession($admin))
            ->get(route('admin.vaults.index'));

        $vaultListResponse->assertOk();
        $vaultListResponse->assertSee('Failed');
        $vaultListResponse->assertSee('Broken sequence: 7');

        $duplicateVaultKeyResponse = $this
            ->withSession($this->adminSession($admin))
            ->from(route('admin.vaults.index'))
            ->post(route('admin.vaults.store'), [
                'owner_user_id' => $owner->id,
                'vault_key' => 'vault-key-admin-flow-001',
                'name' => 'Duplicate Key Vault',
                'description' => null,
            ]);

        $duplicateVaultKeyResponse->assertRedirect(route('admin.vaults.index'));
        $duplicateVaultKeyResponse->assertSessionHasErrors('vault_key');

        $assignMemberResponse = $this
            ->withSession($this->adminSession($admin))
            ->from(route('admin.vaults.index'))
            ->post(route('admin.vaults.members.assign', ['vault' => $createdVault?->id]), [
                'user_id' => $activeMember->id,
            ]);

        $assignMemberResponse->assertRedirect(route('admin.vaults.index'));
        $this->assertDatabaseHas('vault_members', [
            'vault_id' => $createdVault?->id,
            'user_id' => $activeMember->id,
            'role' => 'member',
            'added_by_admin_id' => $admin->id,
        ]);

        $assignmentLog = AuditLog::query()->where('action', 'admin.vault.member.assigned')->latest('id')->first();
        $this->assertNotNull($assignmentLog);
        $this->assertSame($activeMember->id, $assignmentLog?->metadata_json['user_id'] ?? null);

        $alreadyAssignedResponse = $this
            ->withSession($this->adminSession($admin))
            ->from(route('admin.vaults.index'))
            ->post(route('admin.vaults.members.assign', ['vault' => $createdVault?->id]), [
                'user_id' => $activeMember->id,
            ]);

        $alreadyAssignedResponse->assertRedirect(route('admin.vaults.index'));
        $alreadyAssignedResponse->assertSessionHasErrors('user_id');

        $inactiveMemberResponse = $this
            ->withSession($this->adminSession($admin))
            ->from(route('admin.vaults.index'))
            ->post(route('admin.vaults.members.assign', ['vault' => $createdVault?->id]), [
                'user_id' => $inactiveMember->id,
            ]);

        $inactiveMemberResponse->assertRedirect(route('admin.vaults.index'));
        $inactiveMemberResponse->assertSessionHasErrors('user_id');

        $softDeletedGuardVault = app(VaultAccessService::class)->createVault(
            owner: $owner,
            vaultKey: 'soft-delete-guard-key-001',
            name: 'Soft deleted guard vault',
            description: null,
        );
        $softDeletedGuardVault->status = VaultStatus::SoftDeleted;
        $softDeletedGuardVault->save();

        $softDeletedAssignResponse = $this
            ->withSession($this->adminSession($admin))
            ->from(route('admin.vaults.index'))
            ->post(route('admin.vaults.members.assign', ['vault' => $softDeletedGuardVault->id]), [
                'user_id' => $activeMember->id,
            ]);

        $softDeletedAssignResponse->assertRedirect(route('admin.vaults.index'));
        $softDeletedAssignResponse->assertSessionHasErrors('user_id');

        $archiveSoftDeletedResponse = $this
            ->withSession($this->adminSession($admin))
            ->from(route('admin.vaults.index'))
            ->post(route('admin.vaults.archive', ['vault' => $softDeletedGuardVault->id]));

        $archiveSoftDeletedResponse->assertRedirect(route('admin.vaults.index'));
        $archiveSoftDeletedResponse->assertSessionHasErrors('vault');

        $archiveResponse = $this
            ->withSession($this->adminSession($admin))
            ->from(route('admin.vaults.index'))
            ->post(route('admin.vaults.archive', ['vault' => $createdVault?->id]));

        $archiveResponse->assertRedirect(route('admin.vaults.index'));

        $createdVault?->refresh();
        $this->assertSame(VaultStatus::Archived, $createdVault?->status);
        $this->assertNotNull($createdVault?->archived_at);

        $restorableVault = app(VaultAccessService::class)->createVault(
            owner: $owner,
            vaultKey: 'restorable-vault-key-001',
            name: 'Restorable vault',
            description: null,
        );

        $softDeleteResponse = $this
            ->withSession($this->adminSession($admin))
            ->from(route('admin.vaults.index'))
            ->post(route('admin.vaults.soft-delete', ['vault' => $restorableVault->id]));

        $softDeleteResponse->assertRedirect(route('admin.vaults.index'));

        $restorableVault = Vault::withTrashed()->find($restorableVault->id);
        $this->assertNotNull($restorableVault);
        $this->assertSame(VaultStatus::SoftDeleted, $restorableVault?->status);
        $this->assertNotNull($restorableVault?->deleted_at);

        $restoreMissingResponse = $this
            ->withSession($this->adminSession($admin))
            ->from(route('admin.vaults.index'))
            ->post(route('admin.vaults.restore', ['vaultId' => '00000000-0000-0000-0000-000000000000']));

        $restoreMissingResponse->assertRedirect(route('admin.vaults.index'));
        $restoreMissingResponse->assertSessionHasErrors('vault');

        $restoreResponse = $this
            ->withSession($this->adminSession($admin))
            ->from(route('admin.vaults.index'))
            ->post(route('admin.vaults.restore', ['vaultId' => $restorableVault?->id]));

        $restoreResponse->assertRedirect(route('admin.vaults.index'));

        $restored = Vault::withTrashed()->find($restorableVault?->id);
        $this->assertNotNull($restored);
        $this->assertSame(VaultStatus::Active, $restored?->status);
        $this->assertNull($restored?->deleted_at);
    }

    public function test_admin_logs_resolve_actor_labels_and_support_filters(): void
    {
        $admin = $this->createAdmin();
        $userWithNickname = $this->createUser('with-nick@example.com', 'Shadow', true);
        $userWithoutNickname = $this->createUser('without-nick@example.com', '', true);

        AuditLog::query()->create([
            'actor_type' => 'admin',
            'actor_id' => $admin->id,
            'action' => 'admin.login.success',
            'target_type' => 'admin',
            'target_id' => (string) $admin->id,
            'metadata_json' => ['auth_method' => 'password'],
            'created_at' => now()->subMinutes(3),
        ]);

        AuditLog::query()->create([
            'actor_type' => 'user',
            'actor_id' => $userWithNickname->id,
            'action' => 'user.login.success',
            'target_type' => 'user',
            'target_id' => (string) $userWithNickname->id,
            'metadata_json' => ['auth_method' => 'email_otp'],
            'created_at' => now()->subMinutes(2),
        ]);

        AuditLog::query()->create([
            'actor_type' => 'user',
            'actor_id' => $userWithoutNickname->id,
            'action' => 'user.settings.updated',
            'target_type' => 'user',
            'target_id' => (string) $userWithoutNickname->id,
            'metadata_json' => null,
            'created_at' => now()->subMinute(),
        ]);

        AuditLog::query()->create([
            'actor_type' => 'user',
            'actor_id' => 999,
            'action' => 'user.login.failed',
            'target_type' => 'user',
            'target_id' => null,
            'metadata_json' => ['auth_method' => 'email_otp'],
            'created_at' => now(),
        ]);

        AuditLog::query()->create([
            'actor_type' => 'system',
            'actor_id' => 0,
            'action' => 'integrity.chain.failed',
            'target_type' => 'vault',
            'target_id' => 'fixture-vault-id',
            'metadata_json' => ['broken_sequence_no' => 1],
            'created_at' => now()->addSecond(),
        ]);

        AuditLog::query()->create([
            'actor_type' => null,
            'actor_id' => null,
            'action' => 'system.maintenance.completed',
            'target_type' => null,
            'target_id' => null,
            'metadata_json' => null,
            'created_at' => now()->addSeconds(2),
        ]);

        $allLogsResponse = $this
            ->withSession($this->adminSession($admin))
            ->get(route('admin.logs.index'));

        $allLogsResponse->assertOk();
        $allLogsResponse->assertSee('root');
        $allLogsResponse->assertSee('Shadow');
        $allLogsResponse->assertSee('without-nick@example.com');
        $allLogsResponse->assertSee('user#999');
        $allLogsResponse->assertSee('system#0');
        $allLogsResponse->assertSee('integrity.chain.failed');
        $allLogsResponse->assertSee('n/a');

        $filteredResponse = $this
            ->withSession($this->adminSession($admin))
            ->get(route('admin.logs.index', [
                'actor_type' => 'user',
                'action' => 'user.login',
            ]));

        $filteredResponse->assertOk();
        $filteredResponse->assertSee('user.login.success');
        $filteredResponse->assertSee('user.login.failed');
        $filteredResponse->assertDontSee('admin.login.success');
        $filteredResponse->assertViewHas('actorType', 'user');
        $filteredResponse->assertViewHas('action', 'user.login');
    }

    private function createAdmin(): Admin
    {
        return Admin::query()->create([
            'login' => 'root',
            'password_hash' => Hash::make('super-secret-password'),
            'is_active' => true,
        ]);
    }

    private function createUser(string $email, string $nickname, bool $isActive = true): User
    {
        return User::query()->create([
            'email' => $email,
            'nickname' => $nickname,
            'locale' => 'en',
            'is_active' => $isActive,
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
