<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Console\Commands;

use EvilStudio\Cryptosik\Models\AuditLog;
use Illuminate\Console\Command;

class PruneAuditLogsCommand extends Command
{
    private const RETENTION_NEVER = 'never';

    private const ALLOWED_RETENTION_DAYS = [
        30,
        60,
        90,
        120,
    ];

    protected $signature = 'cryptosik:audit-logs-prune {--retention= : Override retention: never, 30, 60, 90, 120}';

    protected $description = 'Delete audit logs older than configured retention.';

    public function handle(): int
    {
        $retention = $this->resolveRetention();

        if ($retention === self::RETENTION_NEVER) {
            $this->info('Audit log pruning skipped: retention is never.');

            return self::SUCCESS;
        }

        $retentionDays = (int) $retention;

        if (!in_array($retentionDays, self::ALLOWED_RETENTION_DAYS, true)) {
            $this->error('Invalid audit log retention. Allowed values: never, 30, 60, 90, 120.');

            return self::FAILURE;
        }

        $deletedCount = AuditLog::query()
            ->where('created_at', '<', now()->subDays($retentionDays))
            ->delete();

        $this->info(sprintf('Audit log pruning completed: %d deleted.', $deletedCount));

        return self::SUCCESS;
    }

    private function resolveRetention(): string
    {
        $override = $this->option('retention');

        if (is_string($override) && trim($override) !== '') {
            return trim($override);
        }

        return trim((string) config('cryptosik.audit_logs.retention_days', self::RETENTION_NEVER));
    }
}
