<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Services\Audit;

use EvilStudio\Cryptosik\Models\Admin;
use EvilStudio\Cryptosik\Models\AuditLog;
use EvilStudio\Cryptosik\Models\Entry;
use EvilStudio\Cryptosik\Models\User;
use EvilStudio\Cryptosik\Models\Vault;

class AuditLogService
{
    private const SYSTEM_ACTOR_TYPE = 'system';

    private const SYSTEM_ACTOR_ID = 0;

    public function userLoginSuccess(User $user): void
    {
        $this->write(
            actorType: 'user',
            actorId: $user->id,
            action: 'user.login.success',
            targetType: 'user',
            targetId: (string) $user->id,
            metadata: ['auth_method' => 'email_otp'],
        );
    }

    public function userLoginFailed(?User $user): void
    {
        $this->write(
            actorType: 'user',
            actorId: $user?->id,
            action: 'user.login.failed',
            targetType: 'user',
            targetId: $user !== null ? (string) $user->id : null,
            metadata: ['auth_method' => 'email_otp'],
        );
    }

    public function adminLoginSuccess(Admin $admin): void
    {
        $this->write(
            actorType: 'admin',
            actorId: $admin->id,
            action: 'admin.login.success',
            targetType: 'admin',
            targetId: (string) $admin->id,
            metadata: ['auth_method' => 'password'],
        );
    }

    public function adminLoginFailed(?Admin $admin): void
    {
        $this->write(
            actorType: 'admin',
            actorId: $admin?->id,
            action: 'admin.login.failed',
            targetType: 'admin',
            targetId: $admin !== null ? (string) $admin->id : null,
            metadata: ['auth_method' => 'password'],
        );
    }

    public function adminUserCreated(Admin $admin, User $user): void
    {
        $this->write(
            actorType: 'admin',
            actorId: $admin->id,
            action: 'admin.user.created',
            targetType: 'user',
            targetId: (string) $user->id,
        );
    }

    public function adminVaultCreated(Admin $admin, Vault $vault): void
    {
        $this->write(
            actorType: 'admin',
            actorId: $admin->id,
            action: 'admin.vault.created',
            targetType: 'vault',
            targetId: (string) $vault->id,
        );
    }

    public function adminVaultMemberAssigned(Admin $admin, Vault $vault, User $user): void
    {
        $this->write(
            actorType: 'admin',
            actorId: $admin->id,
            action: 'admin.vault.member.assigned',
            targetType: 'vault',
            targetId: (string) $vault->id,
            metadata: ['user_id' => $user->id],
        );
    }

    public function adminVaultMemberNotificationSent(Admin $admin, Vault $vault, User $user, string $mode): void
    {
        $this->write(
            actorType: 'admin',
            actorId: $admin->id,
            action: 'admin.vault.member.notification.sent',
            targetType: 'vault',
            targetId: (string) $vault->id,
            metadata: [
                'user_id' => $user->id,
                'mode' => $mode,
            ],
        );
    }

    public function adminVaultMemberNotificationSkipped(Admin $admin, Vault $vault, User $user): void
    {
        $this->write(
            actorType: 'admin',
            actorId: $admin->id,
            action: 'admin.vault.member.notification.skipped',
            targetType: 'vault',
            targetId: (string) $vault->id,
            metadata: [
                'user_id' => $user->id,
            ],
        );
    }

    public function adminVaultMemberNotificationFailed(Admin $admin, Vault $vault, User $user, string $errorCode): void
    {
        $this->write(
            actorType: 'admin',
            actorId: $admin->id,
            action: 'admin.vault.member.notification.failed',
            targetType: 'vault',
            targetId: (string) $vault->id,
            metadata: [
                'user_id' => $user->id,
                'error_code' => $errorCode,
            ],
        );
    }

    public function vaultOpenSuccess(User $user, Vault $vault): void
    {
        $this->write(
            actorType: 'user',
            actorId: $user->id,
            action: 'vault.open.success',
            targetType: 'vault',
            targetId: (string) $vault->id,
        );
    }

    public function vaultOpenFailed(User $user): void
    {
        $this->write(
            actorType: 'user',
            actorId: $user->id,
            action: 'vault.open.failed',
            targetType: 'vault',
            targetId: null,
        );
    }

    public function vaultLocked(User $user, ?Vault $vault): void
    {
        $this->write(
            actorType: 'user',
            actorId: $user->id,
            action: 'vault.locked',
            targetType: 'vault',
            targetId: $vault !== null ? (string) $vault->id : null,
        );
    }

    public function vaultEntryAdded(User $user, Entry $entry): void
    {
        $this->write(
            actorType: 'user',
            actorId: $user->id,
            action: 'vault.entry.added',
            targetType: 'entry',
            targetId: (string) $entry->id,
        );
    }

    public function vaultDraftSaved(User $user, Vault $vault): void
    {
        $this->write(
            actorType: 'user',
            actorId: $user->id,
            action: 'vault.draft.saved',
            targetType: 'vault',
            targetId: (string) $vault->id,
        );
    }

    public function vaultFileUploaded(User $user, Vault $vault): void
    {
        $this->write(
            actorType: 'user',
            actorId: $user->id,
            action: 'vault.file.uploaded',
            targetType: 'vault',
            targetId: (string) $vault->id,
        );
    }

    public function integrityVerificationFailed(Vault $vault, ?int $brokenSequenceNo, string $error): void
    {
        $this->write(
            actorType: self::SYSTEM_ACTOR_TYPE,
            actorId: self::SYSTEM_ACTOR_ID,
            action: 'integrity.chain.failed',
            targetType: 'vault',
            targetId: (string) $vault->id,
            metadata: [
                'broken_sequence_no' => $brokenSequenceNo,
                'error' => $error,
            ],
        );
    }

    public function weeklyUnreadDigestSent(User $user, int $vaultCount, int $unreadCount): void
    {
        $this->write(
            actorType: self::SYSTEM_ACTOR_TYPE,
            actorId: self::SYSTEM_ACTOR_ID,
            action: 'system.user.weekly_unread_digest.sent',
            targetType: 'user',
            targetId: (string) $user->id,
            metadata: [
                'vault_count' => $vaultCount,
                'unread_count' => $unreadCount,
            ],
        );
    }

    public function weeklyUnreadDigestFailed(User $user, string $errorCode): void
    {
        $this->write(
            actorType: self::SYSTEM_ACTOR_TYPE,
            actorId: self::SYSTEM_ACTOR_ID,
            action: 'system.user.weekly_unread_digest.failed',
            targetType: 'user',
            targetId: (string) $user->id,
            metadata: [
                'error_code' => $errorCode,
            ],
        );
    }

    private function write(
        ?string $actorType,
        ?int $actorId,
        string $action,
        ?string $targetType,
        ?string $targetId,
        ?array $metadata = null,
    ): void {
        AuditLog::query()->create([
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'metadata_json' => $metadata,
            'created_at' => now(),
        ]);
    }
}
