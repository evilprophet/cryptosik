<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Services\Integrity;

use EvilStudio\Cryptosik\Enums\ChainVerificationResult;
use EvilStudio\Cryptosik\Models\Vault;

class IntegrityVerificationService
{
    public function __construct(private readonly EntryHashService $entryHashService)
    {
    }

    public function verifyVault(Vault $vault): array
    {
        $entries = $vault->entries()->with('attachments')->orderBy('sequence_no')->get();

        $expectedSequence = 1;
        $prevHash = null;

        foreach ($entries as $entry) {
            if ($entry->sequence_no !== $expectedSequence) {
                return [
                    'result' => ChainVerificationResult::Failed,
                    'broken_sequence_no' => $entry->sequence_no,
                    'details' => [
                        'error' => 'Invalid sequence continuity.',
                        'expected_sequence' => $expectedSequence,
                        'actual_sequence' => $entry->sequence_no,
                    ],
                ];
            }

            if ($entry->prev_hash !== $prevHash) {
                return [
                    'result' => ChainVerificationResult::Failed,
                    'broken_sequence_no' => $entry->sequence_no,
                    'details' => [
                        'error' => 'Previous hash mismatch.',
                        'expected_prev_hash' => $prevHash,
                        'actual_prev_hash' => $entry->prev_hash,
                    ],
                ];
            }

            $attachmentHash = $this->entryHashService->makeAttachmentHash(
                $entry->attachments->pluck('blob_enc')->all(),
            );

            $entryDateIso = $entry->entry_date?->toDateString() ?? $entry->finalized_at->toDateString();

            $recalculatedHash = $this->entryHashService->makeEntryHash(
                sequenceNo: $entry->sequence_no,
                prevHash: $entry->prev_hash,
                entryDateIso: $entryDateIso,
                titleEnc: $entry->title_enc,
                contentEnc: $entry->content_enc,
                contentFormat: $entry->content_format->value,
                attachmentHash: $attachmentHash,
                finalizedAtIso: $entry->finalized_at->toIso8601String(),
            );

            if (!hash_equals($entry->entry_hash, $recalculatedHash)) {
                return [
                    'result' => ChainVerificationResult::Failed,
                    'broken_sequence_no' => $entry->sequence_no,
                    'details' => [
                        'error' => 'Entry hash mismatch.',
                        'expected_hash' => $recalculatedHash,
                        'actual_hash' => $entry->entry_hash,
                    ],
                ];
            }

            $prevHash = $entry->entry_hash;
            $expectedSequence++;
        }

        return [
            'result' => ChainVerificationResult::Passed,
            'broken_sequence_no' => null,
            'details' => [
                'entries_verified' => $entries->count(),
            ],
        ];
    }
}
