<?php

declare(strict_types=1);

namespace Tests\Integration\Vault;

use EvilStudio\Cryptosik\Enums\ChainVerificationResult;
use EvilStudio\Cryptosik\Enums\EntryContentFormat;
use EvilStudio\Cryptosik\Models\Entry;
use EvilStudio\Cryptosik\Models\User;
use EvilStudio\Cryptosik\Models\Vault;
use EvilStudio\Cryptosik\Models\VaultChainState;
use EvilStudio\Cryptosik\Services\Integrity\IntegrityVerificationService;
use EvilStudio\Cryptosik\Services\Vault\EntryService;
use EvilStudio\Cryptosik\Services\Vault\VaultAccessService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Tests\TestCase;

class EntryChainLinkingTest extends TestCase
{
    use RefreshDatabase;

    private const VAULT_KEY = 'chain-linking-vault-key-001';

    private User $owner;

    private Vault $vault;

    private string $dataKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::query()->create([
            'email' => 'owner@example.com',
            'nickname' => 'Owner',
            'locale' => 'en',
            'is_active' => true,
        ]);

        $vaultAccessService = app(VaultAccessService::class);
        $this->vault = $vaultAccessService->createVault($this->owner, self::VAULT_KEY, 'Chain Vault', null);

        $unlockResult = $vaultAccessService->unlockVaultForUser($this->owner, self::VAULT_KEY);

        if ($unlockResult === null) {
            throw new RuntimeException('Unable to prepare unlocked vault fixture.');
        }

        $this->dataKey = $unlockResult->dataKey;
    }

    public function test_three_finalized_entries_form_a_linked_chain_and_advance_the_anchor(): void
    {
        $first = $this->finalizeEntry('Wpis pierwszy', 'Tresc pierwsza');
        $second = $this->finalizeEntry('Wpis drugi', 'Tresc druga', withAttachment: true);
        $third = $this->finalizeEntry('Wpis trzeci', 'Tresc trzecia');

        $this->assertSame(1, $first->sequence_no);
        $this->assertSame(2, $second->sequence_no);
        $this->assertSame(3, $third->sequence_no);

        $this->assertNull($first->prev_hash, 'The first entry opens the chain.');
        $this->assertSame($first->entry_hash, $second->prev_hash);
        $this->assertSame($second->entry_hash, $third->prev_hash);

        $this->assertNotSame($first->entry_hash, $second->entry_hash);
        $this->assertNotSame($second->entry_hash, $third->entry_hash);

        $chainState = VaultChainState::query()->find($this->vault->id);

        $this->assertSame(3, $chainState?->last_sequence_no);
        $this->assertSame($third->entry_hash, $chainState?->last_entry_hash);
    }

    public function test_a_chain_built_through_finalize_passes_integrity_verification(): void
    {
        $this->finalizeEntry('Wpis pierwszy', 'Tresc pierwsza');
        $this->finalizeEntry('Wpis drugi', 'Tresc druga', withAttachment: true);
        $this->finalizeEntry('Wpis trzeci', 'Tresc trzecia');

        $result = app(IntegrityVerificationService::class)->verifyVault($this->vault->fresh());

        $this->assertSame(ChainVerificationResult::Passed, $result->result);
        $this->assertSame(3, $result->details['entries_verified'] ?? null);
    }

    public function test_truncating_the_chain_built_through_finalize_is_detected(): void
    {
        $this->finalizeEntry('Wpis pierwszy', 'Tresc pierwsza');
        $this->finalizeEntry('Wpis drugi', 'Tresc druga');
        $third = $this->finalizeEntry('Wpis trzeci', 'Tresc trzecia');

        $third->attachments()->delete();
        $third->delete();

        $result = app(IntegrityVerificationService::class)->verifyVault($this->vault->fresh());

        $this->assertSame(ChainVerificationResult::Failed, $result->result);
        $this->assertSame('Chain truncated or state mismatch.', $result->details['error'] ?? null);
        $this->assertSame(3, $result->brokenSequenceNo);
    }

    public function test_an_entry_with_several_attachments_verifies_regardless_of_row_order(): void
    {
        $entryService = app(EntryService::class);

        $entryService->upsertDraft(
            user: $this->owner,
            vault: $this->vault,
            dataKey: $this->dataKey,
            entryDate: '2026-04-13',
            title: 'Wpis z zalacznikami',
            content: 'Tresc',
        );

        foreach (['alpha', 'beta', 'gamma', 'delta'] as $name) {
            $entryService->addDraftAttachment(
                user: $this->owner,
                vault: $this->vault,
                dataKey: $this->dataKey,
                file: UploadedFile::fake()->createWithContent($name.'.txt', 'content of '.$name),
            );
        }

        $entry = $entryService->finalizeDraft($this->owner, $this->vault);

        $this->assertSame(4, $entry->attachments()->count());

        $result = app(IntegrityVerificationService::class)->verifyVault($this->vault->fresh());

        $this->assertSame(
            ChainVerificationResult::Passed,
            $result->result,
            'The attachment hash must not depend on the order the database happens to return rows in.',
        );
    }

    public function test_the_attachment_limit_is_enforced_per_draft(): void
    {
        config(['cryptosik.limits.attachments_per_entry' => 2]);

        $entryService = app(EntryService::class);

        foreach (['one', 'two'] as $name) {
            $entryService->addDraftAttachment(
                user: $this->owner,
                vault: $this->vault,
                dataKey: $this->dataKey,
                file: UploadedFile::fake()->createWithContent($name.'.txt', $name),
            );
        }

        try {
            $entryService->addDraftAttachment(
                user: $this->owner,
                vault: $this->vault,
                dataKey: $this->dataKey,
                file: UploadedFile::fake()->createWithContent('three.txt', 'three'),
            );

            $this->fail('The third attachment should have been rejected.');
        } catch (RuntimeException) {
            // expected
        }

        // The rejected insert must be rolled back, not left behind.
        $draft = $this->vault->drafts()->where('user_id', $this->owner->id)->first();

        $this->assertSame(2, $draft?->attachments()->count());
    }

    public function test_the_database_rejects_a_duplicate_sequence_number(): void
    {
        $first = $this->finalizeEntry('Wpis pierwszy', 'Tresc pierwsza');

        $this->expectException(QueryException::class);

        Entry::query()->create([
            'vault_id' => $this->vault->id,
            'sequence_no' => $first->sequence_no,
            'entry_date' => '2026-04-13',
            'title_enc' => 'duplicate-title',
            'content_enc' => 'duplicate-content',
            'content_format' => EntryContentFormat::Markdown,
            'prev_hash' => null,
            'entry_hash' => hash('sha256', 'duplicate'),
            'attachment_hash' => hash('sha256', ''),
            'created_by' => $this->owner->id,
            'finalized_at' => now(),
        ]);
    }

    private function finalizeEntry(string $title, string $content, bool $withAttachment = false): Entry
    {
        $entryService = app(EntryService::class);

        $entryService->upsertDraft(
            user: $this->owner,
            vault: $this->vault,
            dataKey: $this->dataKey,
            entryDate: '2026-04-13',
            title: $title,
            content: $content,
        );

        if ($withAttachment) {
            $entryService->addDraftAttachment(
                user: $this->owner,
                vault: $this->vault,
                dataKey: $this->dataKey,
                file: UploadedFile::fake()->createWithContent('chain.txt', 'chain fixture content'),
            );
        }

        return $entryService->finalizeDraft($this->owner, $this->vault);
    }
}
