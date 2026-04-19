<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Integrity;

use EvilStudio\Cryptosik\Enums\ChainVerificationResult;
use EvilStudio\Cryptosik\Enums\EntryContentFormat;
use EvilStudio\Cryptosik\Enums\VaultStatus;
use EvilStudio\Cryptosik\Models\Entry;
use EvilStudio\Cryptosik\Models\EntryAttachment;
use EvilStudio\Cryptosik\Models\User;
use EvilStudio\Cryptosik\Models\Vault;
use EvilStudio\Cryptosik\Services\Integrity\EntryHashService;
use EvilStudio\Cryptosik\Services\Integrity\IntegrityVerificationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrityVerificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_verify_vault_passes_for_empty_history(): void
    {
        [$owner, $vault] = $this->createOwnerAndVault();

        $result = app(IntegrityVerificationService::class)->verifyVault($vault);

        $this->assertSame(ChainVerificationResult::Passed, $result['result']);
        $this->assertNull($result['broken_sequence_no']);
        $this->assertSame(0, $result['details']['entries_verified'] ?? null);

        $this->assertNotNull($owner);
    }

    public function test_verify_vault_passes_for_valid_chain(): void
    {
        [$owner, $vault] = $this->createOwnerAndVault();

        $first = $this->createEntry(
            vault: $vault,
            owner: $owner,
            sequenceNo: 1,
            prevHash: null,
            entryDateIso: '2026-04-19',
            blobs: ['blob-a'],
            keepValidHash: true,
        );

        $this->createEntry(
            vault: $vault,
            owner: $owner,
            sequenceNo: 2,
            prevHash: $first->entry_hash,
            entryDateIso: '2026-04-19',
            blobs: ['blob-b', 'blob-c'],
            keepValidHash: true,
        );

        $result = app(IntegrityVerificationService::class)->verifyVault($vault->fresh());

        $this->assertSame(ChainVerificationResult::Passed, $result['result']);
        $this->assertNull($result['broken_sequence_no']);
        $this->assertSame(2, $result['details']['entries_verified'] ?? null);
    }

    public function test_verify_vault_fails_on_sequence_gap(): void
    {
        [$owner, $vault] = $this->createOwnerAndVault();

        $this->createEntry(
            vault: $vault,
            owner: $owner,
            sequenceNo: 1,
            prevHash: null,
            entryDateIso: '2026-04-19',
            blobs: [],
            keepValidHash: true,
        );

        $this->createEntry(
            vault: $vault,
            owner: $owner,
            sequenceNo: 3,
            prevHash: 'does-not-matter-for-sequence-gap',
            entryDateIso: '2026-04-19',
            blobs: [],
            keepValidHash: true,
        );

        $result = app(IntegrityVerificationService::class)->verifyVault($vault->fresh());

        $this->assertSame(ChainVerificationResult::Failed, $result['result']);
        $this->assertSame(3, $result['broken_sequence_no']);
        $this->assertSame('Invalid sequence continuity.', $result['details']['error'] ?? null);
        $this->assertSame(2, $result['details']['expected_sequence'] ?? null);
        $this->assertSame(3, $result['details']['actual_sequence'] ?? null);
    }

    public function test_verify_vault_fails_on_previous_hash_mismatch(): void
    {
        [$owner, $vault] = $this->createOwnerAndVault();

        $first = $this->createEntry(
            vault: $vault,
            owner: $owner,
            sequenceNo: 1,
            prevHash: null,
            entryDateIso: '2026-04-19',
            blobs: [],
            keepValidHash: true,
        );

        $this->assertNotNull($first);

        $this->createEntry(
            vault: $vault,
            owner: $owner,
            sequenceNo: 2,
            prevHash: 'wrong-prev-hash',
            entryDateIso: '2026-04-19',
            blobs: [],
            keepValidHash: true,
        );

        $result = app(IntegrityVerificationService::class)->verifyVault($vault->fresh());

        $this->assertSame(ChainVerificationResult::Failed, $result['result']);
        $this->assertSame(2, $result['broken_sequence_no']);
        $this->assertSame('Previous hash mismatch.', $result['details']['error'] ?? null);
    }

    public function test_verify_vault_fails_on_entry_hash_mismatch(): void
    {
        [$owner, $vault] = $this->createOwnerAndVault();

        $first = $this->createEntry(
            vault: $vault,
            owner: $owner,
            sequenceNo: 1,
            prevHash: null,
            entryDateIso: '2026-04-19',
            blobs: ['blob-a'],
            keepValidHash: true,
        );

        $this->createEntry(
            vault: $vault,
            owner: $owner,
            sequenceNo: 2,
            prevHash: $first->entry_hash,
            entryDateIso: '2026-04-19',
            blobs: ['blob-b'],
            keepValidHash: false,
        );

        $result = app(IntegrityVerificationService::class)->verifyVault($vault->fresh());

        $this->assertSame(ChainVerificationResult::Failed, $result['result']);
        $this->assertSame(2, $result['broken_sequence_no']);
        $this->assertSame('Entry hash mismatch.', $result['details']['error'] ?? null);
    }

    /**
     * @return array{User, Vault}
     */
    private function createOwnerAndVault(): array
    {
        $owner = User::query()->create([
            'email' => sprintf('owner-%s@example.com', uniqid('', true)),
            'nickname' => 'Owner',
            'locale' => 'en',
            'is_active' => true,
        ]);

        $vault = Vault::query()->create([
            'owner_user_id' => $owner->id,
            'name_enc' => 'enc-vault-name',
            'description_enc' => 'enc-vault-description',
            'status' => VaultStatus::Active,
        ]);

        return [$owner, $vault];
    }

    /**
     * @param list<string> $blobs
     */
    private function createEntry(
        Vault $vault,
        User $owner,
        int $sequenceNo,
        ?string $prevHash,
        string $entryDateIso,
        array $blobs,
        bool $keepValidHash,
    ): Entry {
        $entryHashService = app(EntryHashService::class);
        $finalizedAt = CarbonImmutable::now()->addSeconds($sequenceNo);
        $attachmentHash = $entryHashService->makeAttachmentHash($blobs);

        $entryHash = $entryHashService->makeEntryHash(
            sequenceNo: $sequenceNo,
            prevHash: $prevHash,
            entryDateIso: $entryDateIso,
            titleEnc: sprintf('title-enc-%d', $sequenceNo),
            contentEnc: sprintf('content-enc-%d', $sequenceNo),
            contentFormat: EntryContentFormat::Markdown->value,
            attachmentHash: $attachmentHash,
            finalizedAtIso: $finalizedAt->toIso8601String(),
        );

        if (!$keepValidHash) {
            $entryHash = hash('sha256', sprintf('broken-entry-hash-%d', $sequenceNo));
        }

        $entry = Entry::query()->create([
            'vault_id' => $vault->id,
            'sequence_no' => $sequenceNo,
            'entry_date' => $entryDateIso,
            'title_enc' => sprintf('title-enc-%d', $sequenceNo),
            'content_enc' => sprintf('content-enc-%d', $sequenceNo),
            'content_format' => EntryContentFormat::Markdown,
            'prev_hash' => $prevHash,
            'entry_hash' => $entryHash,
            'attachment_hash' => $attachmentHash,
            'created_by' => $owner->id,
            'finalized_at' => $finalizedAt,
        ]);

        foreach ($blobs as $index => $blob) {
            EntryAttachment::query()->create([
                'entry_id' => $entry->id,
                'filename_enc' => sprintf('file-%d-%d.enc', $sequenceNo, $index),
                'mime_enc' => 'text/plain',
                'size_bytes' => strlen($blob),
                'blob_enc' => $blob,
                'blob_nonce' => sprintf('nonce-%d-%d', $sequenceNo, $index),
            ]);
        }

        return $entry;
    }
}
