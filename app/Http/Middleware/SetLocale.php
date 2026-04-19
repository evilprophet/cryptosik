<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Http\Middleware;

use Closure;
use EvilStudio\Cryptosik\Models\User;
use EvilStudio\Cryptosik\Support\SessionKeys;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supportedLocales = (array) config('cryptosik.locales', ['en']);
        $defaultLocale = (string) config('app.locale', 'en');
        $sessionLocale = $request->session()->get('app.locale');
        $userLocale = null;

        $userId = $request->session()->get(SessionKeys::USER_ID);

        if (is_numeric($userId)) {
            $user = User::query()->select('locale')->find((int) $userId);
            $userLocale = $user?->locale;
        }

        if (is_string($userLocale) && in_array($userLocale, $supportedLocales, true)) {
            app()->setLocale($userLocale);
            $request->session()->put('app.locale', $userLocale);
        } elseif (is_string($sessionLocale) && in_array($sessionLocale, $supportedLocales, true)) {
            app()->setLocale($sessionLocale);
        } elseif (in_array($defaultLocale, $supportedLocales, true)) {
            app()->setLocale($defaultLocale);
        } else {
            app()->setLocale('en');
        }

        return $next($request);
    }
}
