<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Http\Controllers\User;

use EvilStudio\Cryptosik\Http\Controllers\Controller;
use EvilStudio\Cryptosik\Models\User;
use EvilStudio\Cryptosik\Support\SessionKeys;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserSettingsController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $supportedLocales = (array) config('cryptosik.locales', ['en']);
        $nicknameLimit = (int) config('cryptosik.limits.user_nickname_chars', 80);

        $validated = $request->validate([
            'nickname' => ['required', 'string', 'max:'.$nicknameLimit],
            'locale' => ['required', 'string', Rule::in($supportedLocales)],
        ]);

        $userId = $request->session()->get(SessionKeys::USER_ID);

        if (!is_numeric($userId)) {
            return redirect()->route('auth.user.login.show');
        }

        $user = User::query()->find((int) $userId);

        if ($user === null) {
            return redirect()->route('auth.user.login.show');
        }

        $nickname = trim((string) $validated['nickname']);

        if ($nickname === '') {
            return back()
                ->withErrors(['nickname' => __('messages.user.settings.errors.nickname_required')])
                ->withInput();
        }

        $locale = (string) $validated['locale'];

        $user->nickname = $nickname;
        $user->locale = $locale;
        $user->save();

        $request->session()->put(SessionKeys::USER_NICKNAME, $user->displayName());
        $request->session()->put('app.locale', $locale);
        app()->setLocale($locale);

        return back()->with('status', __('messages.user.settings.status.updated'));
    }
}
