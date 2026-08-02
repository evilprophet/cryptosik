<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Services\Integrity;

use EvilStudio\Cryptosik\Models\Vault;
use EvilStudio\Cryptosik\Support\Values\ChainVerificationOutcome;

class IntegrityVerificationService
{
    public function __construct(private readonly EntryHashService $entryHashService) {}

    public function verifyVault(Vault $vault): ChainVerificationOutcome
    {
        // The attachment hash is order-dependent and finalizeDraft builds it in id order.
        $entries = $vault->entries()
            ->with(['attachments' => static fn ($query) => $query->orderBy('id')])
            ->orderBy('sequence_no')
            ->get();

        $expectedSequence = 1;
        $prevHash = null;

        foreach ($entries as $entry) {
            if ($entry->sequence_no !== $expectedSequence) {
                return ChainVerificationOutcome::failed('Invalid sequence continuity.', $entry->sequence_no, [
                    'expected_sequence' => $expectedSequence,
                    'actual_sequence' => $entry->sequence_no,
                ]);
            }

            if ($entry->prev_hash !== $prevHash) {
                return ChainVerificationOutcome::failed('Previous hash mismatch.', $entry->sequence_no, [
                    'expected_prev_hash' => $prevHash,
                    'actual_prev_hash' => $entry->prev_hash,
                ]);
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

            if (! hash_equals($entry->entry_hash, $recalculatedHash)) {
                return ChainVerificationOutcome::failed('Entry hash mismatch.', $entry->sequence_no, [
                    'expected_hash' => $recalculatedHash,
                    'actual_hash' => $entry->entry_hash,
                ]);
            }

            $prevHash = $entry->entry_hash;
            $expectedSequence++;
        }

        $entriesVerified = $expectedSequence - 1;
        $chainState = $vault->chainState()->first();

        if ($chainState === null) {
            return ChainVerificationOutcome::failed('Chain state anchor missing.', $entriesVerified > 0 ? $entriesVerified : null, [
                'entries_verified' => $entriesVerified,
            ]);
        }

        if ($chainState->last_sequence_no !== $entriesVerified) {
            return ChainVerificationOutcome::failed('Chain truncated or state mismatch.', min($entriesVerified, $chainState->last_sequence_no) + 1, [
                'expected_last_sequence' => $chainState->last_sequence_no,
                'actual_last_sequence' => $entriesVerified,
            ]);
        }

        if (! $this->headMatchesAnchor($prevHash, $chainState->last_entry_hash)) {
            return ChainVerificationOutcome::failed('Chain head hash mismatch.', $entriesVerified > 0 ? $entriesVerified : null, [
                'expected_head_hash' => $chainState->last_entry_hash,
                'actual_head_hash' => $prevHash,
            ]);
        }

        return ChainVerificationOutcome::passed($entriesVerified);
    }

    private function headMatchesAnchor(?string $headHash, ?string $anchorHash): bool
    {
        if ($headHash === null || $anchorHash === null) {
            return $headHash === $anchorHash;
        }

        return hash_equals($anchorHash, $headHash);
    }
}
