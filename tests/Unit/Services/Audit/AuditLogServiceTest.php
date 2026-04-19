<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Audit;

use EvilStudio\Cryptosik\Enums\EntryContentFormat;
use EvilStudio\Cryptosik\Enums\VaultStatus;
use EvilStudio\Cryptosik\Models\Admin;
use EvilStudio\Cryptosik\Models\AuditLog;
use EvilStudio\Cryptosik\Models\Entry;
use EvilStudio\Cryptosik\Models\User;
use EvilStudio\Cryptosik\Models\Vault;
use EvilStudio\Cryptosik\Services\Audit\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuditLogServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_service_records_all_supported_actions(): void
    {
        $service = app(AuditLogService::class);

        $admin = Admin::query()->create([
            'login' => 'root',
            'password_hash' => Hash::make('secret-password'),
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'email' => 'member@example.com',
            'nickname' => 'member',
            'locale' => 'en',
            'is_active' => true,
        ]);

        $vault = Vault::query()->create([
            'owner_user_id' => $user->id,
            'name_enc' => 'enc-name',
            'description_enc' => 'enc-description',
            'status' => VaultStatus::Active,
        ]);

        $entry = Entry::query()->create([
            'vault_id' => $vault->id,
            'sequence_no' => 1,
            'entry_date' => now()->toDateString(),
            'title_enc' => 'enc-title',
            'content_enc' => 'enc-content',
            'content_format' => EntryContentFormat::Markdown,
            'prev_hash' => null,
            'entry_hash' => hash('sha256', 'entry-hash-1'),
            'attachment_hash' => null,
            'created_by' => $user->id,
            'finalized_at' => now(),
        ]);

        $service->userLoginSuccess($user);
        $service->userLoginFailed($user);
        $service->userLoginFailed(null);
        $service->adminLoginSuccess($admin);
        $service->adminLoginFailed($admin);
        $service->adminLoginFailed(null);
        $service->adminUserCreated($admin, $user);
        $service->adminVaultCreated($admin, $vault);
        $service->adminVaultMemberAssigned($admin, $vault, $user);
        $service->vaultOpenSuccess($user, $vault);
        $service->vaultOpenFailed($user);
        $service->vaultLocked($user, $vault);
        $service->vaultLocked($user, null);
        $service->vaultEntryAdded($user, $entry);
        $service->vaultDraftSaved($user, $vault);
        $service->vaultFileUploaded($user, $vault);
        $service->integrityVerificationFailed($vault, 7, 'Hash mismatch');

        $this->assertDatabaseCount('audit_logs', 17);

        $expectedActions = [
            'user.login.success',
            'user.login.failed',
            'admin.login.success',
            'admin.login.failed',
            'admin.user.created',
            'admin.vault.created',
            'admin.vault.member.assigned',
            'vault.open.success',
            'vault.open.failed',
            'vault.locked',
            'vault.entry.added',
            'vault.draft.saved',
            'vault.file.uploaded',
            'integrity.chain.failed',
        ];

        foreach ($expectedActions as $action) {
            $this->assertDatabaseHas('audit_logs', [
                'action' => $action,
            ]);
        }

        $userLoginSuccessLog = AuditLog::query()->where('action', 'user.login.success')->latest('id')->first();
        $adminLoginSuccessLog = AuditLog::query()->where('action', 'admin.login.success')->latest('id')->first();

        $this->assertNotNull($userLoginSuccessLog);
        $this->assertNotNull($adminLoginSuccessLog);

        $this->assertSame('email_otp', $userLoginSuccessLog?->metadata_json['auth_method'] ?? null);
        $this->assertSame('password', $adminLoginSuccessLog?->metadata_json['auth_method'] ?? null);
        $this->assertArrayNotHasKey('password', $adminLoginSuccessLog?->metadata_json ?? []);

        $integrityFailureLog = AuditLog::query()->where('action', 'integrity.chain.failed')->latest('id')->first();

        $this->assertNotNull($integrityFailureLog);
        $this->assertSame('system', $integrityFailureLog?->actor_type);
        $this->assertSame(0, $integrityFailureLog?->actor_id);
        $this->assertSame(7, $integrityFailureLog?->metadata_json['broken_sequence_no'] ?? null);
        $this->assertSame('Hash mismatch', $integrityFailureLog?->metadata_json['error'] ?? null);
    }
}
