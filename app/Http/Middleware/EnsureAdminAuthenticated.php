<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Http\Middleware;

use EvilStudio\Cryptosik\Support\SessionKeys;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->missing(SessionKeys::ADMIN_ID)) {
            return redirect()->route('admin.login.show');
        }

        return $next($request);
    }
}
