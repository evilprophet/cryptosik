<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Http\Controllers\Admin;

use EvilStudio\Cryptosik\Http\Controllers\Controller;
use EvilStudio\Cryptosik\Http\Requests\AdminLoginRequest;
use EvilStudio\Cryptosik\Models\Admin;
use EvilStudio\Cryptosik\Services\Audit\AuditLogService;
use EvilStudio\Cryptosik\Support\SessionKeys;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AdminAuthController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function showLogin(): View
    {
        return view('admin.login');
    }

    public function login(AdminLoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();
        $throttleKey = $this->loginThrottleKey((string) $credentials['login'], (string) $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, (int) config('cryptosik.throttle.admin_login.max_attempts'))) {
            throw ValidationException::withMessages([
                'login' => __('messages.admin.login.errors.too_many_attempts', [
                    'seconds' => RateLimiter::availableIn($throttleKey),
                ]),
            ]);
        }

        $admin = Admin::query()
            ->where('login', (string) $credentials['login'])
            ->where('is_active', true)
            ->first();

        if ($admin === null || ! Hash::check((string) $credentials['password'], $admin->password_hash)) {
            RateLimiter::hit($throttleKey, (int) config('cryptosik.throttle.admin_login.decay_minutes') * 60);

            $this->auditLogService->adminLoginFailed($admin);

            return back()->withErrors([
                'login' => 'Invalid admin credentials.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        $request->session()->regenerate();
        $request->session()->put(SessionKeys::ADMIN_ID, $admin->id);
        $request->session()->put(SessionKeys::ADMIN_LOGIN, (string) $admin->login);

        $this->auditLogService->adminLoginSuccess($admin);

        return redirect()->route('admin.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget([
            SessionKeys::ADMIN_ID,
            SessionKeys::ADMIN_LOGIN,
        ]);

        // Not invalidate(): a user session in the same browser must survive.
        $request->session()->regenerate(true);

        return redirect()->route('admin.login.show');
    }

    private function loginThrottleKey(string $login, string $ipAddress): string
    {
        return sprintf('admin:login:%s:%s', mb_strtolower($login), $ipAddress);
    }
}
