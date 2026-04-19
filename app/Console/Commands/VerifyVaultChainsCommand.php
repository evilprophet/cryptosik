<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Console\Commands;

use EvilStudio\Cryptosik\Enums\ChainVerificationResult;
use EvilStudio\Cryptosik\Enums\VaultStatus;
use EvilStudio\Cryptosik\Models\ChainVerificationRun;
use EvilStudio\Cryptosik\Models\Vault;
use EvilStudio\Cryptosik\Services\Audit\AuditLogService;
use EvilStudio\Cryptosik\Services\Integrity\IntegrityVerificationService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

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

            $result = $this->verificationService->verifyVault($vault);

            $run->result = $result['result'];
            $run->broken_sequence_no = $result['broken_sequence_no'];
            $run->details_json = $result['details'];
            $run->finished_at = CarbonImmutable::now();
            $run->save();

            if ($result['result'] === ChainVerificationResult::Passed) {
                $this->info(sprintf('Vault %s: OK', $vault->id));
                continue;
            }

            $error = (string) ($result['details']['error'] ?? 'Unknown integrity verification error.');

            $this->auditLogService->integrityVerificationFailed(
                vault: $vault,
                brokenSequenceNo: is_int($result['broken_sequence_no']) ? $result['broken_sequence_no'] : null,
                error: $error,
            );

            $this->error(sprintf('Vault %s: FAILED at sequence %s', $vault->id, (string) $result['broken_sequence_no']));
        }

        return self::SUCCESS;
    }
}
