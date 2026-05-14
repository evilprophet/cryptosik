<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Http\Controllers\Auth;

use EvilStudio\Cryptosik\Http\Controllers\Controller;
use EvilStudio\Cryptosik\Http\Requests\RequestLoginCodeRequest;
use EvilStudio\Cryptosik\Http\Requests\UnlockVaultRequest;
use EvilStudio\Cryptosik\Http\Requests\VerifyLoginCodeRequest;
use EvilStudio\Cryptosik\Models\User;
use EvilStudio\Cryptosik\Services\Audit\AuditLogService;
use EvilStudio\Cryptosik\Services\Auth\OtpService;
use EvilStudio\Cryptosik\Services\Vault\VaultAccessService;
use EvilStudio\Cryptosik\Support\SessionKeys;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserAuthController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    public function showLogin(Request $request): \Illuminate\Contracts\View\View
    {
        if ($request->boolean('reset')) {
            $request->session()->forget(['auth.pending_email', 'dev_code']);
        }

        return view('auth.user-login');
    }

    public function requestCode(RequestLoginCodeRequest $request, OtpService $otpService): RedirectResponse
    {
        $email = mb_strtolower((string) $request->validated('email'));
        $user = User::query()->where('email', $email)->where('is_active', true)->first();

        $devCode = null;

        if ($user !== null) {
            $devCode = $otpService->issueForEmail($email, (string) $request->ip());
        }

        $request->session()->put('auth.pending_email', $email);

        $redirect = redirect()->route('auth.user.login.show')
            ->with('status', __('messages.auth.user_login.status.code_issued'));

        if ($devCode !== null) {
            $redirect->with('dev_code', $devCode);
        }

        return $redirect;
    }

    public function verifyCode(VerifyLoginCodeRequest $request, OtpService $otpService): RedirectResponse
    {
        $pendingEmail = mb_strtolower((string) $request->session()->get('auth.pending_email', ''));
        $email = mb_strtolower((string) $request->validated('email'));
        $code = (string) $request->validated('code');
        $user = User::query()->where('email', $email)->where('is_active', true)->first();

        if ($pendingEmail === '' || $pendingEmail !== $email) {
            $this->auditLogService->userLoginFailed($user);

            return redirect()->route('auth.user.login.show', ['reset' => 1])->withErrors([
                'email' => __('messages.auth.user_login.errors.restart_login'),
            ]);
        }

        if ($user === null || !$otpService->verify($email, $code, (string) $request->ip())) {
            $this->auditLogService->userLoginFailed($user);

            return redirect()->route('auth.user.login.show')->withErrors([
                'code' => __('messages.auth.user_login.errors.invalid_code'),
            ]);
        }

        $request->session()->regenerate();
        $request->session()->forget([
            SessionKeys::UNLOCKED_VAULT_ID,
            SessionKeys::UNLOCKED_VAULT_KEY,
        ]);

        $locale = $this->resolveUserLocale($user);

        $request->session()->put(SessionKeys::USER_ID, $user->id);
        $request->session()->put(SessionKeys::USER_EMAIL, $user->email);
        $request->session()->put(SessionKeys::USER_NICKNAME, $user->displayName());
        $request->session()->put(SessionKeys::USER_NOTIFICATIONS_ENABLED, $user->notifications_enabled);
        $request->session()->put('app.locale', $locale);
        $request->session()->forget(['auth.pending_email', 'dev_code']);

        app()->setLocale($locale);

        $this->auditLogService->userLoginSuccess($user);

        return redirect()->route('auth.vault.unlock.show');
    }

    public function showUnlock(): \Illuminate\Contracts\View\View
    {
        return view('auth.vault-unlock');
    }

    public function unlockVault(UnlockVaultRequest $request, VaultAccessService $vaultAccessService): RedirectResponse
    {
        $user = $this->resolveUserFromSession($request);

        if ($user === null) {
            return redirect()->route('auth.user.login.show');
        }

        $unlockResult = $vaultAccessService->unlockVaultForUser($user, (string) $request->validated('vault_key'));

        if ($unlockResult === null) {
            $this->auditLogService->vaultOpenFailed($user);

            return back()->withErrors([
                'vault_key' => __('messages.auth.vault_unlock.errors.invalid_key'),
            ]);
        }

        $request->session()->put(SessionKeys::UNLOCKED_VAULT_ID, $unlockResult['vault']->id);
        $request->session()->put(SessionKeys::UNLOCKED_VAULT_KEY, $unlockResult['data_key']);

        $this->auditLogService->vaultOpenSuccess($user, $unlockResult['vault']);

        return redirect()->route('vault.workspace');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget([
            SessionKeys::USER_ID,
            SessionKeys::USER_EMAIL,
            SessionKeys::USER_NICKNAME,
            SessionKeys::USER_NOTIFICATIONS_ENABLED,
            SessionKeys::UNLOCKED_VAULT_ID,
            SessionKeys::UNLOCKED_VAULT_KEY,
            'auth.pending_email',
            'dev_code',
        ]);

        $request->session()->regenerateToken();

        return redirect()->route('auth.user.login.show');
    }

    private function resolveUserFromSession(Request $request): ?User
    {
        $userId = $request->session()->get(SessionKeys::USER_ID);

        if (!is_numeric($userId)) {
            return null;
        }

        return User::query()->find((int) $userId);
    }

    private function resolveUserLocale(User $user): string
    {
        $supportedLocales = (array) config('cryptosik.locales', ['en']);
        $defaultLocale = (string) config('app.locale', 'en');
        $locale = (string) $user->locale;

        if (in_array($locale, $supportedLocales, true)) {
            return $locale;
        }

        if (in_array($defaultLocale, $supportedLocales, true)) {
            return $defaultLocale;
        }

        return (string) ($supportedLocales[0] ?? 'en');
    }
}
