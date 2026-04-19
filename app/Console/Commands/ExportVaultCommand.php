<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Console\Commands;

use EvilStudio\Cryptosik\Enums\VaultStatus;
use EvilStudio\Cryptosik\Models\Vault;
use Illuminate\Console\Command;
use RuntimeException;

class ExportVaultCommand extends Command
{
    protected $signature = 'cryptosik:export-vault {vault_id} {output_path}';

    protected $description = 'Export a vault to JSONL with encrypted payloads.';

    public function handle(): int
    {
        $vaultId = (string) $this->argument('vault_id');
        $outputPath = (string) $this->argument('output_path');

        $vault = Vault::query()->with(['entries.attachments', 'members', 'crypto'])->find($vaultId);

        if ($vault === null) {
            $this->error('Vault not found.');

            return self::FAILURE;
        }

        if ($vault->status === VaultStatus::SoftDeleted || $vault->deleted_at !== null) {
            $this->error('Export is blocked for soft-deleted vaults.');

            return self::FAILURE;
        }

        if (file_exists($outputPath)) {
            $this->error('Output file already exists. Use a new path.');

            return self::FAILURE;
        }

        $handle = fopen($outputPath, 'wb');

        if ($handle === false) {
            throw new RuntimeException('Unable to open output file for writing.');
        }

        $this->writeJsonLine($handle, [
            'type' => 'vault',
            'vault_id' => $vault->id,
            'owner_user_id' => $vault->owner_user_id,
            'status' => $vault->status->value,
            'name_enc' => $vault->name_enc,
            'description_enc' => $vault->description_enc,
            'vault_crypto' => [
                'vault_locator' => $vault->crypto?->vault_locator,
                'kdf_salt' => $vault->crypto?->kdf_salt,
                'kdf_params' => $vault->crypto?->kdf_params,
                'wrapped_data_key' => $vault->crypto?->wrapped_data_key,
                'wrap_nonce' => $vault->crypto?->wrap_nonce,
                'key_fingerprint' => $vault->crypto?->key_fingerprint,
            ],
            'members' => $vault->members->map(fn ($member): array => [
                'user_id' => $member->user_id,
                'role' => $member->role->value,
            ])->values()->all(),
        ]);

        foreach ($vault->entries as $entry) {
            $this->writeJsonLine($handle, [
                'type' => 'entry',
                'vault_id' => $entry->vault_id,
                'entry_id' => $entry->id,
                'sequence_no' => $entry->sequence_no,
                'title_enc' => $entry->title_enc,
                'content_enc' => $entry->content_enc,
                'content_format' => $entry->content_format->value,
                'prev_hash' => $entry->prev_hash,
                'entry_hash' => $entry->entry_hash,
                'attachment_hash' => $entry->attachment_hash,
                'created_by' => $entry->created_by,
                'finalized_at' => $entry->finalized_at->toIso8601String(),
            ]);

            foreach ($entry->attachments as $attachment) {
                $this->writeJsonLine($handle, [
                    'type' => 'attachment',
                    'entry_id' => $entry->id,
                    'attachment_id' => $attachment->id,
                    'filename_enc' => $attachment->filename_enc,
                    'mime_enc' => $attachment->mime_enc,
                    'size_bytes' => $attachment->size_bytes,
                    'blob_enc' => $attachment->blob_enc,
                    'blob_nonce' => $attachment->blob_nonce,
                ]);
            }
        }

        fclose($handle);

        $this->info(sprintf('Vault export completed: %s', $outputPath));

        return self::SUCCESS;
    }

    private function writeJsonLine($handle, array $payload): void
    {
        fwrite($handle, json_encode($payload, JSON_THROW_ON_ERROR).PHP_EOL);
    }
}
