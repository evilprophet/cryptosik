<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Http\Controllers\Admin;

use EvilStudio\Cryptosik\Enums\VaultMemberRole;
use EvilStudio\Cryptosik\Enums\VaultStatus;
use EvilStudio\Cryptosik\Http\Controllers\Controller;
use EvilStudio\Cryptosik\Http\Requests\AssignVaultMemberRequest;
use EvilStudio\Cryptosik\Http\Requests\CreateVaultRequest;
use EvilStudio\Cryptosik\Models\Admin;
use EvilStudio\Cryptosik\Models\Entry;
use EvilStudio\Cryptosik\Models\EntryRead;
use EvilStudio\Cryptosik\Models\User;
use EvilStudio\Cryptosik\Models\Vault;
use EvilStudio\Cryptosik\Models\VaultMember;
use EvilStudio\Cryptosik\Services\Audit\AuditLogService;
use EvilStudio\Cryptosik\Services\Vault\MembershipNotificationService;
use EvilStudio\Cryptosik\Services\Vault\VaultAccessService;
use EvilStudio\Cryptosik\Support\SessionKeys;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use RuntimeException;

class AdminVaultController extends Controller
{
    private const PAGE_SIZE = 50;

    public function __construct(
        private readonly VaultAccessService $vaultAccessService,
        private readonly AuditLogService $auditLogService,
        private readonly MembershipNotificationService $membershipNotificationService,
    ) {
    }

    public function index(): View
    {
        $vaults = Vault::query()
            ->with([
                'members.user:id,email,nickname,is_active',
                'latestVerificationRun',
            ])
            ->withCount('members')
            ->orderByDesc('created_at')
            ->paginate(self::PAGE_SIZE);

        $this->attachUnreadEntryCounts($vaults);

        return view('admin.vaults', [
            'vaults' => $vaults,
            'users' => User::query()->orderBy('email')->get(['id', 'email', 'nickname', 'is_active']),
        ]);
    }

    public function store(CreateVaultRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $sendOwnerNotificationNow = !array_key_exists('send_owner_notification_now', $validated) || (bool) $validated['send_owner_notification_now'];

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

            if ($sendOwnerNotificationNow) {
                try {
                    $this->membershipNotificationService->sendMemberAddedNotification($admin, $vault, $owner, 'immediate_owner_create');
                } catch (RuntimeException $exception) {
                    return back()
                        ->with('status', __('messages.admin.vaults.status.created_owner_notification_skipped'))
                        ->withErrors(['owner_user_id' => $exception->getMessage()]);
                }

                return back()->with('status', __('messages.admin.vaults.status.created_with_owner_notification'));
            }

            $this->membershipNotificationService->skipNotification($admin, $vault, $owner);

            return back()->with('status', __('messages.admin.vaults.status.created_owner_notification_skipped'));
        }

        return back()->with('status', __('messages.admin.vaults.status.created'));
    }

    public function assignMember(AssignVaultMemberRequest $request, Vault $vault): RedirectResponse
    {
        $validated = $request->validated();
        $adminId = $request->session()->get(SessionKeys::ADMIN_ID);
        $userId = (int) $validated['user_id'];
        $sendNotificationNow = !array_key_exists('send_notification_now', $validated) || (bool) $validated['send_notification_now'];

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

        if ($admin === null) {
            return back()->with('status', __('messages.admin.vaults.status.member_assigned'));
        }

        if ($sendNotificationNow) {
            try {
                $this->membershipNotificationService->sendMemberAddedNotification($admin, $vault, $user, 'immediate');
            } catch (RuntimeException $exception) {
                return back()
                    ->with('status', __('messages.admin.vaults.status.member_assigned_notification_skipped'))
                    ->withErrors(['user_id' => $exception->getMessage()]);
            }

            return back()->with('status', __('messages.admin.vaults.status.member_assigned_with_notification'));
        }

        $this->membershipNotificationService->skipNotification($admin, $vault, $user);

        return back()->with('status', __('messages.admin.vaults.status.member_assigned_notification_skipped'));
    }

    public function notifyMember(Request $request, Vault $vault, User $user): RedirectResponse
    {
        if ($vault->status === VaultStatus::SoftDeleted) {
            return back()->withErrors(['user_id' => __('messages.admin.vaults.errors.soft_deleted_membership')]);
        }

        if (!$user->is_active) {
            return back()->withErrors(['user_id' => __('messages.admin.vaults.errors.member_must_be_active')]);
        }

        $membershipExists = VaultMember::query()
            ->where('vault_id', $vault->id)
            ->where('user_id', $user->id)
            ->exists();

        if (!$membershipExists) {
            return back()->withErrors(['user_id' => __('messages.admin.vaults.errors.member_not_found')]);
        }

        $admin = $this->resolveAdmin($request->session()->get(SessionKeys::ADMIN_ID));

        if ($admin === null) {
            return redirect()->route('admin.login.show');
        }

        try {
            $this->membershipNotificationService->sendMemberAddedNotification($admin, $vault, $user, 'manual');
        } catch (RuntimeException $exception) {
            return back()->withErrors(['user_id' => $exception->getMessage()]);
        }

        return back()->with('status', __('messages.admin.vaults.status.notification_sent'));
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

    private function attachUnreadEntryCounts(LengthAwarePaginator $vaults): void
    {
        $vaultIds = $vaults->getCollection()
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->values();

        if ($vaultIds->isEmpty()) {
            return;
        }

        $entryTotals = Entry::query()
            ->selectRaw('vault_id, COUNT(*) as aggregate')
            ->whereIn('vault_id', $vaultIds)
            ->groupBy('vault_id')
            ->pluck('aggregate', 'vault_id');

        $readCounts = EntryRead::query()
            ->selectRaw('entries.vault_id as vault_id, entry_reads.user_id as user_id, COUNT(*) as aggregate')
            ->join('entries', 'entries.id', '=', 'entry_reads.entry_id')
            ->whereIn('entries.vault_id', $vaultIds)
            ->groupBy('entries.vault_id', 'entry_reads.user_id')
            ->get();

        $readCountsByMember = [];

        foreach ($readCounts as $readCount) {
            $key = sprintf('%s:%s', (string) $readCount->vault_id, (string) $readCount->user_id);
            $readCountsByMember[$key] = (int) $readCount->aggregate;
        }

        foreach ($vaults->getCollection() as $vault) {
            $entryTotal = (int) ($entryTotals[(string) $vault->id] ?? 0);

            foreach ($vault->members as $member) {
                $key = sprintf('%s:%s', (string) $vault->id, (string) $member->user_id);
                $member->setAttribute('unread_entries_count', max(0, $entryTotal - ($readCountsByMember[$key] ?? 0)));
            }
        }
    }
}
