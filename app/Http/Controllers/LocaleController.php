<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Http\Controllers;

use EvilStudio\Cryptosik\Models\User;
use EvilStudio\Cryptosik\Support\SessionKeys;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $supportedLocales = (array) config('cryptosik.locales', ['en']);

        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in($supportedLocales)],
        ]);

        $locale = (string) $validated['locale'];
        $userId = $request->session()->get(SessionKeys::USER_ID);

        if (is_numeric($userId)) {
            $user = User::query()->find((int) $userId);

            if ($user !== null) {
                $user->locale = $locale;
                $user->save();
            }
        }

        $request->session()->put('app.locale', $locale);
        app()->setLocale($locale);

        return back();
    }
}
