<x-layouts.admin section="logs" :title="__('messages.admin.logs.title')">
    <section class="rounded-xl border border-base-300 bg-base-200 p-6 shadow-sm">
        <form method="get" action="{{ route('admin.logs.index') }}" class="mb-4 grid gap-3 md:grid-cols-3">
            <label class="block text-sm">
                <span class="mb-1 block font-medium text-base-content/90">{{ __('messages.admin.logs.actor_type') }}</span>
                <select name="actor_type" class="w-full rounded-md border border-base-300 bg-base-100 px-3 py-2 text-base-content">
                    <option value="">{{ __('messages.admin.logs.any') }}</option>
                    <option value="admin" @selected($actorType === 'admin')>admin</option>
                    <option value="user" @selected($actorType === 'user')>user</option>
                </select>
            </label>

            <label class="block text-sm">
                <span class="mb-1 block font-medium text-base-content/90">{{ __('messages.admin.logs.action_prefix') }}</span>
                <input type="text" name="action" value="{{ $action }}" placeholder="vault." class="w-full rounded-md border border-base-300 bg-base-100 px-3 py-2 text-base-content" />
            </label>

            <div class="flex items-end gap-2">
                <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm text-primary-content hover:opacity-90 cursor-pointer select-text">
                    {{ __('messages.admin.logs.filter') }}
                </button>
                <a href="{{ route('admin.logs.index') }}" class="rounded-md border border-base-300 bg-base-300 px-4 py-2 text-sm text-base-content hover:bg-base-300 cursor-pointer select-text">
                    {{ __('messages.admin.logs.clear') }}
                </a>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm text-base-content/90">
                <thead>
                <tr class="border-b border-base-300 text-base-content/70">
                    <th class="px-3 py-2">{{ __('messages.admin.logs.time') }}</th>
                    <th class="px-3 py-2">{{ __('messages.admin.logs.actor') }}</th>
                    <th class="px-3 py-2">{{ __('messages.admin.logs.action') }}</th>
                    <th class="px-3 py-2">{{ __('messages.admin.logs.target') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($logs as $log)
                    <tr class="border-b border-base-300">
                        <td class="px-3 py-2">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                        <td class="px-3 py-2">{{ $actorLabels[$log->id] ?? (($log->actor_type ?? 'n/a').'#'.($log->actor_id ?? '-')) }}</td>
                        <td class="px-3 py-2 font-mono text-xs">{{ $log->action }}</td>
                        <td class="px-3 py-2">{{ $log->target_type ?? 'n/a' }}#{{ $log->target_id ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-3 py-3 text-base-content/60">{{ __('messages.admin.logs.empty') }}</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->lastPage() > 1)
            @php($currentPage = $logs->currentPage())
            @php($lastPage = $logs->lastPage())
            @php($firstVisiblePage = max(1, $currentPage - 2))
            @php($lastVisiblePage = min($lastPage, $currentPage + 2))

            <nav class="mt-4 flex flex-wrap items-center justify-center gap-2 text-sm" aria-label="{{ __('messages.admin.logs.pagination') }}">
                @if ($logs->onFirstPage())
                    <span class="rounded-md border border-base-300 bg-base-100 px-3 py-2 text-base-content/40">{{ __('messages.admin.logs.previous') }}</span>
                @else
                    <a href="{{ $logs->previousPageUrl() }}" class="rounded-md border border-base-300 bg-base-300 px-3 py-2 text-base-content hover:bg-base-300 cursor-pointer select-text">{{ __('messages.admin.logs.previous') }}</a>
                @endif

                @if ($firstVisiblePage > 1)
                    <a href="{{ $logs->url(1) }}" class="rounded-md border border-base-300 bg-base-300 px-3 py-2 text-base-content hover:bg-base-300 cursor-pointer select-text">1</a>
                    @if ($firstVisiblePage > 2)
                        <span class="px-2 py-2 text-base-content/50">...</span>
                    @endif
                @endif

                @foreach (range($firstVisiblePage, $lastVisiblePage) as $page)
                    @if ($page === $currentPage)
                        <span class="rounded-md border border-primary bg-primary px-3 py-2 text-primary-content" aria-current="page">{{ $page }}</span>
                    @else
                        <a href="{{ $logs->url($page) }}" class="rounded-md border border-base-300 bg-base-300 px-3 py-2 text-base-content hover:bg-base-300 cursor-pointer select-text">{{ $page }}</a>
                    @endif
                @endforeach

                @if ($lastVisiblePage < $lastPage)
                    @if ($lastVisiblePage < $lastPage - 1)
                        <span class="px-2 py-2 text-base-content/50">...</span>
                    @endif
                    <a href="{{ $logs->url($lastPage) }}" class="rounded-md border border-base-300 bg-base-300 px-3 py-2 text-base-content hover:bg-base-300 cursor-pointer select-text">{{ $lastPage }}</a>
                @endif

                @if ($logs->hasMorePages())
                    <a href="{{ $logs->nextPageUrl() }}" class="rounded-md border border-base-300 bg-base-300 px-3 py-2 text-base-content hover:bg-base-300 cursor-pointer select-text">{{ __('messages.admin.logs.next') }}</a>
                @else
                    <span class="rounded-md border border-base-300 bg-base-100 px-3 py-2 text-base-content/40">{{ __('messages.admin.logs.next') }}</span>
                @endif
            </nav>
        @endif
    </section>
</x-layouts.admin>
