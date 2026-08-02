<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Console\Commands;

use Carbon\CarbonImmutable;
use EvilStudio\Cryptosik\Enums\ChainVerificationResult;
use EvilStudio\Cryptosik\Enums\VaultStatus;
use EvilStudio\Cryptosik\Models\ChainVerificationRun;
use EvilStudio\Cryptosik\Models\Vault;
use EvilStudio\Cryptosik\Services\Audit\AuditLogService;
use EvilStudio\Cryptosik\Services\Integrity\IntegrityVerificationService;
use Illuminate\Console\Command;
use Throwable;

class VerifyVaultChainsCommand extends Command
{
    protected $signature = 'cryptosik:verify-chains {--vault=}';

    protected $description = 'Verify append-only hash chains for all active/archived vaults.';

    public function __construct(
        private readonly IntegrityVerificationService $verificationService,
        private readonly AuditLogService $auditLogService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $vaultId = $this->option('vault');

        $query = Vault::query()->whereIn('status', [
            VaultStatus::Active,
            VaultStatus::Archived,
        ]);

        if (is_string($vaultId) && $vaultId !== '') {
            $query->where('id', $vaultId);
        }

        $vaults = $query->get();

        if ($vaults->isEmpty()) {
            if (is_string($vaultId) && $vaultId !== '') {
                $this->error(sprintf('Vault %s not found.', $vaultId));

                return self::FAILURE;
            }

            $this->warn('No vaults found for verification.');

            return self::SUCCESS;
        }

        foreach ($vaults as $vault) {
            $startedAt = CarbonImmutable::now();

            $run = ChainVerificationRun::query()->create([
                'vault_id' => $vault->id,
                'started_at' => $startedAt,
                'result' => ChainVerificationResult::Pending,
                'initiated_by_system' => true,
            ]);

            try {
                $result = $this->verificationService->verifyVault($vault);
            } catch (Throwable $exception) {
                // Otherwise the run stays Pending forever and the panel shows it as running.
                report($exception);

                $run->result = ChainVerificationResult::Failed;
                $run->broken_sequence_no = null;
                $run->details_json = ['error' => sprintf('Verification crashed: %s', $exception::class)];
                $run->finished_at = CarbonImmutable::now();
                $run->save();

                $this->auditLogService->integrityVerificationFailed(
                    vault: $vault,
                    brokenSequenceNo: null,
                    error: sprintf('Verification crashed: %s', $exception::class),
                );

                $this->error(sprintf('Vault %s: CRASHED (%s)', $vault->id, $exception::class));

                continue;
            }

            $run->result = $result->result;
            $run->broken_sequence_no = $result->brokenSequenceNo;
            $run->details_json = $result->details;
            $run->finished_at = CarbonImmutable::now();
            $run->save();

            if ($result->result === ChainVerificationResult::Passed) {
                $this->info(sprintf('Vault %s: OK', $vault->id));

                continue;
            }

            $error = $result->error();

            $this->auditLogService->integrityVerificationFailed(
                vault: $vault,
                brokenSequenceNo: $result->brokenSequenceNo,
                error: $error,
            );

            $this->error(sprintf('Vault %s: FAILED at sequence %s', $vault->id, (string) $result->brokenSequenceNo));
        }

        return self::SUCCESS;
    }
}
