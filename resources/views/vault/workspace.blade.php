@php($isComposeMode = $workspaceMode === 'new')
@php($isOverviewMode = $workspaceMode === 'overview')
@php($contentCharsLimit = (int) config('cryptosik.limits.entry_content_chars'))
@php($attachmentAccept = collect((array) config('cryptosik.allowed_attachment_extensions'))->map(fn ($ext) => '.'.ltrim((string) $ext, '.'))->implode(','))

<x-layouts.app :header-center-title="$vaultName" :header-center-href="$vaultOverviewUrl" :show-vault-lock="true">
    @if ($isArchived)
        <div class="mb-4 rounded-xl border border-base-300 bg-base-200 p-4 shadow-sm">
            <p class="text-sm font-medium text-warning">{{ __('messages.vault.workspace.archived_readonly') }}</p>
        </div>
    @endif

    <div class="grid h-[calc(100dvh-11rem)] min-h-0 gap-4 overflow-hidden lg:grid-cols-12">
        <aside class="flex h-full min-h-0 flex-col rounded-xl border border-base-300 bg-base-200 p-4 shadow-sm lg:col-span-3">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-base-content/70">{{ __('messages.vault.workspace.entries_chronological') }}</h3>
                <a href="{{ route('vault.workspace', ['mode' => 'new']) }}" class="rounded-md border border-base-300 bg-base-300 px-3 py-2 text-xs font-medium text-base-content hover:bg-base-300 cursor-pointer select-text">{{ __('messages.vault.workspace.new_message') }}</a>
            </div>
            <div class="min-h-0 flex-1 space-y-1 overflow-y-auto pr-1">
                @forelse ($entries as $item)
                    <a href="{{ route('vault.workspace', ['mode' => 'entry', 'entry' => $item['id']]) }}"
                       @class([
                           'block rounded-lg border px-3 py-2 transition cursor-pointer select-text',
                           'border-success bg-success/10' => $selectedEntryId === $item['id'],
                           'border-base-300 hover:bg-base-300' => $selectedEntryId !== $item['id'],
                           'ring-1 ring-warning/70' => !$item['is_read'],
                       ])>
                        <p class="flex items-center gap-1 text-xs text-base-content/60">
                            <span>{{ $item['entry_date'] }} - {{ $item['author_nickname'] }}</span>
                            @if (!$item['is_read'])
                                <span class="inline-flex rounded bg-warning/20 px-1.5 py-0.5 text-[10px] font-semibold text-warning">{{ __('messages.vault.workspace.unread') }}</span>
                            @endif
                        </p>
                        <p class="flex items-center gap-1 text-sm font-medium text-base-content">#{{ $item['sequence_no'] }} {{ $item['title'] }}
                            @if ((int) ($item['attachments_count'] ?? 0) > 0)
                                <span class="inline-flex items-center text-[10px] font-semibold text-secondary" title="attachments">📎{{ (int) $item['attachments_count'] }}</span>
                            @endif
                        </p>
                    </a>
                    @unless ($loop->last)
                        <div class="flex items-center justify-center py-0.5">
                            <span class="block h-1.5 w-1.5 rotate-45 border-t border-l border-base-content/40"></span>
                        </div>
                    @endunless
                @empty
                    <p class="text-sm text-base-content/60">{{ __('messages.vault.workspace.no_entries') }}</p>
                @endforelse
            </div>
        </aside>

        <main class="h-full min-h-0 space-y-4 lg:col-span-9">
            @if ($isComposeMode)
                <section class="rounded-xl border border-base-300 bg-base-200 p-6 shadow-sm h-full overflow-y-auto">
                    <h3 class="mb-3 text-lg font-semibold text-base-content">{{ __('messages.vault.workspace.new_message') }}</h3>

                    <form id="message-compose-form" method="post" action="{{ route('vault.draft.finalize') }}" class="space-y-4">
                        @csrf
                        <label class="block text-sm">
                            <span class="mb-1 block font-medium text-base-content/90">{{ __('messages.vault.workspace.entry_date') }}</span>
                            <input type="date" name="entry_date" required value="{{ old('entry_date', $draftEntryDate) }}"
                                   class="w-full rounded-md border border-base-300 bg-base-100 px-3 py-2 text-base-content" />
                        </label>
                        <label class="block text-sm">
                            <span class="mb-1 block font-medium text-base-content/90">{{ __('messages.vault.workspace.title') }}</span>
                            <input type="text" name="title" required value="{{ old('title', $draftTitle) }}"
                                   class="w-full rounded-md border border-base-300 bg-base-100 px-3 py-2 text-base-content" />
                        </label>
                        <label class="block text-sm">
                            <span class="mb-1 block font-medium text-base-content/90">{{ __('messages.vault.workspace.content_markdown') }}</span>
                            <textarea id="content-field" name="content" rows="14" maxlength="{{ $contentCharsLimit }}" class="w-full rounded-md border border-base-300 bg-base-100 px-3 py-2 text-base-content">{{ old('content', $draftContent) }}</textarea>
                            <p id="content-counter" class="mt-1 hidden text-right text-xs text-base-content/60">0 / {{ number_format($contentCharsLimit, 0, '.', ' ') }}</p>
                        </label>

                        <div class="rounded-lg border border-base-300 p-3">
                            <h4 class="mb-2 text-sm font-semibold text-base-content">{{ __('messages.vault.workspace.attachments') }}</h4>
                            @if ($draftAttachments)
                                <ul class="space-y-2 text-sm text-base-content/70">
                                    @foreach ($draftAttachments as $attachment)
                                        <li class="flex flex-wrap items-center justify-between gap-2 rounded-md border border-base-300 px-3 py-2">
                                            <span>{{ $attachment['filename'] }} ({{ number_format(((int) $attachment['size_bytes']) / 1024, 1) }} KB)</span>
                                            <button type="submit" formaction="{{ route('vault.draft.attachments.delete', ['attachment' => $attachment['id'], 'mode' => 'new']) }}" formnovalidate class="rounded-md border border-base-300 bg-base-300 px-3 py-1.5 text-xs font-medium text-base-content hover:bg-base-300/80">{{ __('messages.vault.actions.remove_attachment') }}</button>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="text-sm text-base-content/60">{{ __('messages.vault.workspace.no_attachments') }}</p>
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center gap-2 border-t border-base-300 pt-3">
                            <button type="submit" formaction="{{ route('vault.draft.save', ['mode' => 'new']) }}" class="rounded-md bg-secondary px-4 py-2 text-sm font-medium text-secondary-content hover:opacity-90 disabled:opacity-50 cursor-pointer select-text" @if($isArchived) disabled @endif>
                                {{ __('messages.vault.actions.save_draft') }}
                            </button>
                            <button type="submit" formaction="{{ route('vault.draft.delete', ['mode' => 'new']) }}" formnovalidate class="rounded-md bg-error px-4 py-2 text-sm font-medium text-error-content hover:opacity-90 disabled:opacity-50 cursor-pointer select-text" @if($isArchived) disabled @endif>
                                {{ __('messages.vault.actions.delete_draft') }}
                            </button>
                            <button type="submit" formaction="{{ route('vault.draft.finalize') }}" class="rounded-md bg-success px-4 py-2 text-sm font-medium text-success-content hover:opacity-90 disabled:opacity-50 cursor-pointer select-text" @if($isArchived) disabled @endif>
                                {{ __('messages.vault.actions.add_message') }}
                            </button>
                        </div>
                    </form>

                    <form id="attachment-upload-form" method="post" action="{{ route('vault.draft.attachments.upload', ['mode' => 'new']) }}" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-end gap-2 border-t border-base-300 pt-3">
                        @csrf
                        <input type="hidden" name="entry_date" value="{{ old('entry_date', $draftEntryDate) }}" />
                        <input type="hidden" name="title" value="{{ old('title', $draftTitle) }}" />
                        <input type="hidden" name="content" value="{{ old('content', $draftContent) }}" />
                        <label class="block text-sm">
                            <span class="mb-1 block font-medium text-base-content/90">{{ __('messages.vault.workspace.attachment') }}</span>
                            <input type="file" name="attachment" accept="{{ $attachmentAccept }}" class="block w-full rounded-md border border-base-300 bg-base-100 px-3 py-2 text-sm text-base-content/90 file:mr-3 file:rounded-md file:border-0 file:bg-base-300 file:px-3 file:py-2 file:text-sm file:font-medium file:text-base-content hover:file:bg-base-300/80" @if($isArchived) disabled @endif />
                        </label>
                        <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-content hover:opacity-90 disabled:opacity-50 cursor-pointer select-text" @if($isArchived) disabled @endif>
                            {{ __('messages.vault.actions.add_attachment') }}
                        </button>
                    </form>

                    <script>
                        (() => {
                            const composeForm = document.getElementById('message-compose-form');
                            const attachmentForm = document.getElementById('attachment-upload-form');
                            const contentField = document.getElementById('content-field');
                            const contentCounter = document.getElementById('content-counter');

                            if (!composeForm || !attachmentForm) {
                                return;
                            }

                            let markdownEditor = null;

                            const getContentValue = () => markdownEditor ? markdownEditor.value() : (contentField ? contentField.value : '');
                            const fields = ['entry_date', 'title', 'content'];

                            const syncComposeToAttachmentForm = () => {
                                fields.forEach((fieldName) => {
                                    const targetField = attachmentForm.elements.namedItem(fieldName);

                                    if (!targetField) {
                                        return;
                                    }

                                    if (fieldName === 'content') {
                                        targetField.value = getContentValue();

                                        return;
                                    }

                                    const sourceField = composeForm.elements.namedItem(fieldName);

                                    if (sourceField) {
                                        targetField.value = sourceField.value;
                                    }
                                });
                            };

                            let updateContentCounter = () => {};

                            if (contentCounter && contentField) {
                                const maxLength = Number(contentField.getAttribute('maxlength') || '0');
                                const threshold = maxLength > 0 ? Math.floor(maxLength * 0.9) : 0;

                                updateContentCounter = () => {
                                    const currentLength = getContentValue().length;
                                    contentCounter.textContent = `${currentLength.toLocaleString('en-US')} / ${maxLength.toLocaleString('en-US')}`;
                                    const showCounter = threshold > 0 && currentLength >= threshold;
                                    contentCounter.classList.toggle('hidden', !showCounter);
                                    contentCounter.classList.toggle('text-warning', showCounter);
                                };

                                contentField.addEventListener('input', () => {
                                    updateContentCounter();
                                    syncComposeToAttachmentForm();
                                });
                            }

                            const initializeMarkdownEditor = () => {
                                if (markdownEditor !== null || !contentField || typeof window.SimpleMDE === 'undefined') {
                                    return;
                                }

                                markdownEditor = new window.SimpleMDE({
                                    element: contentField,
                                    forceSync: true,
                                    autoDownloadFontAwesome: false,
                                    spellChecker: false,
                                    status: ['lines', 'words'],
                                });

                                markdownEditor.codemirror.on('change', () => {
                                    updateContentCounter();
                                    syncComposeToAttachmentForm();
                                });

                                updateContentCounter();
                                syncComposeToAttachmentForm();
                            };

                            syncComposeToAttachmentForm();
                            updateContentCounter();
                            attachmentForm.addEventListener('submit', syncComposeToAttachmentForm);

                            initializeMarkdownEditor();
                            window.addEventListener('load', initializeMarkdownEditor, { once: true });
                        })();
                    </script>
                </section>
            @elseif ($isOverviewMode)
                <section class="rounded-xl border border-base-300 bg-base-200 p-6 shadow-sm h-full overflow-y-auto">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <h3 class="text-lg font-semibold text-base-content">{{ __('messages.vault.workspace.overview_title') }}</h3>
                        @if ($isVaultOwner)
                            <button id="open-vault-description-modal" type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-base-300 bg-base-300 text-base-content hover:bg-base-300/80 disabled:opacity-50 cursor-pointer" title="{{ __('messages.vault.actions.update_description') }}" aria-label="{{ __('messages.vault.actions.update_description') }}" @if($isArchived) disabled @endif>
                                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M12 20h9" />
                                    <path d="M16.5 3.5a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5z" />
                                </svg>
                            </button>
                        @endif
                    </div>

                    @if ($vaultDescriptionHtml)
                        <article class="markdown-content max-w-none rounded-lg border border-base-300 bg-base-100/40 p-4">
                            {!! $vaultDescriptionHtml !!}
                        </article>
                    @else
                        <p class="text-sm text-base-content/60">{{ __('messages.vault.workspace.overview_empty') }}</p>
                    @endif

                    @if ($isVaultOwner)
                        <div id="vault-description-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-base-100/80 p-4 backdrop-blur-sm" aria-hidden="true">
                            <div class="w-full max-w-2xl rounded-2xl border border-base-300 bg-base-200 p-5 shadow-xl">
                                <div class="mb-4 flex items-center justify-between">
                                    <h4 class="text-base font-semibold text-base-content">{{ __('messages.vault.actions.update_description') }}</h4>
                                    <button type="button" data-close-vault-description-modal class="rounded-md border border-base-300 bg-base-300 px-3 py-1 text-xs text-base-content hover:bg-base-300/80">{{ __('messages.user.settings.cancel') }}</button>
                                </div>

                                <form id="vault-description-form" method="post" action="{{ route('vault.description.update') }}" class="space-y-3">
                                    @csrf
                                    <label class="block text-sm">
                                        <span class="mb-1 block font-medium text-base-content/90">{{ __('messages.vault.workspace.title') }}</span>
                                        <input type="text" name="title" value="{{ old('title', $vaultName) }}" maxlength="255" class="w-full rounded-md border border-base-300 bg-base-100 px-3 py-2 text-base-content" @if($isArchived) disabled @endif />
                                    </label>

                                    <label class="block text-sm">
                                        <span class="mb-1 block font-medium text-base-content/90">{{ __('messages.vault.workspace.description_markdown') }}</span>
                                        <textarea id="vault-description-field" name="description" rows="10" maxlength="3000" class="w-full rounded-md border border-base-300 bg-base-100 px-3 py-2 text-base-content" @if($isArchived) disabled @endif>{{ old('description', $vaultDescription ?? '') }}</textarea>
                                    </label>

                                    <div class="flex items-center justify-end gap-2 pt-2">
                                        <button type="button" data-close-vault-description-modal class="rounded-md border border-base-300 bg-base-300 px-4 py-2 text-sm text-base-content hover:bg-base-300/80">{{ __('messages.user.settings.cancel') }}</button>
                                        <button type="submit" class="rounded-md bg-success px-4 py-2 text-sm font-medium text-success-content hover:opacity-90 disabled:opacity-50 cursor-pointer select-text" @if($isArchived) disabled @endif>
                                            {{ __('messages.vault.actions.update_description') }}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <script>
                            (() => {
                                const openButton = document.getElementById('open-vault-description-modal');
                                const modal = document.getElementById('vault-description-modal');
                                const descriptionField = document.getElementById('vault-description-field');
                                let descriptionEditor = null;

                                if (!openButton || !modal || !descriptionField) {
                                    return;
                                }

                                const closeButtons = modal.querySelectorAll('[data-close-vault-description-modal]');

                                const initializeEditor = () => {
                                    if (descriptionEditor !== null || typeof window.SimpleMDE === 'undefined') {
                                        return;
                                    }

                                    descriptionEditor = new window.SimpleMDE({
                                        element: descriptionField,
                                        forceSync: true,
                                        autoDownloadFontAwesome: false,
                                        spellChecker: false,
                                        status: false,
                                    });
                                };

                                const openModal = () => {
                                    modal.classList.remove('hidden');
                                    modal.classList.add('flex');
                                    modal.setAttribute('aria-hidden', 'false');
                                    initializeEditor();
                                };

                                const closeModal = () => {
                                    modal.classList.add('hidden');
                                    modal.classList.remove('flex');
                                    modal.setAttribute('aria-hidden', 'true');
                                };

                                openButton.addEventListener('click', openModal);
                                closeButtons.forEach((button) => button.addEventListener('click', closeModal));

                                modal.addEventListener('click', (event) => {
                                    if (event.target === modal) {
                                        closeModal();
                                    }
                                });

                                document.addEventListener('keydown', (event) => {
                                    if (event.key === 'Escape' && modal.classList.contains('flex')) {
                                        closeModal();
                                    }
                                });

                                if (@json($errors->has('description') || $errors->has('title'))) {
                                    openModal();
                                }

                                window.addEventListener('load', () => {
                                    if (!modal.classList.contains('hidden')) {
                                        initializeEditor();
                                    }
                                }, { once: true });
                            })();
                        </script>
                    @endif
                </section>
            @else
                <section class="rounded-xl border border-base-300 bg-base-200 p-6 shadow-sm h-full overflow-y-auto">
                    @if ($selectedEntry)
                        <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
                            <h3 class="text-lg font-semibold text-base-content">#{{ $selectedEntry->sequence_no }} {{ $selectedEntryTitle }}</h3>
                            <button id="open-entry-attachments" type="button" class="rounded-md border border-base-300 bg-base-300 px-3 py-2 text-xs font-medium text-base-content hover:bg-base-300/80 cursor-pointer">
                                {{ __('messages.vault.workspace.entry_attachments') }} ({{ count($selectedEntryAttachments) }})
                            </button>
                        </div>
                        <p class="mb-3 text-xs text-base-content/60">
                            {{ __('messages.vault.workspace.sequence_line', ['nickname' => $selectedEntryAuthor, 'entry_date' => $selectedEntry->entry_date?->format('Y-m-d') ?? $selectedEntry->finalized_at->format('Y-m-d'), 'date' => $selectedEntry->finalized_at->format('Y-m-d H:i')]) }}
                        </p>
                        <div class="min-h-0 flex-1 overflow-y-auto">
                            <article class="markdown-content max-w-none rounded-lg border border-base-300 bg-base-100/40 p-4">
                                {!! $selectedEntryHtml !!}
                            </article>
                        </div>

                        <div id="entry-attachments-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-base-100/80 p-4 backdrop-blur-sm" aria-hidden="true">
                            <div class="w-full max-w-2xl rounded-2xl border border-base-300 bg-base-200 p-5 shadow-xl">
                                <div class="mb-4 flex items-center justify-between">
                                    <h4 class="text-base font-semibold text-base-content">{{ __('messages.vault.workspace.entry_attachments') }}</h4>
                                    <button type="button" data-close-entry-attachments class="rounded-md border border-base-300 bg-base-300 px-3 py-1 text-xs text-base-content hover:bg-base-300/80">{{ __('messages.user.settings.cancel') }}</button>
                                </div>

                                @if ($selectedEntryAttachments)
                                    <ul class="space-y-2">
                                        @foreach ($selectedEntryAttachments as $attachment)
                                            <li class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-base-300 px-3 py-2 text-sm">
                                                <span class="text-base-content/85">{{ $attachment['filename'] }} ({{ number_format($attachment['size_bytes'] / 1024, 1) }} KB)</span>
                                                <a href="{{ $attachment['download_url'] }}" class="rounded-md bg-primary px-3 py-1.5 text-xs font-medium text-primary-content hover:opacity-90">{{ __('messages.vault.actions.download_attachment') }}</a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p class="text-sm text-base-content/60">{{ __('messages.vault.workspace.no_entry_attachments') }}</p>
                                @endif
                            </div>
                        </div>

                        <script>
                            (() => {
                                const openButton = document.getElementById('open-entry-attachments');
                                const modal = document.getElementById('entry-attachments-modal');

                                if (!openButton || !modal) {
                                    return;
                                }

                                const closeButtons = modal.querySelectorAll('[data-close-entry-attachments]');

                                const openModal = () => {
                                    modal.classList.remove('hidden');
                                    modal.classList.add('flex');
                                    modal.setAttribute('aria-hidden', 'false');
                                };

                                const closeModal = () => {
                                    modal.classList.add('hidden');
                                    modal.classList.remove('flex');
                                    modal.setAttribute('aria-hidden', 'true');
                                };

                                openButton.addEventListener('click', openModal);
                                closeButtons.forEach((button) => button.addEventListener('click', closeModal));

                                modal.addEventListener('click', (event) => {
                                    if (event.target === modal) {
                                        closeModal();
                                    }
                                });

                                document.addEventListener('keydown', (event) => {
                                    if (event.key === 'Escape' && modal.classList.contains('flex')) {
                                        closeModal();
                                    }
                                });
                            })();
                        </script>
                    @else
                        <p class="text-sm text-base-content/60">{{ __('messages.vault.workspace.select_entry_hint') }}</p>
                    @endif
                </section>
            @endif
        </main>
    </div>
</x-layouts.app>
