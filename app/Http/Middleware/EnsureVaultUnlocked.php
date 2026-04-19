<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Http\Middleware;

use EvilStudio\Cryptosik\Support\SessionKeys;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVaultUnlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->missing(SessionKeys::UNLOCKED_VAULT_ID)
            || $request->session()->missing(SessionKeys::UNLOCKED_VAULT_KEY)) {
            return redirect()->route('auth.vault.unlock.show');
        }

        return $next($request);
    }
}
