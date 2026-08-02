<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Http\Controllers\Vault;

use EvilStudio\Cryptosik\Enums\VaultStatus;
use EvilStudio\Cryptosik\Http\Controllers\Controller;
use EvilStudio\Cryptosik\Http\Requests\UpdateVaultDescriptionRequest;
use EvilStudio\Cryptosik\Models\Entry;
use EvilStudio\Cryptosik\Models\EntryAttachment;
use EvilStudio\Cryptosik\Models\Vault;
use EvilStudio\Cryptosik\Services\Audit\AuditLogService;
use EvilStudio\Cryptosik\Services\Crypto\CryptoService;
use EvilStudio\Cryptosik\Services\Crypto\EnvelopeReader;
use EvilStudio\Cryptosik\Services\Vault\VaultWorkspacePresenter;
use EvilStudio\Cryptosik\Support\SessionKeys;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VaultController extends Controller
{
    public function __construct(
        private readonly CryptoService $cryptoService,
        private readonly AuditLogService $auditLogService,
        private readonly VaultWorkspacePresenter $workspacePresenter,
        private readonly EnvelopeReader $envelopeReader,
    ) {}

    public function workspace(Request $request): View|RedirectResponse
    {
        $user = $this->currentUser($request);
        $vault = $this->currentVault($request);
        $dataKey = (string) $request->session()->get(SessionKeys::UNLOCKED_VAULT_KEY, '');

        if ($user === null || $vault === null || $dataKey === '') {
            return redirect()->route('auth.vault.unlock.show');
        }

        return view('vault.workspace', $this->workspacePresenter->present(
            user: $user,
            vault: $vault,
            dataKey: $dataKey,
            requestedMode: (string) $request->query('mode', ''),
            requestedEntryId: $request->query('entry') !== null ? (string) $request->query('entry') : null,
        ));
    }

    public function updateDescription(UpdateVaultDescriptionRequest $request): RedirectResponse
    {
        $user = $this->currentUser($request);
        $vault = $this->currentVault($request);
        $dataKey = (string) $request->session()->get(SessionKeys::UNLOCKED_VAULT_KEY, '');

        if ($user === null || $vault === null || $dataKey === '') {
            return redirect()->route('auth.vault.unlock.show');
        }

        if ((int) $vault->owner_user_id !== (int) $user->id) {
            return back()->withErrors(['description' => __('messages.vault.errors.owner_only_description')]);
        }

        if ($vault->status === VaultStatus::Archived) {
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
        $user = $this->currentUser($request);
        $vault = $this->currentVault($request);
        $dataKey = (string) $request->session()->get(SessionKeys::UNLOCKED_VAULT_KEY, '');

        if ($user === null || $vault === null || $dataKey === '') {
            return redirect()->route('auth.vault.unlock.show');
        }

        // Membership is enforced by EnsureVaultUnlocked; these two keep the entry and the
        // attachment bound to the unlocked vault.
        if ($entry->vault_id !== $vault->id || $attachment->entry_id !== $entry->id) {
            abort(404);
        }

        $filename = $this->sanitizeFilename($this->envelopeReader->decryptOrPlaceholder($attachment->filename_enc, $dataKey));
        $mimeType = $this->envelopeReader->decryptOrPlaceholder($attachment->mime_enc, $dataKey);
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
        $user = $this->currentUser($request);
        $vault = $this->currentVault($request);

        if ($user !== null) {
            $this->auditLogService->vaultLocked($user, $vault);
        }

        $request->session()->forget([
            SessionKeys::UNLOCKED_VAULT_ID,
            SessionKeys::UNLOCKED_VAULT_KEY,
        ]);

        return redirect()->route('auth.vault.unlock.show');
    }

    private function sanitizeFilename(string $filename): string
    {
        $normalized = preg_replace('/[\r\n\t]+/', ' ', $filename) ?? '';

        return trim(str_replace(['/', '\\'], '-', $normalized));
    }
}
