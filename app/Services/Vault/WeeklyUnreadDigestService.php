<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Services\Vault;

use EvilStudio\Cryptosik\Mail\WeeklyUnreadDigestMail;
use EvilStudio\Cryptosik\Models\User;
use EvilStudio\Cryptosik\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\Mail;
use Throwable;

class WeeklyUnreadDigestService
{
    public function __construct(
        private readonly UnreadEntryService $unreadEntryService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * @return array{sent:int, skipped:int, failed:int}
     */
    public function sendWeeklyDigests(int $entriesPerVault): array
    {
        $stats = [
            'sent' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        $users = User::query()
            ->where('is_active', true)
            ->whereHas('memberships.vault')
            ->get(['id', 'email', 'locale']);

        foreach ($users as $user) {
            $payload = $this->unreadEntryService->getUnreadDigestForUser($user, $entriesPerVault);
            $totalUnread = (int) ($payload['total_unread'] ?? 0);

            if ($totalUnread === 0) {
                $stats['skipped']++;

                continue;
            }

            try {
                Mail::to($user->email)
                    ->locale($user->locale)
                    ->send(new WeeklyUnreadDigestMail(
                        vaults: $payload['vaults'],
                        totalUnread: $totalUnread,
                    ));

                $stats['sent']++;
                $this->auditLogService->weeklyUnreadDigestSent($user, count($payload['vaults']), $totalUnread);
            } catch (Throwable $exception) {
                $stats['failed']++;
                $this->auditLogService->weeklyUnreadDigestFailed($user, $exception::class);
            }
        }

        return $stats;
    }
}

