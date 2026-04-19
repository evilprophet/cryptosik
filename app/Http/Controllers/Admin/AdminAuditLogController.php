<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Http\Controllers\Admin;

use EvilStudio\Cryptosik\Http\Controllers\Controller;
use EvilStudio\Cryptosik\Models\Admin;
use EvilStudio\Cryptosik\Models\AuditLog;
use EvilStudio\Cryptosik\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AdminAuditLogController extends Controller
{
    private const PAGE_SIZE = 50;

    public function index(Request $request): View
    {
        $actorType = trim((string) $request->query('actor_type', ''));
        $action = trim((string) $request->query('action', ''));

        $logsQuery = AuditLog::query()->orderByDesc('created_at')->orderByDesc('id');

        if ($actorType !== '') {
            $logsQuery->where('actor_type', $actorType);
        }

        if ($action !== '') {
            $logsQuery->where('action', 'like', sprintf('%s%%', $action));
        }

        $logs = $logsQuery->paginate(self::PAGE_SIZE)->withQueryString();
        $logItems = $logs->getCollection();

        $userActorIds = $logItems
            ->where('actor_type', 'user')
            ->pluck('actor_id')
            ->filter(static fn ($id): bool => is_numeric($id))
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $adminActorIds = $logItems
            ->where('actor_type', 'admin')
            ->pluck('actor_id')
            ->filter(static fn ($id): bool => is_numeric($id))
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $usersById = User::query()
            ->whereIn('id', $userActorIds)
            ->get(['id', 'nickname', 'email'])
            ->keyBy('id');

        $adminsById = Admin::query()
            ->whereIn('id', $adminActorIds)
            ->get(['id', 'login'])
            ->keyBy('id');

        $actorLabels = [];

        foreach ($logItems as $log) {
            if ($log->actor_type === 'user' && is_numeric($log->actor_id)) {
                $user = $usersById->get((int) $log->actor_id);

                if ($user !== null) {
                    $displayName = trim((string) $user->nickname);
                    $actorLabels[$log->id] = $displayName !== '' ? $displayName : (string) $user->email;

                    continue;
                }
            }

            if ($log->actor_type === 'admin' && is_numeric($log->actor_id)) {
                $admin = $adminsById->get((int) $log->actor_id);

                if ($admin !== null) {
                    $actorLabels[$log->id] = (string) $admin->login;

                    continue;
                }
            }

            if ($log->actor_type !== null && $log->actor_id !== null) {
                $actorLabels[$log->id] = sprintf('%s#%s', (string) $log->actor_type, (string) $log->actor_id);
            } else {
                $actorLabels[$log->id] = 'n/a';
            }
        }

        return view('admin.logs', [
            'logs' => $logs,
            'actorLabels' => $actorLabels,
            'actorType' => $actorType,
            'action' => $action,
        ]);
    }
}
