<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Http\Controllers\Admin;

use EvilStudio\Cryptosik\Http\Controllers\Controller;
use EvilStudio\Cryptosik\Models\Entry;
use EvilStudio\Cryptosik\Models\User;
use EvilStudio\Cryptosik\Models\Vault;
use Illuminate\Contracts\View\View;

class AdminDashboardController extends Controller
{
    private const PREVIEW_LIMIT = 10;

    public function index(): View
    {
        return view('admin.dashboard', [
            'usersCount' => User::query()->count(),
            'vaultsCount' => Vault::query()->count(),
            'entriesCount' => Entry::query()->count(),
            'latestUsers' => User::query()
                ->orderByDesc('created_at')
                ->limit(self::PREVIEW_LIMIT)
                ->get(['id', 'email', 'nickname', 'is_active', 'created_at']),
            'latestVaults' => Vault::query()
                ->with(['owner:id,email'])
                ->orderByDesc('created_at')
                ->limit(self::PREVIEW_LIMIT)
                ->get(['id', 'owner_user_id', 'status', 'created_at']),
        ]);
    }
}
