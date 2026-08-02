<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Http\Middleware;

use Closure;
use EvilStudio\Cryptosik\Models\User;
use EvilStudio\Cryptosik\Support\SessionKeys;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserOtpAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->session()->get(SessionKeys::USER_ID);

        if (! is_numeric($userId)) {
            return $this->reject($request);
        }

        // Re-read every request: session-key presence alone outlives deactivation.
        $user = User::query()->where('id', (int) $userId)->where('is_active', true)->first();

        if ($user === null) {
            return $this->reject($request);
        }

        $request->attributes->set('cryptosik.user', $user);

        return $next($request);
    }

    private function reject(Request $request): Response
    {
        // User-scoped keys only - an admin session in the same browser must survive.
        $request->session()->forget([
            SessionKeys::USER_ID,
            SessionKeys::USER_EMAIL,
            SessionKeys::USER_NICKNAME,
            SessionKeys::USER_NOTIFICATIONS_ENABLED,
            SessionKeys::UNLOCKED_VAULT_ID,
            SessionKeys::UNLOCKED_VAULT_KEY,
        ]);

        return redirect()->route('auth.user.login.show');
    }
}
