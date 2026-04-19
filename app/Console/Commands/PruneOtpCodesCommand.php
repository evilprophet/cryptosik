<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Console\Commands;

use EvilStudio\Cryptosik\Models\AuthLoginCode;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class PruneOtpCodesCommand extends Command
{
    protected $signature = 'cryptosik:otp-prune {--dry-run : Only show how many rows would be deleted}';

    protected $description = 'Delete OTP records that are no longer usable (consumed or expired).';

    public function handle(): int
    {
        $query = AuthLoginCode::query()
            ->whereNotNull('consumed_at')
            ->orWhere('expires_at', '<=', now());

        $rows = (clone $query)->count();

        if ($rows === 0) {
            $this->info('No obsolete OTP records found.');

            return self::SUCCESS;
        }

        if ((bool) $this->option('dry-run')) {
            $this->line(sprintf('Dry-run: %d OTP records would be deleted.', $rows));

            return self::SUCCESS;
        }

        $deleted = $this->deleteRows($query);

        $this->info(sprintf('Deleted %d obsolete OTP records.', $deleted));

        return self::SUCCESS;
    }

    private function deleteRows(Builder $query): int
    {
        return $query->delete();
    }
}
