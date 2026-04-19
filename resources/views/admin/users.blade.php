<x-layouts.admin section="users" :title="__('messages.admin.users.list_title')">
    <section class="rounded-xl border border-base-300 bg-base-200 p-6 shadow-sm">
        <div class="mb-4 flex items-center justify-end">
            <button type="button" data-open-modal="create-user-modal" class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-content hover:opacity-90 cursor-pointer select-text">
                {{ __('messages.admin.users.create_user') }}
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm text-base-content/90">
                <thead>
                <tr class="border-b border-base-300 text-base-content/70">
                    <th class="px-3 py-2">ID</th>
                    <th class="px-3 py-2">{{ __('messages.admin.users.email') }}</th>
                    <th class="px-3 py-2">{{ __('messages.admin.users.nickname') }}</th>
                    <th class="px-3 py-2">{{ __('messages.admin.users.active') }}</th>
                    <th class="px-3 py-2">{{ __('messages.admin.users.created') }}</th>
                    <th class="px-3 py-2">{{ __('messages.admin.users.actions') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($users as $user)
                    <tr class="border-b border-base-300 align-top">
                        <td class="px-3 py-2">{{ $user->id }}</td>
                        <td class="px-3 py-2">{{ $user->email }}</td>
                        <td class="px-3 py-2">
                            <form method="post" action="{{ route('admin.users.nickname.update', $user) }}" class="flex items-center gap-2">
                                @csrf
                                <input
                                    type="text"
                                    name="nickname"
                                    value="{{ $user->displayName() }}"
                                    class="w-48 rounded-md border border-base-300 bg-base-100 px-3 py-1.5 text-sm text-base-content"
                                    required
                                />
                                <button type="submit" class="rounded-md border border-base-300 bg-base-300 px-3 py-1.5 text-xs text-base-content hover:bg-base-300 cursor-pointer select-text">
                                    {{ __('messages.admin.users.update_nickname') }}
                                </button>
                            </form>
                        </td>
                        <td class="px-3 py-2">{{ $user->is_active ? __('messages.common.yes') : __('messages.common.no') }}</td>
                        <td class="px-3 py-2">{{ $user->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-3 py-2">
                            @if ($user->is_active)
                                <form method="post" action="{{ route('admin.users.deactivate', $user) }}">
                                    @csrf
                                    <button type="submit" class="rounded-md bg-warning px-3 py-1.5 text-xs font-medium text-warning-content hover:opacity-90 cursor-pointer select-text">
                                        {{ __('messages.admin.users.deactivate') }}
                                    </button>
                                </form>
                            @else
                                <form method="post" action="{{ route('admin.users.activate', $user) }}">
                                    @csrf
                                    <button type="submit" class="rounded-md bg-success px-3 py-1.5 text-xs font-medium text-success-content hover:opacity-90 cursor-pointer select-text">
                                        {{ __('messages.admin.users.activate') }}
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $users->links() }}</div>
    </section>

    <dialog id="create-user-modal" class="modal">
        <div class="modal-box max-w-xl rounded-xl border border-base-300 bg-base-200 text-base-content">
            <h3 class="mb-4 text-lg font-semibold">{{ __('messages.admin.users.create_user') }}</h3>

            <form method="post" action="{{ route('admin.users.store') }}" class="space-y-3">
                @csrf
                <label class="block text-sm">
                    <span class="mb-1 block font-medium text-base-content/90">{{ __('messages.admin.users.email') }}</span>
                    <input type="email" name="email" required value="{{ old('email') }}" class="w-full rounded-md border border-base-300 bg-base-100 px-3 py-2 text-base-content" />
                </label>

                <label class="block text-sm">
                    <span class="mb-1 block font-medium text-base-content/90">{{ __('messages.admin.users.nickname') }}</span>
                    <input type="text" name="nickname" required value="{{ old('nickname') }}" class="w-full rounded-md border border-base-300 bg-base-100 px-3 py-2 text-base-content" />
                </label>

                <label class="flex items-center gap-2 rounded-md border border-base-300 bg-base-100 px-3 py-2 text-sm text-base-content/90">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', '1') === '1')>
                    <span>{{ __('messages.admin.users.active') }}</span>
                </label>

                <div class="flex items-center justify-center gap-2 pt-2">
                    <button type="button" data-close-modal="create-user-modal" class="rounded-md border border-base-300 bg-base-300 px-4 py-2 text-sm text-base-content hover:bg-base-300 cursor-pointer select-text">
                        {{ __('messages.user.settings.cancel') }}
                    </button>
                    <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm text-primary-content hover:opacity-90 cursor-pointer select-text">
                        {{ __('messages.admin.users.create_user') }}
                    </button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>

    <script>
        (() => {
            const openButtons = document.querySelectorAll('[data-open-modal]');
            const closeButtons = document.querySelectorAll('[data-close-modal]');

            openButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    const modalId = button.getAttribute('data-open-modal');
                    const modal = modalId ? document.getElementById(modalId) : null;

                    if (modal && typeof modal.showModal === 'function') {
                        modal.showModal();
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

            if (@json($errors->has('email') || $errors->has('nickname') || $errors->has('is_active'))) {
                const modal = document.getElementById('create-user-modal');
                if (modal && typeof modal.showModal === 'function') {
                    modal.showModal();
                }
            }
        })();
    </script>
</x-layouts.admin>
