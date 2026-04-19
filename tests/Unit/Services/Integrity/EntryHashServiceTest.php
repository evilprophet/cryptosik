<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Integrity;

use EvilStudio\Cryptosik\Services\Integrity\EntryHashService;
use Tests\TestCase;

class EntryHashServiceTest extends TestCase
{
    public function test_attachment_hash_is_null_for_empty_payload(): void
    {
        $service = new EntryHashService();

        $this->assertNull($service->makeAttachmentHash([]));
    }

    public function test_attachment_hash_is_stable_for_same_payload(): void
    {
        $service = new EntryHashService();
        $payload = ['blob-a', 'blob-b'];

        $first = $service->makeAttachmentHash($payload);
        $second = $service->makeAttachmentHash($payload);

        $this->assertSame($first, $second);
    }

    public function test_entry_hash_changes_when_payload_changes(): void
    {
        $service = new EntryHashService();

        $base = $service->makeEntryHash(
            sequenceNo: 1,
            prevHash: null,
            entryDateIso: '2026-04-13',
            titleEnc: 'title-enc-a',
            contentEnc: 'content-enc-a',
            contentFormat: 'markdown',
            attachmentHash: null,
            finalizedAtIso: '2026-04-11T12:00:00+00:00',
        );

        $changed = $service->makeEntryHash(
            sequenceNo: 1,
            prevHash: null,
            entryDateIso: '2026-04-13',
            titleEnc: 'title-enc-b',
            contentEnc: 'content-enc-a',
            contentFormat: 'markdown',
            attachmentHash: null,
            finalizedAtIso: '2026-04-11T12:00:00+00:00',
        );

        $this->assertNotSame($base, $changed);
    }
}
