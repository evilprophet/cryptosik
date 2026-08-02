<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Http\Middleware;

use Closure;
use EvilStudio\Cryptosik\Models\Admin;
use EvilStudio\Cryptosik\Support\SessionKeys;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $adminId = $request->session()->get(SessionKeys::ADMIN_ID);

        if (! is_numeric($adminId)) {
            return $this->reject($request);
        }

        // Re-read every request: session-key presence alone outlives deactivation.
        $admin = Admin::query()->where('id', (int) $adminId)->where('is_active', true)->first();

        if ($admin === null) {
            return $this->reject($request);
        }

        $request->attributes->set('cryptosik.admin', $admin);

        return $next($request);
    }

    private function reject(Request $request): Response
    {
        // Admin-scoped keys only - a user session in the same browser must survive.
        $request->session()->forget([
            SessionKeys::ADMIN_ID,
            SessionKeys::ADMIN_LOGIN,
        ]);

        return redirect()->route('admin.login.show');
    }
}
