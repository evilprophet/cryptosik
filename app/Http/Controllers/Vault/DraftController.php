<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Http\Controllers\Vault;

use Closure;
use EvilStudio\Cryptosik\Enums\VaultStatus;
use EvilStudio\Cryptosik\Http\Controllers\Controller;
use EvilStudio\Cryptosik\Models\User;
use EvilStudio\Cryptosik\Models\Vault;
use EvilStudio\Cryptosik\Services\Audit\AuditLogService;
use EvilStudio\Cryptosik\Services\Vault\EntryService;
use EvilStudio\Cryptosik\Support\SessionKeys;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class DraftController extends Controller
{
    public function __construct(
        private readonly EntryService $entryService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function save(Request $request): RedirectResponse
    {
        $validated = $this->validateCompose($request);

        $user = $this->resolveUser($request);
        $vault = $this->resolveVault($request);
        $dataKey = (string) $request->session()->get(SessionKeys::UNLOCKED_VAULT_KEY, '');

        if ($user === null || $vault === null || $dataKey === '') {
            return redirect()->route('auth.vault.unlock.show');
        }

        if ($vault->status !== VaultStatus::Active) {
            return $this->redirectToComposer()->withErrors(['title' => __('messages.vault.errors.readonly')]);
        }

        $this->entryService->upsertDraft(
            user: $user,
            vault: $vault,
            dataKey: $dataKey,
            entryDate: (string) $validated['entry_date'],
            title: (string) $validated['title'],
            content: (string) ($validated['content'] ?? ''),
        );

        $this->auditLogService->vaultDraftSaved($user, $vault);

        return $this->redirectToComposer()->with('status', __('messages.vault.status.draft_saved'));
    }

    public function delete(Request $request): RedirectResponse
    {
        $user = $this->resolveUser($request);
        $vault = $this->resolveVault($request);

        if ($user === null || $vault === null) {
            return redirect()->route('auth.vault.unlock.show');
        }

        if ($vault->status !== VaultStatus::Active) {
            return $this->redirectToComposer()->withErrors(['draft' => __('messages.vault.errors.readonly')]);
        }

        $this->entryService->deleteDraft($user, $vault);

        return $this->redirectToComposer()->with('status', __('messages.vault.status.draft_deleted'));
    }

    public function finalize(Request $request): RedirectResponse
    {
        $validated = $this->validateCompose($request);

        $user = $this->resolveUser($request);
        $vault = $this->resolveVault($request);
        $dataKey = (string) $request->session()->get(SessionKeys::UNLOCKED_VAULT_KEY, '');

        if ($user === null || $vault === null || $dataKey === '') {
            return redirect()->route('auth.vault.unlock.show');
        }

        if ($vault->status !== VaultStatus::Active) {
            return $this->redirectToComposer()->withErrors(['draft' => __('messages.vault.errors.readonly')]);
        }

        try {
            $this->entryService->upsertDraft(
                user: $user,
                vault: $vault,
                dataKey: $dataKey,
                entryDate: (string) $validated['entry_date'],
                title: (string) $validated['title'],
                content: (string) ($validated['content'] ?? ''),
            );

            $entry = $this->entryService->finalizeDraft($user, $vault);
            $this->auditLogService->vaultEntryAdded($user, $entry);
        } catch (RuntimeException $exception) {
            return $this->redirectToComposer()->withErrors(['draft' => $exception->getMessage()]);
        }

        return redirect()->route('vault.workspace', ['mode' => 'entry', 'entry' => $entry->id])
            ->with('status', __('messages.vault.status.message_added'));
    }

    public function uploadAttachment(Request $request): RedirectResponse
    {
        $allowedMimeTypes = array_map('mb_strtolower', (array) config('cryptosik.allowed_mime_types'));
        $allowedExtensions = array_map('mb_strtolower', (array) config('cryptosik.allowed_attachment_extensions'));
        $maxFileSizeKb = (int) ceil(((int) config('cryptosik.limits.attachment_size_bytes')) / 1024);
        $contentMaxChars = (int) config('cryptosik.limits.entry_content_chars');
        $composeData = $request->validate([
            'entry_date' => ['nullable', 'date_format:Y-m-d'],
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string', sprintf('max:%d', $contentMaxChars)],
        ]);

        $user = $this->resolveUser($request);
        $vault = $this->resolveVault($request);
        $dataKey = (string) $request->session()->get(SessionKeys::UNLOCKED_VAULT_KEY, '');

        if ($user === null || $vault === null || $dataKey === '') {
            return redirect()->route('auth.vault.unlock.show');
        }

        if ($vault->status !== VaultStatus::Active) {
            return $this->redirectToComposer()->withErrors(['attachment' => __('messages.vault.errors.readonly')]);
        }

        $entryDate = trim((string) ($composeData['entry_date'] ?? ''));
        $title = (string) ($composeData['title'] ?? '');
        $content = (string) ($composeData['content'] ?? '');
        $hasComposeInput = $entryDate !== '' || $title !== '' || $content !== '';

        if ($hasComposeInput) {
            $this->entryService->upsertDraft(
                user: $user,
                vault: $vault,
                dataKey: $dataKey,
                entryDate: $entryDate !== '' ? $entryDate : now()->toDateString(),
                title: $title !== '' ? $title : EntryService::DEFAULT_DRAFT_TITLE,
                content: $content,
            );
        }

        $attachmentValidator = Validator::make($request->all(), [
            'attachment' => [
                'required',
                'file',
                sprintf('max:%d', $maxFileSizeKb),
                static function (string $attribute, mixed $value, Closure $fail) use ($allowedExtensions, $allowedMimeTypes): void {
                    if (!$value instanceof UploadedFile) {
                        $fail(__('messages.vault.errors.attachment_type_not_allowed'));

                        return;
                    }

                    $extension = mb_strtolower((string) $value->getClientOriginalExtension());
                    $mimeType = mb_strtolower((string) ($value->getMimeType() ?? ''));

                    if (!in_array($extension, $allowedExtensions, true) && !in_array($mimeType, $allowedMimeTypes, true)) {
                        $fail(__('messages.vault.errors.attachment_type_not_allowed'));
                    }
                },
            ],
        ]);

        if ($attachmentValidator->fails()) {
            return $this->redirectToComposer()
                ->withErrors($attachmentValidator)
                ->withInput();
        }

        try {
            $this->entryService->addDraftAttachment($user, $vault, $dataKey, $attachmentValidator->validated()['attachment']);
            $this->auditLogService->vaultFileUploaded($user, $vault);
        } catch (RuntimeException $exception) {
            return $this->redirectToComposer()->withErrors(['attachment' => $exception->getMessage()]);
        }

        return $this->redirectToComposer()->with('status', __('messages.vault.status.attachment_added'));
    }

    public function deleteAttachment(Request $request, int $attachment): RedirectResponse
    {
        $user = $this->resolveUser($request);
        $vault = $this->resolveVault($request);

        if ($user === null || $vault === null) {
            return redirect()->route('auth.vault.unlock.show');
        }

        if ($vault->status !== VaultStatus::Active) {
            return $this->redirectToComposer()->withErrors(['attachment' => __('messages.vault.errors.readonly')]);
        }

        $deleted = $this->entryService->removeDraftAttachment($user, $vault, $attachment);

        if (!$deleted) {
            return $this->redirectToComposer()->withErrors(['attachment' => __('messages.vault.errors.attachment_not_found')]);
        }

        return $this->redirectToComposer()->with('status', __('messages.vault.status.attachment_removed'));
    }

    private function validateCompose(Request $request): array
    {
        $contentMaxChars = (int) config('cryptosik.limits.entry_content_chars');

        return $request->validate([
            'entry_date' => ['required', 'date_format:Y-m-d'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string', sprintf('max:%d', $contentMaxChars)],
        ]);
    }

    private function redirectToComposer(): RedirectResponse
    {
        return redirect()->route('vault.workspace', ['mode' => 'new']);
    }

    private function resolveUser(Request $request): ?User
    {
        $id = $request->session()->get(SessionKeys::USER_ID);

        return is_numeric($id) ? User::query()->find((int) $id) : null;
    }

    private function resolveVault(Request $request): ?Vault
    {
        $vaultId = $request->session()->get(SessionKeys::UNLOCKED_VAULT_ID);

        return is_string($vaultId) ? Vault::query()->find($vaultId) : null;
    }
}
