<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Http\Controllers\Vault;

use EvilStudio\Cryptosik\Http\Controllers\Controller;
use EvilStudio\Cryptosik\Http\Requests\UpdateVaultDescriptionRequest;
use EvilStudio\Cryptosik\Models\Entry;
use EvilStudio\Cryptosik\Models\EntryAttachment;
use EvilStudio\Cryptosik\Models\EntryDraft;
use EvilStudio\Cryptosik\Models\User;
use EvilStudio\Cryptosik\Models\Vault;
use EvilStudio\Cryptosik\Services\Audit\AuditLogService;
use EvilStudio\Cryptosik\Services\Crypto\CryptoService;
use EvilStudio\Cryptosik\Services\Vault\UnreadEntryService;
use EvilStudio\Cryptosik\Services\Vault\VaultAccessService;
use EvilStudio\Cryptosik\Support\SessionKeys;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VaultController extends Controller
{
    public function __construct(
        private readonly CryptoService $cryptoService,
        private readonly VaultAccessService $vaultAccessService,
        private readonly AuditLogService $auditLogService,
        private readonly UnreadEntryService $unreadEntryService,
    ) {
    }

    public function workspace(Request $request): View|RedirectResponse
    {
        $user = $this->resolveUser($request);
        $vault = $this->resolveVault($request);
        $dataKey = (string) $request->session()->get(SessionKeys::UNLOCKED_VAULT_KEY, '');

        if ($user === null || $vault === null || $dataKey === '') {
            return redirect()->route('auth.vault.unlock.show');
        }

        $hasMembership = $vault->members()->where('user_id', $user->id)->exists();

        if (!$hasMembership) {
            return redirect()->route('auth.vault.unlock.show');
        }

        $isVaultOwner = (int) $vault->owner_user_id === (int) $user->id;
        $vaultDescription = $this->vaultAccessService->decryptVaultDescription($vault, $dataKey);
        $vaultDescriptionHtml = $vaultDescription !== null ? $this->renderMarkdown($vaultDescription) : null;

        $entryItems = [];
        $readEntryIds = $this->unreadEntryService->getReadEntryIdsForVault($user->id, $vault->id);
        $readEntryIdMap = array_fill_keys($readEntryIds, true);

        $entries = Entry::query()
            ->with('author')
            ->withCount('attachments')
            ->where('vault_id', $vault->id)
            ->orderByDesc('sequence_no')
            ->get();

        foreach ($entries as $entry) {
            $authorNickname = trim((string) ($entry->author?->nickname ?? ''));

            $entryItems[] = [
                'id' => $entry->id,
                'sequence_no' => $entry->sequence_no,
                'entry_date' => $entry->entry_date?->toDateString() ?? $entry->finalized_at->toDateString(),
                'title' => $this->safeDecrypt($entry->title_enc, $dataKey),
                'author_nickname' => $authorNickname,
                'attachments_count' => (int) $entry->attachments_count,
                'is_read' => isset($readEntryIdMap[$entry->id]),
            ];
        }

        $requestedMode = (string) $request->query('mode', '');
        $selectedEntryId = $request->query('entry');
        $workspaceMode = 'overview';

        if ($requestedMode === 'new') {
            $workspaceMode = 'new';
        } elseif ($requestedMode === 'entry' || is_numeric($selectedEntryId)) {
            $workspaceMode = 'entry';
        }

        $selectedEntry = null;
        $selectedEntryTitle = null;
        $selectedEntryHtml = null;
        $selectedEntryRaw = null;
        $selectedEntryAuthor = null;
        $selectedEntryAttachments = [];

        if ($workspaceMode === 'entry' && is_numeric($selectedEntryId)) {
            $selectedEntry = $entries->firstWhere('id', (int) $selectedEntryId);

            if ($selectedEntry !== null) {
                $this->unreadEntryService->markAsRead($user->id, $selectedEntry->id);
                $selectedEntryTitle = $this->safeDecrypt($selectedEntry->title_enc, $dataKey);
                $selectedEntryAuthor = trim((string) ($selectedEntry->author?->nickname ?? ''));

                $markdown = $this->safeDecrypt($selectedEntry->content_enc, $dataKey);
                $selectedEntryRaw = $markdown;
                $selectedEntryHtml = $this->renderMarkdown($markdown);

                $entryAttachments = $selectedEntry->attachments()->orderBy('id')->get();

                foreach ($entryAttachments as $attachment) {
                    $filename = $this->safeDecrypt($attachment->filename_enc, $dataKey);

                    $selectedEntryAttachments[] = [
                        'id' => $attachment->id,
                        'filename' => $filename,
                        'size_bytes' => (int) $attachment->size_bytes,
                        'download_url' => route('vault.entries.attachments.download', [
                            'entry' => $selectedEntry->id,
                            'attachment' => $attachment->id,
                        ]),
                    ];
                }

                foreach ($entryItems as $index => $item) {
                    if ($item['id'] === $selectedEntry->id) {
                        $entryItems[$index]['is_read'] = true;

                        break;
                    }
                }
            }
        }

        $draft = EntryDraft::query()
            ->with('attachments')
            ->where('vault_id', $vault->id)
            ->where('user_id', $user->id)
            ->first();

        $draftTitle = '';
        $draftContent = '';
        $draftEntryDate = now()->toDateString();
        $draftAttachments = [];

        if ($draft !== null) {
            $draftEntryDate = $draft->entry_date?->toDateString() ?? $draftEntryDate;
            $draftTitle = $this->safeDecrypt($draft->title_enc, $dataKey);
            $draftContent = $this->safeDecrypt($draft->content_enc, $dataKey);

            foreach ($draft->attachments as $attachment) {
                $draftAttachments[] = [
                    'id' => $attachment->id,
                    'filename' => $this->safeDecrypt($attachment->filename_enc, $dataKey),
                    'size_bytes' => $attachment->size_bytes,
                ];
            }
        }

        return view('vault.workspace', [
            'vaultName' => $this->vaultAccessService->decryptVaultName($vault, $dataKey),
            'vaultDescription' => $vaultDescription,
            'vaultDescriptionHtml' => $vaultDescriptionHtml,
            'isVaultOwner' => $isVaultOwner,
            'vaultOverviewUrl' => route('vault.workspace'),
            'entries' => $entryItems,
            'selectedEntry' => $selectedEntry,
            'selectedEntryId' => $selectedEntry?->id,
            'selectedEntryTitle' => $selectedEntryTitle,
            'selectedEntryAuthor' => $selectedEntryAuthor,
            'selectedEntryHtml' => $selectedEntryHtml,
            'selectedEntryRaw' => $selectedEntryRaw,
            'selectedEntryAttachments' => $selectedEntryAttachments,
            'workspaceMode' => $workspaceMode,
            'draftEntryDate' => $draftEntryDate,
            'draftTitle' => $draftTitle,
            'draftContent' => $draftContent,
            'draftAttachments' => $draftAttachments,
            'isArchived' => $vault->status->value === 'archived',
        ]);
    }

    public function updateDescription(UpdateVaultDescriptionRequest $request): RedirectResponse
    {
        $user = $this->resolveUser($request);
        $vault = $this->resolveVault($request);
        $dataKey = (string) $request->session()->get(SessionKeys::UNLOCKED_VAULT_KEY, '');

        if ($user === null || $vault === null || $dataKey === '') {
            return redirect()->route('auth.vault.unlock.show');
        }

        $hasMembership = $vault->members()->where('user_id', $user->id)->exists();

        if (!$hasMembership || (int) $vault->owner_user_id !== (int) $user->id) {
            return back()->withErrors(['description' => __('messages.vault.errors.owner_only_description')]);
        }

        if ($vault->status->value === 'archived') {
            return back()->withErrors(['description' => __('messages.vault.errors.readonly')]);
        }

        $validated = $request->validated();

        if (array_key_exists('title', $validated)) {
            $title = trim((string) ($validated['title'] ?? ''));

            if ($title === '') {
                return back()->withErrors(['title' => __('messages.vault.errors.title_required')]);
            }

            $vault->name_enc = $this->cryptoService->encryptEnvelope($title, $dataKey);
        }

        $description = trim((string) ($validated['description'] ?? ''));

        $vault->description_enc = $description !== ''
            ? $this->cryptoService->encryptEnvelope($description, $dataKey)
            : null;
        $vault->save();

        return redirect()->route('vault.workspace')->with('status', __('messages.vault.status.description_updated'));
    }

    public function downloadAttachment(Request $request, Entry $entry, EntryAttachment $attachment): StreamedResponse|RedirectResponse
    {
        $user = $this->resolveUser($request);
        $vault = $this->resolveVault($request);
        $dataKey = (string) $request->session()->get(SessionKeys::UNLOCKED_VAULT_KEY, '');

        if ($user === null || $vault === null || $dataKey === '') {
            return redirect()->route('auth.vault.unlock.show');
        }

        $hasMembership = $vault->members()->where('user_id', $user->id)->exists();

        if (!$hasMembership || $entry->vault_id !== $vault->id || $attachment->entry_id !== $entry->id) {
            abort(404);
        }

        $filename = $this->sanitizeFilename($this->safeDecrypt($attachment->filename_enc, $dataKey));
        $mimeType = $this->safeDecrypt($attachment->mime_enc, $dataKey);
        $binary = $this->cryptoService->decryptWithDataKey($attachment->blob_enc, $attachment->blob_nonce, $dataKey);

        if ($filename === '') {
            $filename = sprintf('attachment-%d.bin', $attachment->id);
        }

        if ($mimeType === '' || $mimeType === '[Unable to decrypt]') {
            $mimeType = 'application/octet-stream';
        }

        $size = (string) mb_strlen($binary, '8bit');

        return response()->streamDownload(
            static function () use ($binary): void {
                echo $binary;
            },
            $filename,
            [
                'Content-Type' => $mimeType,
                'Content-Length' => $size,
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    public function lock(Request $request): RedirectResponse
    {
        $user = $this->resolveUser($request);
        $vault = $this->resolveVault($request);

        if ($user !== null) {
            $this->auditLogService->vaultLocked($user, $vault);
        }

        $request->session()->forget([
            SessionKeys::UNLOCKED_VAULT_ID,
            SessionKeys::UNLOCKED_VAULT_KEY,
        ]);

        return redirect()->route('auth.vault.unlock.show');
    }

    private function resolveUser(Request $request): ?User
    {
        $id = $request->session()->get(SessionKeys::USER_ID);

        return is_numeric($id) ? User::query()->find((int) $id) : null;
    }

    private function resolveVault(Request $request): ?Vault
    {
        $vaultId = $request->session()->get(SessionKeys::UNLOCKED_VAULT_ID);

        return is_string($vaultId)
            ? Vault::query()->with('members')->find($vaultId)
            : null;
    }

    private function renderMarkdown(string $markdown): string
    {
        $converter = new GithubFlavoredMarkdownConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return $converter->convert($markdown)->getContent();
    }

    private function safeDecrypt(string $encryptedEnvelope, string $dataKey): string
    {
        try {
            return $this->cryptoService->decryptEnvelope($encryptedEnvelope, $dataKey);
        } catch (RuntimeException) {
            return '[Unable to decrypt]';
        }
    }

    private function sanitizeFilename(string $filename): string
    {
        $normalized = preg_replace('/[\r\n\t]+/', ' ', $filename) ?? '';

        return trim(str_replace(['/', '\\'], '-', $normalized));
    }
}
