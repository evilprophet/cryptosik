<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Services\Vault;

use EvilStudio\Cryptosik\Models\DraftAttachment;
use EvilStudio\Cryptosik\Models\Entry;
use EvilStudio\Cryptosik\Models\EntryAttachment;
use EvilStudio\Cryptosik\Models\EntryDraft;
use EvilStudio\Cryptosik\Models\User;
use EvilStudio\Cryptosik\Models\Vault;
use EvilStudio\Cryptosik\Models\VaultChainState;
use EvilStudio\Cryptosik\Services\Crypto\CryptoService;
use EvilStudio\Cryptosik\Services\Integrity\EntryHashService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EntryService
{
    public const DEFAULT_DRAFT_TITLE = 'Untitled draft';

    public function __construct(
        private readonly CryptoService $cryptoService,
        private readonly EntryHashService $entryHashService,
    ) {
    }

    public function upsertDraft(User $user, Vault $vault, string $dataKey, string $entryDate, string $title, string $content): EntryDraft
    {
        $draft = EntryDraft::query()->firstOrNew([
            'vault_id' => $vault->id,
            'user_id' => $user->id,
        ]);

        $draft->entry_date = $entryDate;
        $draft->title_enc = $this->cryptoService->encryptEnvelope($title, $dataKey);
        $draft->content_enc = $this->cryptoService->encryptEnvelope($content, $dataKey);
        $draft->save();

        return $draft->refresh();
    }

    public function deleteDraft(User $user, Vault $vault): void
    {
        $draft = EntryDraft::query()
            ->where('vault_id', $vault->id)
            ->where('user_id', $user->id)
            ->first();

        if ($draft === null) {
            return;
        }

        $draft->attachments()->delete();
        $draft->delete();
    }

    public function addDraftAttachment(User $user, Vault $vault, string $dataKey, UploadedFile $file): DraftAttachment
    {
        $draft = EntryDraft::query()->firstOrCreate([
            'vault_id' => $vault->id,
            'user_id' => $user->id,
        ], [
            'entry_date' => CarbonImmutable::now()->toDateString(),
            'title_enc' => $this->cryptoService->encryptEnvelope(self::DEFAULT_DRAFT_TITLE, $dataKey),
            'content_enc' => $this->cryptoService->encryptEnvelope('', $dataKey),
        ]);

        $currentCount = $draft->attachments()->count();
        $maxCount = (int) config('cryptosik.limits.attachments_per_entry');

        if ($currentCount >= $maxCount) {
            throw new RuntimeException('Attachment limit reached for this draft.');
        }

        $encryptedBinary = $this->cryptoService->encryptWithDataKey(file_get_contents($file->getRealPath()) ?: '', $dataKey);

        return $draft->attachments()->create([
            'filename_enc' => $this->cryptoService->encryptEnvelope($file->getClientOriginalName(), $dataKey),
            'mime_enc' => $this->cryptoService->encryptEnvelope($file->getMimeType() ?? 'application/octet-stream', $dataKey),
            'size_bytes' => $file->getSize(),
            'blob_enc' => $encryptedBinary['ciphertext'],
            'blob_nonce' => $encryptedBinary['nonce'],
        ]);
    }

    public function removeDraftAttachment(User $user, Vault $vault, int $attachmentId): bool
    {
        $draft = EntryDraft::query()
            ->where('vault_id', $vault->id)
            ->where('user_id', $user->id)
            ->first();

        if ($draft === null) {
            return false;
        }

        $attachment = $draft->attachments()->where('id', $attachmentId)->first();

        if ($attachment === null) {
            return false;
        }

        $attachment->delete();

        return true;
    }

    public function finalizeDraft(User $user, Vault $vault): Entry
    {
        return DB::transaction(function () use ($user, $vault): Entry {
            $draft = EntryDraft::query()
                ->where('vault_id', $vault->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($draft === null) {
                throw new RuntimeException('No draft found to finalize.');
            }

            $chainState = VaultChainState::query()->lockForUpdate()->find($vault->id);

            if ($chainState === null) {
                $chainState = VaultChainState::query()->create([
                    'vault_id' => $vault->id,
                    'last_sequence_no' => 0,
                    'last_entry_hash' => null,
                ]);
            }

            $sequenceNo = $chainState->last_sequence_no + 1;
            $prevHash = $chainState->last_entry_hash;
            $finalizedAt = CarbonImmutable::now();
            $entryDate = $draft->entry_date?->toDateString() ?? CarbonImmutable::now()->toDateString();

            $attachmentBlobPayloads = $draft->attachments()->orderBy('id')->pluck('blob_enc')->all();
            $attachmentHash = $this->entryHashService->makeAttachmentHash($attachmentBlobPayloads);

            $entryHash = $this->entryHashService->makeEntryHash(
                sequenceNo: $sequenceNo,
                prevHash: $prevHash,
                entryDateIso: $entryDate,
                titleEnc: $draft->title_enc,
                contentEnc: $draft->content_enc,
                contentFormat: $draft->content_format->value,
                attachmentHash: $attachmentHash,
                finalizedAtIso: $finalizedAt->toIso8601String(),
            );

            $entry = Entry::query()->create([
                'vault_id' => $vault->id,
                'sequence_no' => $sequenceNo,
                'entry_date' => $entryDate,
                'title_enc' => $draft->title_enc,
                'content_enc' => $draft->content_enc,
                'content_format' => $draft->content_format,
                'prev_hash' => $prevHash,
                'entry_hash' => $entryHash,
                'attachment_hash' => $attachmentHash,
                'created_by' => $user->id,
                'finalized_at' => $finalizedAt,
            ]);

            $draftAttachments = $draft->attachments()->get();

            foreach ($draftAttachments as $draftAttachment) {
                EntryAttachment::query()->create([
                    'entry_id' => $entry->id,
                    'filename_enc' => $draftAttachment->filename_enc,
                    'mime_enc' => $draftAttachment->mime_enc,
                    'size_bytes' => $draftAttachment->size_bytes,
                    'blob_enc' => $draftAttachment->blob_enc,
                    'blob_nonce' => $draftAttachment->blob_nonce,
                ]);
            }

            $draft->attachments()->delete();
            $draft->delete();

            $chainState->last_sequence_no = $sequenceNo;
            $chainState->last_entry_hash = $entryHash;
            $chainState->updated_at = CarbonImmutable::now();
            $chainState->save();

            return $entry;
        });
    }
}
