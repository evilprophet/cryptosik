@props([
    'headerCenterTitle' => null,
    'headerCenterHref' => null,
    'showVaultLock' => false,
    'isAdmin' => false,
])

@php($supportedLocales = (array) config('cryptosik.locales', ['en']))
@php($isUserAuthenticated = session()->has(\EvilStudio\Cryptosik\Support\SessionKeys::USER_ID))
@php($isAdminAuthenticated = session()->has(\EvilStudio\Cryptosik\Support\SessionKeys::ADMIN_ID))
@php($showUserControls = $isUserAuthenticated && (!$isAdmin || !$isAdminAuthenticated))
@php($showAdminControls = $isAdminAuthenticated && ($isAdmin || !$isUserAuthenticated))
@php($showSettingsControls = $showUserControls || $showAdminControls)
@php($nicknameLimit = (int) config('cryptosik.limits.user_nickname_chars', 80))
@php($sessionNickname = trim((string) session(\EvilStudio\Cryptosik\Support\SessionKeys::USER_NICKNAME, '')))
@php($sessionUserEmail = trim((string) session(\EvilStudio\Cryptosik\Support\SessionKeys::USER_EMAIL, '')))
@php($sessionAdminLogin = trim((string) session(\EvilStudio\Cryptosik\Support\SessionKeys::ADMIN_LOGIN, '')))
@php($headerIdentity = $showUserControls ? ($sessionNickname !== '' ? $sessionNickname : $sessionUserEmail) : ($showAdminControls ? ($sessionAdminLogin !== '' ? $sessionAdminLogin : 'admin') : ''))
@php($shouldOpenSettingsModal = ($showUserControls && ($errors->has('nickname') || $errors->has('locale'))) || ($showAdminControls && $errors->has('locale')))

<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="default-dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Crypt(o-st)osik</title>
    @php($hasViteHot = file_exists(public_path('hot')))
    @php($hasViteManifest = file_exists(public_path('build/manifest.json')))
    @php($hasFontAwesomeCss = file_exists(public_path('vendor/font-awesome/css/font-awesome.min.css')))
    @php($hasSimpleMdeCss = file_exists(public_path('vendor/simplemde/simplemde.min.css')))
    @php($hasSimpleMdeJs = file_exists(public_path('vendor/simplemde/simplemde.min.js')))
    @if ($hasFontAwesomeCss)
        <link rel="stylesheet" href="{{ asset('vendor/font-awesome/css/font-awesome.min.css') }}">
    @endif
    @if ($hasSimpleMdeCss)
        <link rel="stylesheet" href="{{ asset('vendor/simplemde/simplemde.min.css') }}">
    @endif
    @if ($hasViteHot || $hasViteManifest)
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    @livewireStyles
</head>
<body @class(['min-h-screen', 'app-bg-admin' => $isAdmin])>
<div class="mx-auto w-full px-4 py-6 lg:w-2/3">
    @if (!($hasViteHot || $hasViteManifest))
        <div class="mb-4 rounded-lg border border-warning bg-warning/10 px-4 py-3 text-sm text-warning-content">
            {{ __('messages.app.assets_missing') }}
        </div>
    @endif

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
                @if ($headerCenterHref)
                    <a href="{{ $headerCenterHref }}" class="max-w-[28rem] truncate text-center text-lg font-semibold text-base-content hover:opacity-90 cursor-pointer select-text">
                        {{ $headerCenterTitle }}
                    </a>
                @else
                    <h2 class="max-w-[28rem] truncate text-center text-lg font-semibold text-base-content">
                        {{ $headerCenterTitle }}
                    </h2>
                @endif
            @endif

            <nav @class([
                'flex flex-wrap items-center gap-2 text-sm',
                'justify-self-end' => $headerCenterTitle,
            ])>
                @if ($showSettingsControls)
                    @if ($headerIdentity !== '')
                        <span class="rounded-md border border-base-300 bg-base-100 px-3 py-2 text-base-content/80">{{ $headerIdentity }}</span>
                    @endif
                    <button id="open-settings-modal" class="rounded-md border border-base-300 bg-base-300 px-3 py-2 text-base-content hover:bg-base-300 cursor-pointer select-text" type="button">
                        {{ __('messages.nav.settings') }}
                    </button>
                @endif

                @if ($showVaultLock && $showUserControls)
                    <form method="post" action="{{ route('vault.lock') }}" class="inline">
                        @csrf
                        <button class="rounded-md border border-base-300 bg-base-300 px-3 py-2 text-base-content hover:bg-base-300 cursor-pointer select-text" type="submit">{{ __('messages.vault.workspace.lock_vault') }}</button>
                    </form>
                @endif

                @if ($showUserControls)
                    <form method="post" action="{{ route('auth.user.logout') }}" class="inline">
                        @csrf
                        <button class="rounded-md border border-base-300 bg-base-300 px-3 py-2 text-base-content hover:bg-base-300 cursor-pointer select-text" type="submit">{{ __('messages.nav.logout') }}</button>
                    </form>
                @elseif ($showAdminControls)
                    <form method="post" action="{{ route('admin.logout') }}" class="inline">
                        @csrf
                        <button class="rounded-md border border-base-300 bg-base-300 px-3 py-2 text-base-content hover:bg-base-300 cursor-pointer select-text" type="submit">{{ __('messages.nav.logout') }}</button>
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

@if ($showSettingsControls)
    <div id="settings-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-base-100/80 p-4 backdrop-blur-sm" aria-hidden="true">
        <div class="w-full max-w-md rounded-2xl border border-base-300 bg-base-200 p-5 shadow-xl">
            <h3 class="mb-4 text-lg font-semibold text-base-content">{{ __('messages.user.settings.title') }}</h3>

            <form method="post" action="{{ $showUserControls ? route('user.settings.update') : route('locale.update') }}" class="space-y-4">
                @csrf
                @if ($showUserControls)
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium text-base-content/90">{{ __('messages.user.settings.nickname') }}</span>
                        <input type="text" name="nickname" value="{{ old('nickname', $sessionNickname) }}" maxlength="{{ $nicknameLimit }}" required class="w-full rounded-md border border-base-300 bg-base-100 px-3 py-2 text-base-content" />
                    </label>
                @endif

                <label class="block text-sm">
                    <span class="mb-1 block font-medium text-base-content/90">{{ __('messages.user.settings.language') }}</span>
                    <select name="locale" class="w-full rounded-md border border-base-300 bg-base-100 px-3 py-2 text-base-content">
                        @foreach ($supportedLocales as $locale)
                            <option value="{{ $locale }}" @selected((old('locale') ?? app()->getLocale()) === $locale)>
                                {{ __('messages.locales.'.$locale) }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" data-close-settings-modal class="rounded-md border border-base-300 bg-base-300 px-4 py-2 text-sm text-base-content hover:bg-base-300 cursor-pointer select-text">{{ __('messages.user.settings.cancel') }}</button>
                    <button type="submit" class="rounded-md bg-success px-4 py-2 text-sm text-success-content hover:opacity-90 cursor-pointer select-text">{{ __('messages.user.settings.save') }}</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (() => {
            const modal = document.getElementById('settings-modal');
            const openButton = document.getElementById('open-settings-modal');

            if (!modal || !openButton) {
                return;
            }

            const closeButtons = modal.querySelectorAll('[data-close-settings-modal]');

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

            if (@json($shouldOpenSettingsModal)) {
                openModal();
            }
        })();
    </script>
@endif

@if ($hasSimpleMdeJs)
    <script src="{{ asset('vendor/simplemde/simplemde.min.js') }}" defer></script>
@endif

@livewireScripts
</body>
</html>
