<x-layouts.app>
    <div class="flex min-h-[calc(100vh-16rem)] items-center justify-center">
        <section class="w-full max-w-md rounded-2xl border border-base-300 bg-base-200/85 p-6 shadow-xl backdrop-blur-sm">
            <h2 class="mb-2 text-center text-lg font-semibold text-base-content">{{ __('messages.auth.vault_unlock.title') }}</h2>
            <p class="mb-5 text-center text-sm text-base-content/70">
                {{ __('messages.auth.vault_unlock.hint') }}
            </p>

            <form method="post" action="{{ route('auth.vault.unlock.submit') }}" class="space-y-4">
                @csrf
                <label class="block text-sm">
                    <span class="mb-1 block font-medium text-base-content/90">{{ __('messages.auth.vault_unlock.vault_key') }}</span>
                    <input type="password" name="vault_key" required class="w-full rounded-md border border-base-300 bg-base-100 px-3 py-2 text-base-content" />
                </label>
                <button type="submit" class="w-full rounded-md bg-success px-4 py-2 text-success-content hover:opacity-90">{{ __('messages.auth.vault_unlock.unlock') }}</button>
            </form>
        </section>
    </div>
</x-layouts.app>
