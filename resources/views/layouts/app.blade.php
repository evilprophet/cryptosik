<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="default-dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Crypt(o-st)osik</title>
    @php($hasViteHot = file_exists(public_path('hot')))
    @php($hasViteManifest = file_exists(public_path('build/manifest.json')))
    @if ($hasViteHot || $hasViteManifest)
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    @livewireStyles
</head>
<body @class(['min-h-screen', 'app-bg-admin' => $isAdmin ?? false])>
@php($headerCenterTitle = $headerCenterTitle ?? null)
@php($showVaultLock = $showVaultLock ?? false)
<div class="mx-auto w-full px-4 py-6 lg:w-2/3">
    @if (!($hasViteHot || $hasViteManifest))
        <div class="mb-4 rounded-lg border border-warning bg-warning/10 px-4 py-3 text-sm text-warning-content">
            {{ __('messages.app.assets_missing') }}
        </div>
    @endif

    @php($supportedLocales = (array) config('cryptosik.locales', ['en']))

    <header class="mb-6 rounded-xl border border-base-300 bg-base-200/85 px-5 py-4 shadow-sm backdrop-blur-sm">
        <div @class([
            'w-full gap-3',
            'grid grid-cols-[1fr_auto_1fr] items-center' => $headerCenterTitle,
            'flex flex-wrap items-center justify-between' => !$headerCenterTitle,
        ])>
            <div @class([
                'flex items-center gap-3',
                'justify-self-start' => $headerCenterTitle,
            ])>
                <img src="{{ asset('images/logo.png') }}" alt="Cryptosik logo" class="h-10 w-10 object-contain" />
                <h1 class="text-xl font-semibold text-base-content">Crypt(o-st)osik</h1>
            </div>

            @if ($headerCenterTitle)
                <h2 class="max-w-[28rem] truncate text-center text-lg font-semibold text-base-content">
                    {{ $headerCenterTitle }}
                </h2>
            @endif

            <nav @class([
                'flex flex-wrap items-center gap-2 text-sm',
                'justify-self-end' => $headerCenterTitle,
            ])>
                <form method="post" action="{{ route('locale.update') }}" class="inline">
                    @csrf
                    <select name="locale" onchange="this.form.submit()" class="rounded-md border border-base-300 bg-base-100 px-3 py-2 text-base-content">
                        @foreach ($supportedLocales as $locale)
                            <option value="{{ $locale }}" @selected(app()->getLocale() === $locale)>
                                {{ __('messages.locales.'.$locale) }}
                            </option>
                        @endforeach
                    </select>
                </form>

                @if ($showVaultLock && session()->has(\EvilStudio\Cryptosik\Support\SessionKeys::USER_ID))
                    <form method="post" action="{{ route('vault.lock') }}" class="inline">
                        @csrf
                        <button class="rounded-md border border-base-300 bg-base-300 px-3 py-2 text-base-content hover:bg-base-300" type="submit">{{ __('messages.vault.workspace.lock_vault') }}</button>
                    </form>
                @endif

                @if (session()->has(\EvilStudio\Cryptosik\Support\SessionKeys::USER_ID))
                    <form method="post" action="{{ route('auth.user.logout') }}" class="inline">
                        @csrf
                        <button class="rounded-md border border-base-300 bg-base-300 px-3 py-2 text-base-content hover:bg-base-300" type="submit">{{ __('messages.nav.logout') }}</button>
                    </form>
                @endif

                @if (session()->has(\EvilStudio\Cryptosik\Support\SessionKeys::ADMIN_ID))
                    <form method="post" action="{{ route('admin.logout') }}" class="inline">
                        @csrf
                        <button class="rounded-md border border-base-300 bg-base-300 px-3 py-2 text-base-content hover:bg-base-300" type="submit">{{ __('messages.nav.logout') }}</button>
                    </form>
                @endif
            </nav>
        </div>
    </header>

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

    {{ $slot }}
</div>

@livewireScripts
</body>
</html>
