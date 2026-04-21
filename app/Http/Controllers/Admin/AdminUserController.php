<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Http\Controllers\Admin;

use EvilStudio\Cryptosik\Http\Controllers\Controller;
use EvilStudio\Cryptosik\Http\Requests\CreateUserRequest;
use EvilStudio\Cryptosik\Http\Requests\UpdateUserNicknameRequest;
use EvilStudio\Cryptosik\Models\Admin;
use EvilStudio\Cryptosik\Models\User;
use EvilStudio\Cryptosik\Services\Audit\AuditLogService;
use EvilStudio\Cryptosik\Support\SessionKeys;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class AdminUserController extends Controller
{
    private const PAGE_SIZE = 50;

    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    public function index(): View
    {
        return view('admin.users', [
            'users' => User::query()->orderBy('id')->paginate(self::PAGE_SIZE),
            'supportedLocales' => (array) config('cryptosik.locales', ['en']),
        ]);
    }

    public function store(CreateUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $supportedLocales = (array) config('cryptosik.locales', ['en']);
        $defaultLocale = (string) config('app.locale', 'en');

        if (!in_array($defaultLocale, $supportedLocales, true)) {
            $defaultLocale = (string) ($supportedLocales[0] ?? 'en');
        }

        $user = User::query()->create([
            'email' => mb_strtolower((string) $validated['email']),
            'nickname' => trim((string) $validated['nickname']),
            'locale' => $defaultLocale,
            'notifications_enabled' => (bool) ($validated['notifications_enabled'] ?? true),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        $admin = $this->resolveAdmin($request->session()->get(SessionKeys::ADMIN_ID));

        if ($admin !== null) {
            $this->auditLogService->adminUserCreated($admin, $user);
        }

        return back()->with('status', __('messages.admin.users.status.created'));
    }

    public function updateNickname(UpdateUserNicknameRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();
        $nickname = trim((string) $validated['edit_nickname']);
        $locale = (string) $validated['edit_locale'];
        $notificationsEnabled = (bool) ($validated['edit_notifications_enabled'] ?? $user->notifications_enabled);

        if ($nickname === $user->nickname && $locale === $user->locale && $notificationsEnabled === $user->notifications_enabled) {
            return back()->with('status', __('messages.admin.users.status.nickname_unchanged'));
        }

        $user->nickname = $nickname;
        $user->locale = $locale;
        $user->notifications_enabled = $notificationsEnabled;
        $user->save();

        return back()->with('status', __('messages.admin.users.status.nickname_updated', ['email' => $user->email]));
    }

    public function deactivate(User $user): RedirectResponse
    {
        if (!$user->is_active) {
            return back()->with('status', __('messages.admin.users.status.already_inactive', ['email' => $user->email]));
        }

        $user->is_active = false;
        $user->save();

        return back()->with('status', __('messages.admin.users.status.deactivated', ['email' => $user->email]));
    }

    public function activate(User $user): RedirectResponse
    {
        if ($user->is_active) {
            return back()->with('status', __('messages.admin.users.status.already_active', ['email' => $user->email]));
        }

        $user->is_active = true;
        $user->save();

        return back()->with('status', __('messages.admin.users.status.activated', ['email' => $user->email]));
    }

    private function resolveAdmin(mixed $adminId): ?Admin
    {
        if (!is_numeric($adminId)) {
            return null;
        }

        return Admin::query()->find((int) $adminId);
    }
}
