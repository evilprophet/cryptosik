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
@php($sessionNotificationsEnabled = (string) session(\EvilStudio\Cryptosik\Support\SessionKeys::USER_NOTIFICATIONS_ENABLED, '1'))
@php($headerIdentity = $showUserControls ? ($sessionNickname !== '' ? $sessionNickname : $sessionUserEmail) : ($showAdminControls ? ($sessionAdminLogin !== '' ? $sessionAdminLogin : 'admin') : ''))
@php($shouldOpenSettingsModal = ($showUserControls && ($errors->has('nickname') || $errors->has('locale') || $errors->has('notifications_enabled'))) || ($showAdminControls && $errors->has('locale')))
@php($hasMobileSidebar = isset($mobileSidebar))

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
<div class="mx-auto w-full px-3 py-4 lg:w-2/3 lg:px-4 lg:py-6">
    @if (!($hasViteHot || $hasViteManifest))
        <div class="mb-4 rounded-lg border border-warning bg-warning/10 px-4 py-3 text-sm text-warning-content">
            {{ __('messages.app.assets_missing') }}
        </div>
    @endif

    @if ($hasMobileSidebar)
        <header class="mb-3 grid grid-cols-[auto_1fr_auto] items-center gap-3 rounded-xl border border-base-300 bg-base-200/85 px-3 py-3 shadow-sm backdrop-blur-sm lg:hidden">
            <button type="button" data-open-mobile-sidebar class="rounded-md border border-base-300 bg-base-300 px-3 py-2 text-xs font-medium text-base-content hover:bg-base-300 cursor-pointer select-text">
                {{ __('messages.nav.menu') }}
            </button>

            @if ($headerCenterHref)
                <a href="{{ $headerCenterHref }}" class="min-w-0 justify-self-center inline-flex items-center gap-2 text-base-content hover:opacity-90 cursor-pointer select-text">
                    <img src="{{ asset('images/logo.png') }}" alt="Cryptosik logo" class="h-8 w-8 shrink-0 object-contain" />
                    <span class="truncate text-base font-semibold">{{ $headerCenterTitle ?? 'Crypt(o-st)osik' }}</span>
                </a>
            @else
                <div class="min-w-0 justify-self-center inline-flex items-center gap-2 text-base-content">
                    <img src="{{ asset('images/logo.png') }}" alt="Cryptosik logo" class="h-8 w-8 shrink-0 object-contain" />
                    <span class="truncate text-base font-semibold">{{ $headerCenterTitle ?? 'Crypt(o-st)osik' }}</span>
                </div>
            @endif

            <span class="w-14" aria-hidden="true"></span>
        </header>
    @endif

    <header @class([
        'mb-6 rounded-xl border border-base-300 bg-base-200/85 px-5 py-4 shadow-sm backdrop-blur-sm',
        'hidden lg:block' => $hasMobileSidebar,
    ])>
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
                    <button id="open-settings-modal" data-open-settings-modal class="rounded-md border border-base-300 bg-base-300 px-3 py-2 text-base-content hover:bg-base-300 cursor-pointer select-text" type="button">
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
        <div data-dismissible-flash class="mb-4 flex items-start justify-between gap-3 rounded-lg border border-success/70 bg-base-200 px-4 py-3 text-sm font-medium text-base-content shadow-sm">
            <span>{{ session('status') }}</span>
            <button type="button" data-dismiss-flash class="rounded-md px-2 py-0.5 text-base-content/70 hover:bg-base-300 hover:text-base-content cursor-pointer select-text" aria-label="{{ __('messages.common.close') }}">
                {{ __('messages.common.close') }}
            </button>
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

@if ($hasMobileSidebar)
    <div id="mobile-sidebar" class="fixed inset-0 z-40 hidden lg:hidden" aria-hidden="true">
        <button type="button" data-close-mobile-sidebar class="absolute inset-0 h-full w-full bg-base-100/80 backdrop-blur-sm" aria-label="{{ __('messages.common.close') }}"></button>

        <aside class="absolute inset-y-0 left-0 flex w-[min(88vw,22rem)] flex-col border-r border-base-300 bg-base-200 p-4 shadow-2xl">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div class="min-w-0 flex items-center gap-3">
                    <img src="{{ asset('images/logo.png') }}" alt="Cryptosik logo" class="h-9 w-9 shrink-0 object-contain" />
                    <div class="min-w-0">
                        <p class="truncate text-base font-semibold text-base-content">Crypt(o-st)osik</p>
                        @if ($headerCenterTitle)
                            <p class="truncate text-xs text-base-content/60">{{ $headerCenterTitle }}</p>
                        @endif
                    </div>
                </div>
                <button type="button" data-close-mobile-sidebar class="rounded-md border border-base-300 bg-base-300 px-3 py-2 text-xs text-base-content hover:bg-base-300 cursor-pointer select-text">
                    {{ __('messages.common.close') }}
                </button>
            </div>

            @if ($showSettingsControls || $showVaultLock || $showUserControls || $showAdminControls)
                <div class="mb-4 space-y-3 rounded-xl border border-base-300 bg-base-100/40 p-3">
                    @if ($headerIdentity !== '')
                        <div class="rounded-md border border-base-300 bg-base-100 px-3 py-2 text-sm font-medium text-base-content/80">
                            {{ $headerIdentity }}
                        </div>
                    @endif

                    <div class="grid grid-cols-1 gap-2 border-t border-base-300 pt-3">
                        @if ($showSettingsControls)
                            <button type="button" data-open-settings-modal data-close-mobile-sidebar class="rounded-md border border-base-300 bg-base-300 px-3 py-2 text-left text-sm text-base-content hover:bg-base-300 cursor-pointer select-text">
                                {{ __('messages.nav.settings') }}
                            </button>
                        @endif

                        @if ($showVaultLock && $showUserControls)
                            <form method="post" action="{{ route('vault.lock') }}">
                                @csrf
                                <button class="w-full rounded-md border border-base-300 bg-base-300 px-3 py-2 text-left text-sm text-base-content hover:bg-base-300 cursor-pointer select-text" type="submit">{{ __('messages.vault.workspace.lock_vault') }}</button>
                            </form>
                        @endif

                        @if ($showUserControls)
                            <form method="post" action="{{ route('auth.user.logout') }}">
                                @csrf
                                <button class="w-full rounded-md border border-base-300 bg-base-300 px-3 py-2 text-left text-sm text-base-content hover:bg-base-300 cursor-pointer select-text" type="submit">{{ __('messages.nav.logout') }}</button>
                            </form>
                        @elseif ($showAdminControls)
                            <form method="post" action="{{ route('admin.logout') }}">
                                @csrf
                                <button class="w-full rounded-md border border-base-300 bg-base-300 px-3 py-2 text-left text-sm text-base-content hover:bg-base-300 cursor-pointer select-text" type="submit">{{ __('messages.nav.logout') }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endif

            <div class="min-h-0 flex-1 overflow-hidden rounded-xl border border-base-300 bg-base-100/35 p-3">
                {{ $mobileSidebar }}
            </div>
        </aside>
    </div>
@endif

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
                @if ($showUserControls)
                    <label class="flex items-center gap-2 rounded-md border border-base-300 bg-base-100 px-3 py-2 text-sm text-base-content/90">
                        <input type="hidden" name="notifications_enabled" value="0" />
                        <input type="checkbox" name="notifications_enabled" value="1" @checked((string) old('notifications_enabled', $sessionNotificationsEnabled) === '1')>
                        <span>{{ __('messages.user.settings.notifications') }}</span>
                    </label>
                @endif


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
            const openButtons = document.querySelectorAll('[data-open-settings-modal]');

            if (!modal || openButtons.length === 0) {
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

            openButtons.forEach((button) => button.addEventListener('click', openModal));
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

<script>
    (() => {
        const sidebar = document.getElementById('mobile-sidebar');

        if (!sidebar) {
            return;
        }

        const openButtons = document.querySelectorAll('[data-open-mobile-sidebar]');
        const closeButtons = document.querySelectorAll('[data-close-mobile-sidebar]');

        const openSidebar = () => {
            sidebar.classList.remove('hidden');
            sidebar.setAttribute('aria-hidden', 'false');
            document.body.classList.add('overflow-hidden');
        };

        const closeSidebar = () => {
            sidebar.classList.add('hidden');
            sidebar.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('overflow-hidden');
        };

        openButtons.forEach((button) => button.addEventListener('click', openSidebar));
        closeButtons.forEach((button) => button.addEventListener('click', closeSidebar));

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !sidebar.classList.contains('hidden')) {
                closeSidebar();
            }
        });
    })();
</script>

<script>
    (() => {
        document.querySelectorAll('[data-dismiss-flash]').forEach((button) => {
            button.addEventListener('click', () => {
                button.closest('[data-dismissible-flash]')?.remove();
            });
        });
    })();
</script>

<script src="{{ asset('vendor/twemoji/twemoji.min.js') }}"></script>
<script>
    (() => {
        const emojiStyle = 'google';
        const emojiBaseUrl = 'https://cdn.jsdelivr.net/npm/emoji-datasource-google/img/google/64/';
        const emojiMartPickerModuleUrl = '/vendor/emoji-mart/module.js';
        const emojiMartDataUrl = '/vendor/emoji-mart/data/google.json';
        const emojiSpriteSheetUrl = '/vendor/emoji-mart/sheets/google-64.png';

        let picker = null;
        let pickerTargetEditor = null;
        let pickerTrigger = null;
        let pickerLoadPromise = null;
        let pickerInitialized = false;

        const applyEmoji = (container) => {
            if (!container || typeof window.twemoji === 'undefined') {
                return;
            }

            window.twemoji.parse(container, {
                className: 'emoji-glyph',
                callback: (icon) => `${emojiBaseUrl}${icon}.png`,
                attributes: () => ({
                    draggable: 'false',
                    loading: 'lazy',
                    decoding: 'async',
                }),
            });
        };

        const closePicker = () => {
            if (!picker) {
                return;
            }

            picker.classList.add('hidden');
            pickerTargetEditor = null;
            pickerTrigger = null;
        };

        const loadPickerDependencies = async () => {
            if (!pickerLoadPromise) {
                pickerLoadPromise = Promise.all([
                    import(emojiMartPickerModuleUrl),
                    fetch(emojiMartDataUrl).then((response) => {
                        if (!response.ok) {
                            throw new Error(`Failed to load emoji data (${response.status})`);
                        }

                        return response.json();
                    }),
                ]).then(([pickerModule, data]) => {
                    const Picker = pickerModule.Picker ?? pickerModule.default?.Picker;

                    if (!Picker || !data) {
                        throw new Error('Unable to initialize emoji picker modules.');
                    }

                    return {
                        Picker,
                        data,
                    };
                }).catch((error) => {
                    pickerLoadPromise = null;
                    throw error;
                });
            }

            return pickerLoadPromise;
        };

        const buildPicker = async () => {
            if (!picker) {
                picker = document.createElement('div');
                picker.id = 'cryptosik-emoji-picker';
                picker.className = 'emoji-picker-popover hidden';

                const host = document.createElement('div');
                host.className = 'emoji-picker-host';
                picker.appendChild(host);

                document.body.appendChild(picker);
            }

            if (!pickerInitialized) {
                const { Picker, data } = await loadPickerDependencies();
                const host = picker.querySelector('.emoji-picker-host');

                if (!host) {
                    throw new Error('Emoji picker host not found.');
                }

                const pickerWidget = new Picker({
                    data,
                    set: emojiStyle,
                    getSpritesheetURL: () => emojiSpriteSheetUrl,
                    theme: 'dark',
                    dynamicWidth: true,
                    perLine: 9,
                    maxFrequentRows: 1,
                    previewPosition: 'none',
                    navPosition: 'top',
                    searchPosition: 'sticky',
                    skinTonePosition: 'search',
                    locale: (document.documentElement.lang || 'en').slice(0, 2),
                    onEmojiSelect: (emoji) => {
                        if (!pickerTargetEditor) {
                            return;
                        }

                        const selectedEmoji = emoji?.native ?? '';

                        if (selectedEmoji === '') {
                            return;
                        }

                        pickerTargetEditor.codemirror.replaceSelection(selectedEmoji);
                        pickerTargetEditor.codemirror.focus();
                        closePicker();
                    },
                });

                host.appendChild(pickerWidget);
                pickerInitialized = true;
            }

            return picker;
        };

        const openPicker = async (editor, triggerElement) => {
            try {
                const pickerElement = await buildPicker();
                const triggerRect = triggerElement.getBoundingClientRect();

                pickerTargetEditor = editor;
                pickerTrigger = triggerElement;

                const top = triggerRect.bottom + window.scrollY + 8;
                const left = Math.max(12, triggerRect.left + window.scrollX - 220);

                pickerElement.style.top = `${top}px`;
                pickerElement.style.left = `${left}px`;
                pickerElement.classList.remove('hidden');
            } catch (error) {
                console.error('Failed to open emoji picker', error);
            }
        };

        const setupSimpleMde = (editor, options = {}) => {
            if (!editor || !editor.gui || !editor.gui.toolbar) {
                return;
            }

            const toolbar = editor.gui.toolbar;

            if (toolbar.querySelector('[data-cryptosik-emoji]')) {
                return;
            }

            const button = document.createElement('a');
            button.href = '#';
            button.className = 'no-disable';
            button.dataset.cryptosikEmoji = '1';
            button.title = options.title ?? 'Emoji';
            button.setAttribute('aria-label', button.title);
            button.innerHTML = '<i class="fa fa-smile-o" aria-hidden="true"></i>';

            button.addEventListener('click', (event) => {
                event.preventDefault();
                openPicker(editor, button);
            });

            toolbar.appendChild(button);

            const wrapper = editor.gui.wrapper;
            const observer = new MutationObserver(() => {
                wrapper.querySelectorAll('.editor-preview, .editor-preview-side, .editor-preview-full').forEach((previewNode) => {
                    applyEmoji(previewNode);
                });
            });

            observer.observe(wrapper, {
                childList: true,
                subtree: true,
                characterData: true,
            });
        };

        document.addEventListener('click', (event) => {
            if (!picker || picker.classList.contains('hidden')) {
                return;
            }

            const target = event.target;

            if (!(target instanceof Node)) {
                closePicker();
                return;
            }

            const clickedInsidePicker = picker.contains(target);
            const clickedTrigger = pickerTrigger instanceof HTMLElement && pickerTrigger.contains(target);

            if (!clickedInsidePicker && !clickedTrigger) {
                closePicker();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closePicker();
            }
        });

        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.markdown-content').forEach((node) => {
                applyEmoji(node);
            });
        });

        window.CryptosikUi = {
            applyEmoji,
            setupSimpleMde,
            closeEmojiPicker: closePicker,
        };
    })();
</script>

@if ($hasSimpleMdeJs)
    <script src="{{ asset('vendor/simplemde/simplemde.min.js') }}" defer></script>
@endif

@livewireScripts
</body>
</html>
