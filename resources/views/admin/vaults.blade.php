<x-layouts.admin section="vaults" :title="__('messages.admin.vaults.list_title')">
    <section class="rounded-xl border border-base-300 bg-base-200 p-6 shadow-sm">
        <div class="mb-4 flex items-center justify-end">
            <button type="button" data-open-modal="create-vault-modal" class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-content hover:opacity-90 cursor-pointer select-text">
                {{ __('messages.admin.vaults.create_vault') }}
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm text-base-content/90">
                <thead>
                <tr class="border-b border-base-300 text-base-content/70">
                    <th class="px-3 py-2">ID</th>
                    <th class="px-3 py-2">{{ __('messages.admin.vaults.status_label') }}</th>
                    <th class="px-3 py-2">{{ __('messages.admin.vaults.integrity_label') }}</th>
                    <th class="px-3 py-2">{{ __('messages.admin.vaults.members') }}</th>
                    <th class="px-3 py-2">{{ __('messages.admin.vaults.created') }}</th>
                    <th class="px-3 py-2">{{ __('messages.admin.vaults.actions') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($vaults as $vault)
                    <tr class="border-b border-base-300 align-top">
                        <td class="px-3 py-2">{{ $vault->id }}</td>

                        <td class="px-3 py-2">{{ $vault->status->value }}</td>
                        <td class="px-3 py-2">
                            @php($integrityRun = $vault->latestVerificationRun)
                            @php($integrityResult = $integrityRun?->result?->value)
                            @php($integrityBadgeClass = match ($integrityResult) {
                                'passed' => 'bg-success text-success-content',
                                'failed' => 'bg-error text-error-content',
                                'pending' => 'bg-warning text-warning-content',
                                default => 'bg-base-300 text-base-content/80',
                            })

                            @if ($integrityResult === null)
                                <span class="inline-flex rounded px-2 py-1 text-xs font-medium {{ $integrityBadgeClass }}">
                                    {{ __('messages.admin.vaults.integrity.not_checked') }}
                                </span>
                            @else
                                <span class="inline-flex rounded px-2 py-1 text-xs font-medium {{ $integrityBadgeClass }}">
                                    {{ __('messages.admin.vaults.integrity.result.'.$integrityResult) }}
                                </span>
                                <div class="mt-1 text-xs text-base-content/70">
                                    {{ __('messages.admin.vaults.integrity.checked_at', ['time' => $integrityRun?->finished_at?->format('Y-m-d H:i') ?? $integrityRun?->started_at?->format('Y-m-d H:i') ?? 'n/a']) }}
                                </div>

                                @if ($integrityResult === 'failed' && $integrityRun?->broken_sequence_no !== null)
                                    <div class="mt-1 text-xs text-error">
                                        {{ __('messages.admin.vaults.integrity.failed_sequence', ['sequence' => $integrityRun->broken_sequence_no]) }}
                                    </div>
                                @endif
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            @if ($vault->members->isNotEmpty())
                                @php($sortedMembers = $vault->members->sort(static function ($left, $right): int {
                                    $leftRank = $left->role->value === 'owner' ? 0 : 1;
                                    $rightRank = $right->role->value === 'owner' ? 0 : 1;

                                    if ($leftRank !== $rightRank) {
                                        return $leftRank <=> $rightRank;
                                    }

                                    $leftEmail = mb_strtolower((string) ($left->user?->email ?? ''));
                                    $rightEmail = mb_strtolower((string) ($right->user?->email ?? ''));

                                    return $leftEmail <=> $rightEmail;
                                }))
                                <ul class="space-y-2">
                                    @foreach ($sortedMembers as $member)
                                        <li class="rounded-md border border-base-300 bg-base-100/40 px-2 py-2">
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                <div class="min-w-0">
                                                    <p class="truncate text-sm">
                                                        {{ $member->user?->email ?? __('messages.admin.vaults.member_unknown') }}
                                                        ({{ $member->role->value }})
                                                    </p>
                                                    <p class="text-xs text-base-content/65">
                                                        {{ __('messages.admin.vaults.last_notification') }}:
                                                        {{ $member->membership_notified_at?->format('Y-m-d H:i') ?? __('messages.admin.vaults.never_notified') }}
                                                    </p>
                                                </div>
                                                @if ($member->user !== null)
                                                    <form method="post" action="{{ route('admin.vaults.members.notify', ['vault' => $vault, 'user' => $member->user]) }}">
                                                        @csrf
                                                        <button type="submit" class="rounded-md bg-secondary px-3 py-1.5 text-xs font-medium text-secondary-content hover:opacity-90 cursor-pointer select-text">
                                                            {{ __('messages.admin.vaults.notify_member') }}
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <span class="text-base-content/60">{{ __('messages.admin.vaults.no_members') }}</span>
                            @endif
                        </td>
                        <td class="px-3 py-2">{{ $vault->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-3 py-2">
                            <div class="flex flex-wrap gap-2">
                                <button type="button" data-open-modal="assign-member-{{ $vault->id }}" class="rounded-md border border-base-300 bg-base-300 px-3 py-1.5 text-xs text-base-content hover:bg-base-300 cursor-pointer select-text">
                                    {{ __('messages.admin.vaults.add_member') }}
                                </button>

                                <form method="post" action="{{ route('admin.vaults.archive', $vault) }}">
                                    @csrf
                                    <button type="submit" class="rounded-md bg-warning px-3 py-1.5 text-xs font-medium text-warning-content hover:opacity-90 cursor-pointer select-text">
                                        {{ __('messages.admin.vaults.archive') }}
                                    </button>
                                </form>

                                <form method="post" action="{{ route('admin.vaults.soft-delete', $vault) }}">
                                    @csrf
                                    <button type="submit" class="rounded-md bg-error px-3 py-1.5 text-xs font-medium text-error-content hover:opacity-90 cursor-pointer select-text">
                                        {{ __('messages.admin.vaults.soft_delete') }}
                                    </button>
                                </form>

                                <form method="post" action="{{ route('admin.vaults.restore', $vault->id) }}">
                                    @csrf
                                    <button type="submit" class="rounded-md bg-base-300 px-3 py-1.5 text-xs text-base-content hover:bg-base-300 cursor-pointer select-text">
                                        {{ __('messages.admin.vaults.restore') }}
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $vaults->links() }}</div>
    </section>

    <dialog id="create-vault-modal" class="modal">
        <div class="modal-box max-w-2xl rounded-xl border border-base-300 bg-base-200 text-base-content">
            <h3 class="mb-4 text-lg font-semibold">{{ __('messages.admin.vaults.create_vault') }}</h3>

            <form id="create-vault-form" method="post" action="{{ route('admin.vaults.store') }}" class="space-y-3">
                @csrf
                <label class="block text-sm">
                    <span class="mb-1 block font-medium text-base-content/90">{{ __('messages.admin.vaults.name') }}</span>
                    <input type="text" name="name" required value="{{ old('name') }}" class="w-full rounded-md border border-base-300 bg-base-100 px-3 py-2 text-base-content" />
                </label>

                <label class="block text-sm">
                    <span class="mb-1 block font-medium text-base-content/90">{{ __('messages.admin.vaults.description_markdown') }}</span>
                    <textarea id="create-vault-description-field" name="description" rows="5" class="w-full rounded-md border border-base-300 bg-base-100 px-3 py-2 text-base-content">{{ old('description') }}</textarea>
                </label>

                <label class="block text-sm">
                    <span class="mb-1 block font-medium text-base-content/90">{{ __('messages.admin.vaults.vault_key') }}</span>
                    <input type="text" name="vault_key" required value="{{ old('vault_key') }}" class="w-full rounded-md border border-base-300 bg-base-100 px-3 py-2 text-base-content" />
                </label>

                <label class="block text-sm">
                    <span class="mb-1 block font-medium text-base-content/90">{{ __('messages.admin.vaults.owner') }}</span>
                    <select name="owner_user_id" required class="w-full rounded-md border border-base-300 bg-base-100 px-3 py-2 text-base-content">
                        <option value="">{{ __('messages.admin.vaults.select_owner') }}</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected((string) old('owner_user_id') === (string) $user->id)>
                                {{ $user->email }} @if(!$user->is_active) ({{ __('messages.admin.users.inactive') }}) @endif
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="flex items-center gap-2 rounded-md border border-base-300 bg-base-100/40 px-3 py-2 text-sm">
                    <input type="hidden" name="send_owner_notification_now" value="0" />
                    <input type="checkbox" name="send_owner_notification_now" value="1" @checked(old('send_owner_notification_now', '1') === '1') class="h-4 w-4 accent-primary" />
                    <span>{{ __('messages.admin.vaults.send_owner_notification_now') }}</span>
                </label>

                <div class="flex items-center justify-center gap-2 pt-2">
                    <button type="button" data-close-modal="create-vault-modal" class="rounded-md border border-base-300 bg-base-300 px-4 py-2 text-sm text-base-content hover:bg-base-300 cursor-pointer select-text">
                        {{ __('messages.user.settings.cancel') }}
                    </button>
                    <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm text-primary-content hover:opacity-90 cursor-pointer select-text">
                        {{ __('messages.admin.vaults.create_vault') }}
                    </button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>

    @foreach ($vaults as $vault)
        <dialog id="assign-member-{{ $vault->id }}" class="modal">
            <div class="modal-box max-w-xl rounded-xl border border-base-300 bg-base-200 text-base-content">
                <h3 class="mb-4 text-lg font-semibold">{{ __('messages.admin.vaults.assign_member') }}</h3>

                <form method="post" action="{{ route('admin.vaults.members.assign', $vault) }}" class="space-y-4">
                    @csrf
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium text-base-content/90">{{ __('messages.admin.vaults.select_user') }}</span>
                        <select name="user_id" required class="w-full rounded-md border border-base-300 bg-base-100 px-3 py-2 text-base-content">
                            <option value="">{{ __('messages.admin.vaults.select_user') }}</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}">
                                    {{ $user->email }} @if(!$user->is_active) ({{ __('messages.admin.users.inactive') }}) @endif
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <label class="flex items-center gap-2 rounded-md border border-base-300 bg-base-100/40 px-3 py-2 text-sm">
                        <input type="hidden" name="send_notification_now" value="0" />
                        <input type="checkbox" name="send_notification_now" value="1" checked class="h-4 w-4 accent-primary" />
                        <span>{{ __('messages.admin.vaults.send_notification_now') }}</span>
                    </label>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" data-close-modal="assign-member-{{ $vault->id }}" class="rounded-md border border-base-300 bg-base-300 px-4 py-2 text-sm text-base-content hover:bg-base-300 cursor-pointer select-text">
                            {{ __('messages.user.settings.cancel') }}
                        </button>
                        <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm text-primary-content hover:opacity-90 cursor-pointer select-text">
                            {{ __('messages.admin.vaults.assign_member') }}
                        </button>
                    </div>
                </form>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button>close</button>
            </form>
        </dialog>
    @endforeach

    <script>
        (() => {
            const openButtons = document.querySelectorAll('[data-open-modal]');
            const closeButtons = document.querySelectorAll('[data-close-modal]');
            const createVaultModal = document.getElementById('create-vault-modal');
            const createVaultDescriptionField = document.getElementById('create-vault-description-field');
            let createVaultDescriptionEditor = null;

            const initializeCreateVaultDescriptionEditor = () => {
                if (createVaultDescriptionEditor !== null || !createVaultDescriptionField || typeof window.SimpleMDE === 'undefined') {
                    return;
                }

                createVaultDescriptionEditor = new window.SimpleMDE({
                    element: createVaultDescriptionField,
                    forceSync: true,
                    autoDownloadFontAwesome: false,
                    spellChecker: false,
                    status: false,
                });
            };

            openButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    const modalId = button.getAttribute('data-open-modal');
                    const modal = modalId ? document.getElementById(modalId) : null;

                    if (modal && typeof modal.showModal === 'function') {
                        modal.showModal();

                        if (modalId === 'create-vault-modal') {
                            initializeCreateVaultDescriptionEditor();
                            window.requestAnimationFrame(initializeCreateVaultDescriptionEditor);
                        }
                    }
                });
            });

            closeButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    const modalId = button.getAttribute('data-close-modal');
                    const modal = modalId ? document.getElementById(modalId) : null;

                    if (modal && typeof modal.close === 'function') {
                        modal.close();
                    }
                });
            });

            if (@json($errors->has('owner_user_id') || $errors->has('vault_key') || $errors->has('name') || $errors->has('description') || $errors->has('send_owner_notification_now'))) {
                if (createVaultModal && typeof createVaultModal.showModal === 'function') {
                    createVaultModal.showModal();
                    initializeCreateVaultDescriptionEditor();
                }
            }

            window.addEventListener('load', () => {
                if (createVaultModal?.open) {
                    initializeCreateVaultDescriptionEditor();
                }
            }, { once: true });
        })();
    </script>
</x-layouts.admin>
