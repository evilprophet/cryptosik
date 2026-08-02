<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Services\Vault;

use EvilStudio\Cryptosik\Enums\VaultStatus;
use EvilStudio\Cryptosik\Models\Entry;
use EvilStudio\Cryptosik\Models\EntryDraft;
use EvilStudio\Cryptosik\Models\User;
use EvilStudio\Cryptosik\Models\Vault;
use EvilStudio\Cryptosik\Services\Crypto\EnvelopeReader;
use EvilStudio\Cryptosik\Services\Markdown\MarkdownRenderer;

class VaultWorkspacePresenter
{
    public function __construct(
        private readonly VaultAccessService $vaultAccessService,
        private readonly UnreadEntryService $unreadEntryService,
        private readonly EnvelopeReader $envelopeReader,
        private readonly MarkdownRenderer $markdownRenderer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(User $user, Vault $vault, string $dataKey, string $requestedMode, ?string $requestedEntryId): array
    {
        $mode = $this->resolveMode($requestedMode, $requestedEntryId);
        $entryItems = $this->buildEntryList($user, $vault, $dataKey);
        $selected = $this->buildSelectedEntry($user, $vault, $dataKey, $mode, $requestedEntryId, $entryItems);
        $draft = $this->buildDraft($user, $vault, $dataKey);
        $description = $this->vaultAccessService->decryptVaultDescription($vault, $dataKey);

        return [
            'vaultName' => $this->vaultAccessService->decryptVaultName($vault, $dataKey),
            'vaultDescription' => $description,
            'vaultDescriptionHtml' => $description !== null ? $this->markdownRenderer->render($description) : null,
            'isVaultOwner' => (int) $vault->owner_user_id === (int) $user->id,
            'vaultOverviewUrl' => route('vault.workspace'),
            'entries' => $selected['entries'],
            'selectedEntry' => $selected['entry'],
            'selectedEntryId' => $selected['entry']?->id,
            'selectedEntryTitle' => $selected['title'],
            'selectedEntryAuthor' => $selected['author'],
            'selectedEntryHtml' => $selected['html'],
            'selectedEntryRaw' => $selected['raw'],
            'selectedEntryAttachments' => $selected['attachments'],
            'selectedEntryNewerUrl' => $selected['newer_url'],
            'selectedEntryOlderUrl' => $selected['older_url'],
            'workspaceMode' => $mode,
            'draftEntryDate' => $draft['entry_date'],
            'draftTitle' => $draft['title'],
            'draftContent' => $draft['content'],
            'draftAttachments' => $draft['attachments'],
            'isArchived' => $vault->status === VaultStatus::Archived,
        ];
    }

    private function resolveMode(string $requestedMode, ?string $requestedEntryId): string
    {
        if ($requestedMode === 'new') {
            return 'new';
        }

        if ($requestedMode === 'entry' || is_numeric($requestedEntryId)) {
            return 'entry';
        }

        return 'overview';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildEntryList(User $user, Vault $vault, string $dataKey): array
    {
        $readEntryIdMap = array_fill_keys(
            $this->unreadEntryService->getReadEntryIdsForVault($user->id, $vault->id),
            true,
        );

        // content_enc is only needed for the selected entry, fetched separately.
        $entries = Entry::query()
            ->select(['id', 'vault_id', 'sequence_no', 'entry_date', 'finalized_at', 'title_enc', 'created_by'])
            ->with('author')
            ->withCount('attachments')
            ->where('vault_id', $vault->id)
            ->orderByDesc('sequence_no')
            ->get();

        $items = [];

        foreach ($entries as $entry) {
            $items[] = [
                'id' => $entry->id,
                'sequence_no' => $entry->sequence_no,
                'entry_date' => $entry->entry_date?->toDateString() ?? $entry->finalized_at->toDateString(),
                'title' => $this->envelopeReader->decryptOrPlaceholder($entry->title_enc, $dataKey),
                'author_nickname' => trim((string) ($entry->author?->nickname ?? '')),
                'attachments_count' => (int) $entry->attachments_count,
                'is_read' => isset($readEntryIdMap[$entry->id]),
            ];
        }

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $entryItems
     * @return array<string, mixed>
     */
    private function buildSelectedEntry(
        User $user,
        Vault $vault,
        string $dataKey,
        string $mode,
        ?string $requestedEntryId,
        array $entryItems,
    ): array {
        $empty = [
            'entries' => $entryItems,
            'entry' => null,
            'title' => null,
            'author' => null,
            'html' => null,
            'raw' => null,
            'attachments' => [],
            'newer_url' => null,
            'older_url' => null,
        ];

        if ($mode !== 'entry' || ! is_numeric($requestedEntryId)) {
            return $empty;
        }

        // The vault_id scope carries the authorization for the requested id.
        $entry = Entry::query()
            ->with('author')
            ->where('vault_id', $vault->id)
            ->where('id', (int) $requestedEntryId)
            ->first();

        if ($entry === null) {
            return $empty;
        }

        $this->unreadEntryService->markAsRead($user->id, $entry->id);

        $markdown = $this->envelopeReader->decryptOrPlaceholder($entry->content_enc, $dataKey);
        $attachments = [];

        foreach ($entry->attachments()->orderBy('id')->get() as $attachment) {
            $attachments[] = [
                'id' => $attachment->id,
                'filename' => $this->envelopeReader->decryptOrPlaceholder($attachment->filename_enc, $dataKey),
                'size_bytes' => (int) $attachment->size_bytes,
                'download_url' => route('vault.entries.attachments.download', [
                    'entry' => $entry->id,
                    'attachment' => $attachment->id,
                ]),
            ];
        }

        [$entryItems, $newerUrl, $olderUrl] = $this->markReadAndFindNeighbours($entryItems, $entry->id);

        return [
            'entries' => $entryItems,
            'entry' => $entry,
            'title' => $this->envelopeReader->decryptOrPlaceholder($entry->title_enc, $dataKey),
            'author' => trim((string) ($entry->author?->nickname ?? '')),
            'html' => $this->markdownRenderer->render($markdown),
            'raw' => $markdown,
            'attachments' => $attachments,
            'newer_url' => $newerUrl,
            'older_url' => $olderUrl,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $entryItems
     * @return array{0: list<array<string, mixed>>, 1: ?string, 2: ?string}
     */
    private function markReadAndFindNeighbours(array $entryItems, int $selectedId): array
    {
        foreach ($entryItems as $index => $item) {
            if ($item['id'] !== $selectedId) {
                continue;
            }

            $entryItems[$index]['is_read'] = true;

            // Ordered newest first, so the previous index is the newer entry.
            $newer = $entryItems[$index - 1] ?? null;
            $older = $entryItems[$index + 1] ?? null;

            return [
                $entryItems,
                $newer !== null ? route('vault.workspace', ['mode' => 'entry', 'entry' => $newer['id']]) : null,
                $older !== null ? route('vault.workspace', ['mode' => 'entry', 'entry' => $older['id']]) : null,
            ];
        }

        return [$entryItems, null, null];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDraft(User $user, Vault $vault, string $dataKey): array
    {
        $draft = EntryDraft::query()
            ->with('attachments')
            ->where('vault_id', $vault->id)
            ->where('user_id', $user->id)
            ->first();

        if ($draft === null) {
            return [
                'entry_date' => now()->toDateString(),
                'title' => '',
                'content' => '',
                'attachments' => [],
            ];
        }

        $attachments = [];

        foreach ($draft->attachments as $attachment) {
            $attachments[] = [
                'id' => $attachment->id,
                'filename' => $this->envelopeReader->decryptOrPlaceholder($attachment->filename_enc, $dataKey),
                'size_bytes' => $attachment->size_bytes,
            ];
        }

        return [
            'entry_date' => $draft->entry_date?->toDateString() ?? now()->toDateString(),
            'title' => $this->envelopeReader->decryptOrPlaceholder($draft->title_enc, $dataKey),
            'content' => $this->envelopeReader->decryptOrPlaceholder($draft->content_enc, $dataKey),
            'attachments' => $attachments,
        ];
    }
}
