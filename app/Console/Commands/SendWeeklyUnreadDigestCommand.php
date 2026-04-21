<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Console\Commands;

use EvilStudio\Cryptosik\Services\Vault\WeeklyUnreadDigestService;
use Illuminate\Console\Command;

class SendWeeklyUnreadDigestCommand extends Command
{
    protected $signature = 'cryptosik:notifications:weekly-unread {--per-vault=5 : Max entry items per vault in digest}';

    protected $description = 'Send weekly unread-entry digest notifications to users.';

    public function __construct(
        private readonly WeeklyUnreadDigestService $weeklyUnreadDigestService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $perVault = max(1, (int) $this->option('per-vault'));
        $stats = $this->weeklyUnreadDigestService->sendWeeklyDigests($perVault);

        $this->info(sprintf(
            'Weekly digest summary: sent=%d skipped=%d failed=%d',
            (int) $stats['sent'],
            (int) $stats['skipped'],
            (int) $stats['failed'],
        ));

        return self::SUCCESS;
    }
}

