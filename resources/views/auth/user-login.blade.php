@php($pendingEmail = session('auth.pending_email'))
@php($supportedLocales = (array) config('cryptosik.locales', ['en']))
@php($hasViteHot = file_exists(public_path('hot')))
@php($hasViteManifest = file_exists(public_path('build/manifest.json')))

<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="default-dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Crypt(o-st)osik</title>
    @if ($hasViteHot || $hasViteManifest)
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-screen text-base-content">
<main class="mx-auto flex min-h-screen w-full max-w-md items-center justify-center px-4 py-8">
    <section class="w-full rounded-2xl border border-base-300 bg-base-200/85 p-6 shadow-xl backdrop-blur-sm">
        <div class="mb-4 flex items-center gap-3">
            <img src="{{ asset('images/logo.png') }}" alt="Cryptosik logo" class="h-12 w-12 object-contain" />
            <h1 class="text-2xl font-semibold tracking-tight">Crypt(o-st)osik</h1>
        </div>

        @if (!($hasViteHot || $hasViteManifest))
            <div class="mb-4 rounded-lg border border-warning bg-warning/10 px-4 py-3 text-sm text-warning-content">
                {{ __('messages.app.assets_missing') }}
            </div>
        @endif

        @if (session('status'))
            <div class="mb-4 rounded-lg border border-success/70 bg-base-200 px-4 py-3 text-sm font-medium text-base-content shadow-sm">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-error/80 bg-base-200 px-4 py-3 text-sm text-base-content shadow-sm">
                <ul class="list-disc pl-5 marker:text-error">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (!$pendingEmail)
            <form method="post" action="{{ route('auth.user.login.request-code') }}" class="space-y-4">
                @csrf
                <label class="block text-sm">
                    <span class="mb-1 block font-medium text-base-content/90">{{ __('messages.auth.user_login.email') }}</span>
                    <input
                        type="email"
                        name="email"
                        required
                        value="{{ old('email') }}"
                        class="w-full rounded-md border border-base-300 bg-base-100 px-3 py-2 text-base-content"
                    />
                </label>
                <button type="submit" class="w-full rounded-md bg-success px-4 py-2 text-success-content hover:opacity-90">
                    {{ __('messages.auth.user_login.send_code') }}
                </button>
            </form>
        @else
            <h2 class="mb-2 text-base font-semibold text-base-content/90">{{ __('messages.auth.user_login.step2_title') }}</h2>
            <p class="mb-4 text-sm text-base-content/70">{{ __('messages.auth.user_login.step2_hint', ['email' => $pendingEmail]) }}</p>

            <form method="post" action="{{ route('auth.user.login.verify-code') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="email" value="{{ $pendingEmail }}" />
                <label class="block text-sm">
                    <span class="mb-1 block font-medium text-base-content/90">{{ __('messages.auth.user_login.otp') }}</span>
                    <input
                        type="text"
                        name="code"
                        pattern="\d{6}"
                        maxlength="6"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        required
                        class="w-full rounded-md border border-base-300 bg-base-100 px-3 py-2 text-base-content"
                    />
                </label>
                <button type="submit" class="w-full rounded-md bg-success px-4 py-2 text-success-content hover:opacity-90">
                    {{ __('messages.auth.user_login.verify_continue') }}
                </button>
                <a href="{{ route('auth.user.login.show', ['reset' => 1]) }}" class="block w-full rounded-md border border-base-300 bg-base-300 px-4 py-2 text-center text-sm text-base-content hover:bg-base-300">
                    {{ __('messages.auth.user_login.change_email') }}
                </a>
            </form>

            @if (session('dev_code'))
                <p class="mt-4 rounded-md border border-warning/80 bg-warning/15 px-3 py-2 text-sm font-medium text-warning">
                    {{ __('messages.auth.user_login.dev_mode_code', ['code' => session('dev_code')]) }}
                </p>
            @endif
        @endif

        <form method="post" action="{{ route('locale.update') }}" class="mt-6 border-t border-base-300 pt-4">
            @csrf
            <label class="block text-center text-xs uppercase tracking-wide text-base-content/60">{{ __('messages.auth.user_login.language') }}</label>
            <select name="locale" onchange="this.form.submit()" class="mx-auto mt-2 block w-1/4 min-w-[120px] rounded-md border border-base-300 bg-base-100 px-3 py-2 text-sm text-base-content">
                @foreach ($supportedLocales as $locale)
                    <option value="{{ $locale }}" @selected(app()->getLocale() === $locale)>
                        {{ __('messages.locales.'.$locale) }}
                    </option>
                @endforeach
            </select>
        </form>
    </section>
</main>
</body>
</html>
