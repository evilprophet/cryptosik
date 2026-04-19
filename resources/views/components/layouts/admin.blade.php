@props([
    'section' => 'dashboard',
    'title' => null,
])

<x-layouts.app :is-admin="true">
    <div class="grid gap-4 lg:grid-cols-[15rem_minmax(0,1fr)]">
        <aside class="rounded-xl border border-base-300 bg-base-200/90 p-4 shadow-sm backdrop-blur-sm">
            <p class="mb-3 text-xs uppercase tracking-[0.16em] text-base-content/60">{{ __('messages.admin.common.panel') }}</p>

            <nav class="space-y-2 text-sm">
                <a href="{{ route('admin.dashboard') }}" @class([
                    'block rounded-md px-3 py-2 transition cursor-pointer select-text',
                    'bg-primary text-primary-content' => $section === 'dashboard',
                    'bg-base-300 text-base-content hover:bg-base-300' => $section !== 'dashboard',
                ])>
                    {{ __('messages.admin.sidebar.dashboard') }}
                </a>
                <a href="{{ route('admin.users.index') }}" @class([
                    'block rounded-md px-3 py-2 transition cursor-pointer select-text',
                    'bg-primary text-primary-content' => $section === 'users',
                    'bg-base-300 text-base-content hover:bg-base-300' => $section !== 'users',
                ])>
                    {{ __('messages.admin.sidebar.users') }}
                </a>
                <a href="{{ route('admin.vaults.index') }}" @class([
                    'block rounded-md px-3 py-2 transition cursor-pointer select-text',
                    'bg-primary text-primary-content' => $section === 'vaults',
                    'bg-base-300 text-base-content hover:bg-base-300' => $section !== 'vaults',
                ])>
                    {{ __('messages.admin.sidebar.vaults') }}
                </a>
                <a href="{{ route('admin.logs.index') }}" @class([
                    'block rounded-md px-3 py-2 transition cursor-pointer select-text',
                    'bg-primary text-primary-content' => $section === 'logs',
                    'bg-base-300 text-base-content hover:bg-base-300' => $section !== 'logs',
                ])>
                    {{ __('messages.admin.sidebar.logs') }}
                </a>
            </nav>
        </aside>

        <main class="min-w-0 space-y-4">
            @if ($title)
                <section class="rounded-xl border border-base-300 bg-base-200/90 px-5 py-4 shadow-sm backdrop-blur-sm">
                    <h2 class="text-lg font-semibold text-base-content">{{ $title }}</h2>
                </section>
            @endif

            {{ $slot }}
        </main>
    </div>
</x-layouts.app>
