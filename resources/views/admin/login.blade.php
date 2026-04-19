<x-layouts.app :is-admin="true">
    <section class="mx-auto max-w-xl rounded-xl border border-base-300 bg-base-200 p-6 shadow-sm">
        <h2 class="mb-2 text-lg font-semibold text-base-content">{{ __('messages.admin.login.title') }}</h2>
        <p class="mb-4 text-sm text-base-content/70">{{ __('messages.admin.login.hint') }}</p>

        <form method="post" action="{{ route('admin.login.submit') }}" class="space-y-4">
            @csrf
            <label class="block text-sm">
                <span class="mb-1 block font-medium text-base-content/90">{{ __('messages.admin.login.login') }}</span>
                <input type="text" name="login" required value="{{ old('login') }}"
                       class="w-full rounded-md border border-base-300 bg-base-100 px-3 py-2 text-base-content" />
            </label>
            <label class="block text-sm">
                <span class="mb-1 block font-medium text-base-content/90">{{ __('messages.admin.login.password') }}</span>
                <input type="password" name="password" required class="w-full rounded-md border border-base-300 bg-base-100 px-3 py-2 text-base-content" />
            </label>
            <button type="submit" class="rounded-md bg-primary px-4 py-2 text-primary-content hover:opacity-90">{{ __('messages.admin.login.sign_in') }}</button>
        </form>
    </section>
</x-layouts.app>
