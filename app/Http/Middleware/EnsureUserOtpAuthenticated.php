<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Http\Middleware;

use EvilStudio\Cryptosik\Support\SessionKeys;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserOtpAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->missing(SessionKeys::USER_ID)) {
            return redirect()->route('auth.user.login.show');
        }

        return $next($request);
    }
}
