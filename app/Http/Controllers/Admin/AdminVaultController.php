<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Http\Controllers\Admin;

use EvilStudio\Cryptosik\Enums\VaultMemberRole;
use EvilStudio\Cryptosik\Enums\VaultStatus;
use EvilStudio\Cryptosik\Http\Controllers\Controller;
use EvilStudio\Cryptosik\Http\Requests\AssignVaultMemberRequest;
use EvilStudio\Cryptosik\Http\Requests\CreateVaultRequest;
use EvilStudio\Cryptosik\Models\Admin;
use EvilStudio\Cryptosik\Models\User;
use EvilStudio\Cryptosik\Models\Vault;
use EvilStudio\Cryptosik\Models\VaultMember;
use EvilStudio\Cryptosik\Services\Audit\AuditLogService;
use EvilStudio\Cryptosik\Services\Vault\VaultAccessService;
use EvilStudio\Cryptosik\Support\SessionKeys;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AdminVaultController extends Controller
{
    private const PAGE_SIZE = 50;

    public function __construct(
        private readonly VaultAccessService $vaultAccessService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function index(): View
    {
        return view('admin.vaults', [
            'vaults' => Vault::query()
                ->with([
                    'members.user:id,email,nickname,is_active',
                    'latestVerificationRun',
                ])
                ->withCount('members')
                ->orderByDesc('created_at')
                ->paginate(self::PAGE_SIZE),
            'users' => User::query()->orderBy('email')->get(['id', 'email', 'nickname', 'is_active']),
        ]);
    }

    public function store(CreateVaultRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $owner = User::query()->find((int) $validated['owner_user_id']);

        if ($owner === null || !$owner->is_active) {
            return back()->withErrors(['owner_user_id' => __('messages.admin.vaults.errors.owner_must_be_active')]);
        }

        try {
            $vault = $this->vaultAccessService->createVault(
                owner: $owner,
                vaultKey: (string) $validated['vault_key'],
                name: (string) $validated['name'],
                description: $validated['description'] !== null ? (string) $validated['description'] : null,
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['vault_key' => $exception->getMessage()]);
        }

        $admin = $this->resolveAdmin($request->session()->get(SessionKeys::ADMIN_ID));

        if ($admin !== null) {
            $this->auditLogService->adminVaultCreated($admin, $vault);
        }

        return back()->with('status', __('messages.admin.vaults.status.created'));
    }

    public function assignMember(AssignVaultMemberRequest $request, Vault $vault): RedirectResponse
    {
        $validated = $request->validated();
        $adminId = $request->session()->get(SessionKeys::ADMIN_ID);
        $userId = (int) $validated['user_id'];

        if ($vault->status === VaultStatus::SoftDeleted) {
            return back()->withErrors(['user_id' => __('messages.admin.vaults.errors.soft_deleted_membership')]);
        }

        $user = User::query()->find($userId);

        if ($user === null || !$user->is_active) {
            return back()->withErrors(['user_id' => __('messages.admin.vaults.errors.member_must_be_active')]);
        }

        $exists = VaultMember::query()
            ->where('vault_id', $vault->id)
            ->where('user_id', $userId)
            ->exists();

        if ($exists) {
            return back()->withErrors(['user_id' => __('messages.admin.vaults.errors.already_assigned')]);
        }

        VaultMember::query()->create([
            'vault_id' => $vault->id,
            'user_id' => $userId,
            'role' => VaultMemberRole::Member,
            'added_by_admin_id' => is_numeric($adminId) ? (int) $adminId : null,
        ]);

        $admin = $this->resolveAdmin($adminId);

        if ($admin !== null) {
            $this->auditLogService->adminVaultMemberAssigned($admin, $vault, $user);
        }

        return back()->with('status', __('messages.admin.vaults.status.member_assigned'));
    }

    public function archive(Vault $vault): RedirectResponse
    {
        if ($vault->status === VaultStatus::SoftDeleted) {
            return back()->withErrors(['vault' => __('messages.admin.vaults.errors.soft_deleted_archive')]);
        }

        $vault->status = VaultStatus::Archived;
        $vault->archived_at = now();
        $vault->save();

        return back()->with('status', __('messages.admin.vaults.status.archived'));
    }

    public function softDelete(Vault $vault): RedirectResponse
    {
        $vault->status = VaultStatus::SoftDeleted;
        $vault->save();
        $vault->delete();

        return back()->with('status', __('messages.admin.vaults.status.soft_deleted'));
    }

    public function restore(string $vaultId): RedirectResponse
    {
        $vault = Vault::withTrashed()->find($vaultId);

        if ($vault === null) {
            return back()->withErrors(['vault' => __('messages.admin.vaults.errors.not_found')]);
        }

        $vault->restore();
        $vault->status = VaultStatus::Active;
        $vault->save();

        return back()->with('status', __('messages.admin.vaults.status.restored'));
    }

    private function resolveAdmin(mixed $adminId): ?Admin
    {
        if (!is_numeric($adminId)) {
            return null;
        }

        return Admin::query()->find((int) $adminId);
    }
}
