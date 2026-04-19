<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Services\Integrity;

use JsonException;

class EntryHashService
{
    public function makeEntryHash(
        int $sequenceNo,
        ?string $prevHash,
        string $entryDateIso,
        string $titleEnc,
        string $contentEnc,
        string $contentFormat,
        ?string $attachmentHash,
        string $finalizedAtIso,
    ): string {
        try {
            $payload = json_encode([
                'sequence_no' => $sequenceNo,
                'prev_hash' => $prevHash,
                'entry_date' => $entryDateIso,
                'title_enc' => $titleEnc,
                'content_enc' => $contentEnc,
                'content_format' => $contentFormat,
                'attachment_hash' => $attachmentHash,
                'finalized_at' => $finalizedAtIso,
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \RuntimeException('Unable to encode entry hash payload.', previous: $exception);
        }

        return hash('sha256', $payload);
    }

    public function makeAttachmentHash(array $encryptedAttachmentBlobs): ?string
    {
        if ($encryptedAttachmentBlobs === []) {
            return null;
        }

        $digestPayload = implode('|', array_map(static fn (string $blob): string => hash('sha256', $blob), $encryptedAttachmentBlobs));

        return hash('sha256', $digestPayload);
    }
}
