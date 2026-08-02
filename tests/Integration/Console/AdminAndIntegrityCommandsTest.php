<?php

declare(strict_types=1);

namespace Tests\Integration\Console;

use EvilStudio\Cryptosik\Enums\ChainVerificationResult;
use EvilStudio\Cryptosik\Enums\VaultStatus;
use EvilStudio\Cryptosik\Models\Admin;
use EvilStudio\Cryptosik\Models\AuditLog;
use EvilStudio\Cryptosik\Models\ChainVerificationRun;
use EvilStudio\Cryptosik\Models\User;
use EvilStudio\Cryptosik\Models\Vault;
use EvilStudio\Cryptosik\Services\Integrity\IntegrityVerificationService;
use EvilStudio\Cryptosik\Services\Vault\EntryService;
use EvilStudio\Cryptosik\Services\Vault\VaultAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Tests\TestCase;

class AdminAndIntegrityCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_admin_command_supports_prompted_password_and_persists_hash(): void
    {
        $this->artisan('cryptosik:admin-create', [
            'login' => 'ops-root',
        ])
            ->expectsQuestion('Admin password', 'top-secret-password')
            ->expectsOutput('Admin account created successfully.')
            ->assertSuccessful();

        $admin = Admin::query()->where('login', 'ops-root')->first();

        $this->assertNotNull($admin);
        $this->assertTrue(Hash::check('top-secret-password', (string) $admin?->password_hash));
    }

    public function test_create_admin_command_rejects_duplicate_login(): void
    {
        Admin::query()->create([
            'login' => 'ops-root',
            'password_hash' => Hash::make('existing-password'),
            'is_active' => true,
        ]);

        $this->artisan('cryptosik:admin-create', [
            'login' => 'ops-root',
            'password' => 'new-password',
        ])
            ->expectsOutput('Admin with this login already exists.')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_create_admin_command_rejects_empty_password_argument(): void
    {
        $this->artisan('cryptosik:admin-create', [
            'login' => 'ops-root',
            'password' => '',
        ])
            ->expectsOutput('Password is required.')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_export_vault_command_handles_not_found_and_blocked_states(): void
    {
        $notFoundPath = sprintf('/tmp/cryptosik-export-not-found-%s.jsonl', uniqid('', true));

        $this->artisan('cryptosik:export-vault', [
            'vault_id' => '00000000-0000-0000-0000-000000000000',
            'output_path' => $notFoundPath,
        ])
            ->expectsOutput('Vault not found.')
            ->assertExitCode(Command::FAILURE);

        $blockedOwner = User::query()->create([
            'email' => sprintf('blocked-owner-%s@example.com', uniqid('', true)),
            'nickname' => 'BlockedOwner',
            'locale' => 'en',
            'is_active' => true,
        ]);

        $blockedVault = Vault::query()->create([
            'owner_user_id' => $blockedOwner->id,
            'name_enc' => 'blocked-vault-name',
            'description_enc' => null,
            'status' => VaultStatus::SoftDeleted,
        ]);

        $blockedPath = sprintf('/tmp/cryptosik-export-blocked-%s.jsonl', uniqid('', true));

        $this->artisan('cryptosik:export-vault', [
            'vault_id' => $blockedVault->id,
            'output_path' => $blockedPath,
        ])->assertExitCode(Command::FAILURE);

        $this->assertFileDoesNotExist($blockedPath);
    }

    public function test_export_vault_command_rejects_existing_output_file_and_exports_jsonl_on_success(): void
    {
        $fixture = $this->createVaultWithEntry('export-success-key-001');
        $vault = $fixture['vault'];

        $existingPath = sprintf('/tmp/cryptosik-export-existing-%s.jsonl', uniqid('', true));
        file_put_contents($existingPath, "already exists\n");

        $this->artisan('cryptosik:export-vault', [
            'vault_id' => $vault->id,
            'output_path' => $existingPath,
        ])
            ->expectsOutput('Output file already exists. Use a new path.')
            ->assertExitCode(Command::FAILURE);

        @unlink($existingPath);

        $outputPath = sprintf('/tmp/cryptosik-export-ok-%s.jsonl', uniqid('', true));

        $this->artisan('cryptosik:export-vault', [
            'vault_id' => $vault->id,
            'output_path' => $outputPath,
        ])
            ->expectsOutput(sprintf('Vault export completed: %s', $outputPath))
            ->assertSuccessful();

        $this->assertFileExists($outputPath);

        $lines = file($outputPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->assertNotFalse($lines);
        $this->assertGreaterThanOrEqual(3, count($lines));

        $records = array_map(
            static fn (string $line): array => json_decode($line, true, 512, JSON_THROW_ON_ERROR),
            $lines,
        );

        $types = array_column($records, 'type');

        $this->assertContains('vault', $types);
        $this->assertContains('entry', $types);
        $this->assertContains('attachment', $types);
        $this->assertSame($vault->id, $records[0]['vault_id']);

        @unlink($outputPath);
    }

    public function test_verify_chains_command_handles_empty_scope_pass_and_failure(): void
    {
        $this->artisan('cryptosik:verify-chains')
            ->expectsOutput('No vaults found for verification.')
            ->assertSuccessful();

        $passFixture = $this->createVaultWithEntry('verify-pass-key-001');
        $passVault = $passFixture['vault'];

        $this->artisan('cryptosik:verify-chains', [
            '--vault' => $passVault->id,
        ])
            ->expectsOutput(sprintf('Vault %s: OK', $passVault->id))
            ->assertSuccessful();

        $this->assertDatabaseHas('chain_verification_runs', [
            'vault_id' => $passVault->id,
            'result' => ChainVerificationResult::Passed->value,
            'initiated_by_system' => true,
        ]);

        $failFixture = $this->createVaultWithEntry('verify-fail-key-001', true);
        $failVault = $failFixture['vault'];

        $this->artisan('cryptosik:verify-chains', [
            '--vault' => $failVault->id,
        ])
            ->expectsOutput(sprintf('Vault %s: FAILED at sequence 1', $failVault->id))
            ->assertSuccessful();

        $this->assertDatabaseHas('chain_verification_runs', [
            'vault_id' => $failVault->id,
            'result' => ChainVerificationResult::Failed->value,
            'broken_sequence_no' => 1,
            'initiated_by_system' => true,
        ]);

        $failedIntegrityAuditLog = AuditLog::query()
            ->where('action', 'integrity.chain.failed')
            ->where('target_type', 'vault')
            ->where('target_id', (string) $failVault->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($failedIntegrityAuditLog);
        $this->assertSame('system', $failedIntegrityAuditLog?->actor_type);
        $this->assertSame(0, $failedIntegrityAuditLog?->actor_id);
        $this->assertSame(1, $failedIntegrityAuditLog?->metadata_json['broken_sequence_no'] ?? null);

        $errorMessage = $failedIntegrityAuditLog?->metadata_json['error'] ?? null;
        $this->assertIsString($errorMessage);
        $this->assertNotSame('', trim((string) $errorMessage));
    }

    public function test_prune_audit_logs_command_respects_configured_retention(): void
    {
        config(['cryptosik.audit_logs.retention_days' => '30']);

        AuditLog::query()->create([
            'actor_type' => 'system',
            'actor_id' => 0,
            'action' => 'old.audit.event',
            'target_type' => 'system',
            'target_id' => null,
            'metadata_json' => null,
            'created_at' => Carbon::now()->subDays(31),
        ]);

        AuditLog::query()->create([
            'actor_type' => 'system',
            'actor_id' => 0,
            'action' => 'fresh.audit.event',
            'target_type' => 'system',
            'target_id' => null,
            'metadata_json' => null,
            'created_at' => Carbon::now()->subDays(29),
        ]);

        $this->artisan('cryptosik:audit-logs-prune')
            ->expectsOutput('Audit log pruning completed: 1 deleted.')
            ->assertSuccessful();

        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'old.audit.event',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'fresh.audit.event',
        ]);
    }

    public function test_prune_audit_logs_command_deletes_a_backlog_larger_than_one_chunk(): void
    {
        config(['cryptosik.audit_logs.retention_days' => '30']);

        $stale = Carbon::now()->subDays(45);
        $rows = [];

        // One row past the 1000-row chunk size, so the loop has to run more than once.
        for ($i = 0; $i < 1001; $i++) {
            $rows[] = [
                'actor_type' => 'system',
                'actor_id' => 0,
                'action' => 'bulk.audit.event',
                'target_type' => 'system',
                'target_id' => null,
                'metadata_json' => null,
                'created_at' => $stale,
            ];
        }

        AuditLog::query()->insert($rows);

        AuditLog::query()->create([
            'actor_type' => 'system',
            'actor_id' => 0,
            'action' => 'kept.audit.event',
            'target_type' => 'system',
            'target_id' => null,
            'metadata_json' => null,
            'created_at' => Carbon::now()->subDays(2),
        ]);

        $deleteStatements = [];

        DB::listen(function ($query) use (&$deleteStatements): void {
            $sql = strtolower(ltrim($query->sql));

            if (str_starts_with($sql, 'delete')) {
                $deleteStatements[] = $sql;
            }
        });

        $this->artisan('cryptosik:audit-logs-prune')
            ->expectsOutput('Audit log pruning completed: 1001 deleted.')
            ->assertSuccessful();

        $this->assertSame(0, AuditLog::query()->where('action', 'bulk.audit.event')->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'kept.audit.event')->count());

        // An unbounded DELETE removes exactly the same rows, so counting them proves nothing -
        // what matters is that every statement is bounded, keeping each write lock short.
        $this->assertNotSame([], $deleteStatements);

        foreach ($deleteStatements as $sql) {
            $this->assertStringContainsString(
                'limit',
                $sql,
                'Every prune statement must be bounded: '.$sql,
            );
        }
    }

    public function test_prune_audit_logs_command_skips_never_and_rejects_invalid_retention(): void
    {
        config(['cryptosik.audit_logs.retention_days' => 'never']);

        AuditLog::query()->create([
            'actor_type' => 'system',
            'actor_id' => 0,
            'action' => 'kept.audit.event',
            'target_type' => 'system',
            'target_id' => null,
            'metadata_json' => null,
            'created_at' => Carbon::now()->subDays(120),
        ]);

        $this->artisan('cryptosik:audit-logs-prune')
            ->expectsOutput('Audit log pruning skipped: retention is never.')
            ->assertSuccessful();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'kept.audit.event',
        ]);

        // A non-numeric value is still a hard error - there is nothing sensible to narrow to.
        $this->artisan('cryptosik:audit-logs-prune', [
            '--retention' => 'forever',
        ])
            ->expectsOutput('Invalid audit log retention. Allowed values: never, 30, 60, 90, 120.')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_verify_chains_command_reports_an_unknown_vault_id(): void
    {
        $this->createVaultWithEntry('unknown-id-probe-key-001');

        $this->artisan('cryptosik:verify-chains', [
            '--vault' => '00000000-0000-0000-0000-000000000000',
        ])
            ->expectsOutput('Vault 00000000-0000-0000-0000-000000000000 not found.')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_verify_chains_command_records_a_crashed_verification_instead_of_leaving_it_pending(): void
    {
        $fixture = $this->createVaultWithEntry('crash-probe-key-001');
        $vault = $fixture['vault'];

        $this->mock(
            IntegrityVerificationService::class,
            static fn ($mock) => $mock->shouldReceive('verifyVault')->andThrow(new RuntimeException('boom')),
        );

        $this->artisan('cryptosik:verify-chains', ['--vault' => $vault->id])
            ->assertSuccessful();

        $run = ChainVerificationRun::query()->where('vault_id', $vault->id)->latest('id')->first();

        $this->assertNotNull($run);
        $this->assertSame(
            ChainVerificationResult::Failed,
            $run?->result,
            'A crashed run must not stay Pending - the admin panel would show it as still running.',
        );
        $this->assertNotNull($run?->finished_at);
        $this->assertStringContainsString('Verification crashed', (string) ($run?->details_json['error'] ?? ''));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'integrity.chain.failed',
            'target_id' => (string) $vault->id,
        ]);
    }

    public function test_prune_audit_logs_command_narrows_a_retention_outside_the_whitelist(): void
    {
        AuditLog::query()->create([
            'actor_type' => 'system',
            'actor_id' => 0,
            'action' => 'inside.retention.event',
            'target_type' => 'system',
            'target_id' => null,
            'metadata_json' => null,
            'created_at' => Carbon::now()->subDays(40),
        ]);

        AuditLog::query()->create([
            'actor_type' => 'system',
            'actor_id' => 0,
            'action' => 'outside.retention.event',
            'target_type' => 'system',
            'target_id' => null,
            'metadata_json' => null,
            'created_at' => Carbon::now()->subDays(80),
        ]);

        // 45 sits exactly between 30 and 60, and a tie must resolve to the longer retention so
        // narrowing never deletes more than asked. The 40-day entry surviving proves it.
        $this->artisan('cryptosik:audit-logs-prune', [
            '--retention' => '45',
        ])
            ->expectsOutput('Retention 45 is not one of 30, 60, 90, 120; using 60 instead.')
            ->expectsOutput('Audit log pruning completed: 1 deleted.')
            ->assertSuccessful();

        $this->assertSame(['inside.retention.event'], AuditLog::query()->pluck('action')->all());
    }

    /**
     * @return array{vault: Vault, owner: User}
     */
    private function createVaultWithEntry(string $vaultKey, bool $tamperEntryHash = false): array
    {
        $owner = User::query()->create([
            'email' => sprintf('owner-%s@example.com', uniqid('', true)),
            'nickname' => 'Owner',
            'locale' => 'en',
            'is_active' => true,
        ]);

        $vaultAccessService = app(VaultAccessService::class);
        $entryService = app(EntryService::class);

        $vault = $vaultAccessService->createVault(
            owner: $owner,
            vaultKey: $vaultKey,
            name: 'Command fixture vault',
            description: 'Fixture description',
        );

        $unlockResult = $vaultAccessService->unlockVaultForUser($owner, $vaultKey);

        $this->assertNotNull($unlockResult);

        $dataKey = $unlockResult->dataKey;
        $this->assertNotSame('', $dataKey);

        $entryService->upsertDraft(
            user: $owner,
            vault: $vault,
            dataKey: $dataKey,
            entryDate: now()->toDateString(),
            title: 'Export fixture title',
            content: 'Export fixture content',
        );

        $entryService->addDraftAttachment(
            user: $owner,
            vault: $vault,
            dataKey: $dataKey,
            file: UploadedFile::fake()->createWithContent('fixture.txt', 'fixture-content'),
        );

        $entry = $entryService->finalizeDraft($owner, $vault);

        if ($tamperEntryHash) {
            $entry->entry_hash = hash('sha256', 'tampered-'.$entry->id);
            $entry->save();
        }

        return [
            'vault' => $vault,
            'owner' => $owner,
        ];
    }
}
