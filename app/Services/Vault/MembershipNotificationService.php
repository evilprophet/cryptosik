<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Services\Vault;

use EvilStudio\Cryptosik\Mail\VaultMemberAddedMail;
use EvilStudio\Cryptosik\Models\Admin;
use EvilStudio\Cryptosik\Models\User;
use EvilStudio\Cryptosik\Models\Vault;
use EvilStudio\Cryptosik\Models\VaultMember;
use EvilStudio\Cryptosik\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class MembershipNotificationService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function skipNotification(Admin $admin, Vault $vault, User $user): void
    {
        $this->auditLogService->adminVaultMemberNotificationSkipped($admin, $vault, $user);
    }

    public function sendMemberAddedNotification(Admin $admin, Vault $vault, User $user, string $mode): void
    {
        $membership = VaultMember::query()
            ->where('vault_id', $vault->id)
            ->where('user_id', $user->id)
            ->first();

        if ($membership === null) {
            throw new RuntimeException(__('messages.admin.vaults.errors.member_not_found'));
        }

        try {
            Mail::to($user->email)
                ->locale($user->locale)
                ->send(new VaultMemberAddedMail($vault->id));

            $membership->membership_notified_at = now();
            $membership->save();

            $this->auditLogService->adminVaultMemberNotificationSent($admin, $vault, $user, $mode);
        } catch (Throwable $exception) {
            $this->auditLogService->adminVaultMemberNotificationFailed(
                $admin,
                $vault,
                $user,
                $exception::class,
            );

            throw new RuntimeException(__('messages.admin.vaults.errors.member_notification_failed'));
        }
    }
}

