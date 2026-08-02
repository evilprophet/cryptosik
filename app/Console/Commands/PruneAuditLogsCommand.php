<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Console\Commands;

use EvilStudio\Cryptosik\Models\AuditLog;
use Illuminate\Console\Command;

class PruneAuditLogsCommand extends Command
{
    private const RETENTION_NEVER = 'never';

    private const DELETE_CHUNK_SIZE = 1000;

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

        if (! is_numeric($retention)) {
            $this->error('Invalid audit log retention. Allowed values: never, 30, 60, 90, 120.');

            return self::FAILURE;
        }

        $retentionDays = (int) $retention;

        if (! in_array($retentionDays, self::ALLOWED_RETENTION_DAYS, true)) {
            $narrowed = $this->narrowRetention($retentionDays);

            $this->warn(sprintf(
                'Retention %d is not one of %s; using %d instead.',
                $retentionDays,
                implode(', ', self::ALLOWED_RETENTION_DAYS),
                $narrowed,
            ));

            $retentionDays = $narrowed;
        }

        $deletedCount = $this->pruneInChunks(now()->subDays($retentionDays));

        $this->info(sprintf('Audit log pruning completed: %d deleted.', $deletedCount));

        return self::SUCCESS;
    }

    // Chunked so each write lock on the SQLite file stays short.
    private function pruneInChunks(\DateTimeInterface $olderThan): int
    {
        $deletedCount = 0;

        do {
            $deleted = AuditLog::query()
                ->where('created_at', '<', $olderThan)
                ->limit(self::DELETE_CHUNK_SIZE)
                ->delete();

            $deletedCount += $deleted;
        } while ($deleted > 0);

        return $deletedCount;
    }

    // Ties resolve to the longer retention, so narrowing never deletes more than asked.
    private function narrowRetention(int $requestedDays): int
    {
        $allowed = self::ALLOWED_RETENTION_DAYS;
        $best = $allowed[count($allowed) - 1];

        foreach ($allowed as $candidate) {
            if (abs($candidate - $requestedDays) <= abs($best - $requestedDays)) {
                $best = $candidate;
            }
        }

        return $best;
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
