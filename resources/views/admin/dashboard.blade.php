<x-layouts.admin section="dashboard" :title="__('messages.admin.dashboard.title')">
    <section class="grid gap-4 md:grid-cols-3">
        <article class="rounded-xl border border-base-300 bg-base-200 p-5 shadow-sm">
            <p class="text-sm text-base-content/70">{{ __('messages.admin.dashboard.users') }}</p>
            <p class="text-2xl font-semibold text-base-content">{{ $usersCount }}</p>
        </article>
        <article class="rounded-xl border border-base-300 bg-base-200 p-5 shadow-sm">
            <p class="text-sm text-base-content/70">{{ __('messages.admin.dashboard.vaults') }}</p>
            <p class="text-2xl font-semibold text-base-content">{{ $vaultsCount }}</p>
        </article>
        <article class="rounded-xl border border-base-300 bg-base-200 p-5 shadow-sm">
            <p class="text-sm text-base-content/70">{{ __('messages.admin.dashboard.entries') }}</p>
            <p class="text-2xl font-semibold text-base-content">{{ $entriesCount }}</p>
        </article>
    </section>

    <section class="rounded-xl border border-base-300 bg-base-200 p-6 shadow-sm">
        <h3 class="mb-3 text-base font-semibold text-base-content">{{ __('messages.admin.dashboard.latest_users') }}</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm text-base-content/90">
                <thead>
                <tr class="border-b border-base-300 text-base-content/70">
                    <th class="px-3 py-2">ID</th>
                    <th class="px-3 py-2">{{ __('messages.admin.users.email') }}</th>
                    <th class="px-3 py-2">{{ __('messages.admin.users.nickname') }}</th>
                    <th class="px-3 py-2">{{ __('messages.admin.users.active') }}</th>
                    <th class="px-3 py-2">{{ __('messages.admin.users.created') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($latestUsers as $user)
                    <tr class="border-b border-base-300">
                        <td class="px-3 py-2">{{ $user->id }}</td>
                        <td class="px-3 py-2">{{ $user->email }}</td>
                        <td class="px-3 py-2">{{ $user->displayName() }}</td>
                        <td class="px-3 py-2">{{ $user->is_active ? __('messages.common.yes') : __('messages.common.no') }}</td>
                        <td class="px-3 py-2">{{ $user->created_at?->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-3 py-3 text-base-content/60">{{ __('messages.admin.dashboard.no_users') }}</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="rounded-xl border border-base-300 bg-base-200 p-6 shadow-sm">
        <h3 class="mb-3 text-base font-semibold text-base-content">{{ __('messages.admin.dashboard.latest_vaults') }}</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm text-base-content/90">
                <thead>
                <tr class="border-b border-base-300 text-base-content/70">
                    <th class="px-3 py-2">ID</th>
                    <th class="px-3 py-2">{{ __('messages.admin.vaults.owner') }}</th>
                    <th class="px-3 py-2">{{ __('messages.admin.vaults.status_label') }}</th>
                    <th class="px-3 py-2">{{ __('messages.admin.vaults.created') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($latestVaults as $vault)
                    <tr class="border-b border-base-300">
                        <td class="px-3 py-2">{{ $vault->id }}</td>
                        <td class="px-3 py-2">{{ $vault->owner?->email ?? 'n/a' }}</td>
                        <td class="px-3 py-2">{{ $vault->status->value }}</td>
                        <td class="px-3 py-2">{{ $vault->created_at?->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-3 py-3 text-base-content/60">{{ __('messages.admin.dashboard.no_vaults') }}</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.admin>
