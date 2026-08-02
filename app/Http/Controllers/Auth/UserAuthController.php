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
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class UserAuthController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function showLogin(Request $request): View
    {
        if ($request->boolean('reset')) {
            $request->session()->forget([SessionKeys::PENDING_EMAIL, 'dev_code']);
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

        $request->session()->put(SessionKeys::PENDING_EMAIL, $email);

        $redirect = redirect()->route('auth.user.login.show')
            ->with('status', __('messages.auth.user_login.status.code_issued'));

        if ($devCode !== null) {
            $redirect->with('dev_code', $devCode);
        }

        return $redirect;
    }

    public function verifyCode(VerifyLoginCodeRequest $request, OtpService $otpService): RedirectResponse
    {
        $pendingEmail = mb_strtolower((string) $request->session()->get(SessionKeys::PENDING_EMAIL, ''));
        $email = mb_strtolower((string) $request->validated('email'));
        $code = (string) $request->validated('code');
        $user = User::query()->where('email', $email)->where('is_active', true)->first();

        if ($pendingEmail === '' || $pendingEmail !== $email) {
            $this->auditLogService->userLoginFailed($user);

            return redirect()->route('auth.user.login.show', ['reset' => 1])->withErrors([
                'email' => __('messages.auth.user_login.errors.restart_login'),
            ]);
        }

        if ($user === null || ! $otpService->verify($email, $code, (string) $request->ip())) {
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
        $request->session()->forget([SessionKeys::PENDING_EMAIL, 'dev_code']);

        app()->setLocale($locale);

        $this->auditLogService->userLoginSuccess($user);

        return redirect()->route('auth.vault.unlock.show');
    }

    public function showUnlock(): View
    {
        return view('auth.vault-unlock');
    }

    public function unlockVault(UnlockVaultRequest $request, VaultAccessService $vaultAccessService): RedirectResponse
    {
        $user = $this->currentUser($request);

        if ($user === null) {
            return redirect()->route('auth.user.login.show');
        }

        $throttleKey = sprintf('vault:unlock:%d:%s', $user->id, (string) $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, (int) config('cryptosik.throttle.vault_unlock.max_attempts'))) {
            return back()->withErrors([
                'vault_key' => __('messages.auth.vault_unlock.errors.too_many_attempts', [
                    'seconds' => RateLimiter::availableIn($throttleKey),
                ]),
            ]);
        }

        $unlockResult = $vaultAccessService->unlockVaultForUser($user, (string) $request->validated('vault_key'));

        if ($unlockResult === null) {
            RateLimiter::hit($throttleKey, (int) config('cryptosik.throttle.vault_unlock.decay_minutes') * 60);

            $this->auditLogService->vaultOpenFailed($user);

            return back()->withErrors([
                'vault_key' => __('messages.auth.vault_unlock.errors.invalid_key'),
            ]);
        }

        RateLimiter::clear($throttleKey);

        $request->session()->regenerate();

        $request->session()->put(SessionKeys::UNLOCKED_VAULT_ID, $unlockResult->vault->id);
        $request->session()->put(SessionKeys::UNLOCKED_VAULT_KEY, $unlockResult->dataKey);

        $this->auditLogService->vaultOpenSuccess($user, $unlockResult->vault);

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
            SessionKeys::PENDING_EMAIL,
            'dev_code',
        ]);

        // Not invalidate(): an admin session in the same browser must survive.
        $request->session()->regenerate(true);

        return redirect()->route('auth.user.login.show');
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
