<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Http\Controllers\Admin;

use EvilStudio\Cryptosik\Http\Controllers\Controller;
use EvilStudio\Cryptosik\Http\Requests\AdminLoginRequest;
use EvilStudio\Cryptosik\Models\Admin;
use EvilStudio\Cryptosik\Services\Audit\AuditLogService;
use EvilStudio\Cryptosik\Support\SessionKeys;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminAuthController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    public function showLogin(): \Illuminate\Contracts\View\View
    {
        return view('admin.login');
    }

    public function login(AdminLoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();

        $admin = Admin::query()
            ->where('login', (string) $credentials['login'])
            ->where('is_active', true)
            ->first();

        if ($admin === null || !Hash::check((string) $credentials['password'], $admin->password_hash)) {
            $this->auditLogService->adminLoginFailed($admin);

            return back()->withErrors([
                'login' => 'Invalid admin credentials.',
            ]);
        }

        $request->session()->regenerate();
        $request->session()->forget([
            SessionKeys::USER_ID,
            SessionKeys::USER_EMAIL,
            SessionKeys::USER_NICKNAME,
            SessionKeys::UNLOCKED_VAULT_ID,
            SessionKeys::UNLOCKED_VAULT_KEY,
            'auth.pending_email',
            'dev_code',
        ]);
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
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login.show');
    }
}
