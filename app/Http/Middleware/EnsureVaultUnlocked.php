<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Http\Middleware;

use Closure;
use EvilStudio\Cryptosik\Enums\VaultStatus;
use EvilStudio\Cryptosik\Models\Vault;
use EvilStudio\Cryptosik\Models\VaultMember;
use EvilStudio\Cryptosik\Support\SessionKeys;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVaultUnlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        $vaultId = $request->session()->get(SessionKeys::UNLOCKED_VAULT_ID);
        $userId = $request->session()->get(SessionKeys::USER_ID);

        if (! is_string($vaultId)
            || $request->session()->missing(SessionKeys::UNLOCKED_VAULT_KEY)
            || ! is_numeric($userId)) {
            return $this->reject($request);
        }

        // Covers every route in this group, including the DraftController writes, which do
        // not check membership themselves.
        $isMember = VaultMember::query()
            ->where('vault_id', $vaultId)
            ->where('user_id', (int) $userId)
            ->exists();

        $vault = $isMember
            ? Vault::query()->where('id', $vaultId)->where('status', VaultStatus::Active)->first()
            : null;

        if ($vault === null) {
            return $this->reject($request);
        }

        $request->attributes->set('cryptosik.vault', $vault);

        return $next($request);
    }

    private function reject(Request $request): Response
    {
        $request->session()->forget([
            SessionKeys::UNLOCKED_VAULT_ID,
            SessionKeys::UNLOCKED_VAULT_KEY,
        ]);

        return redirect()->route('auth.vault.unlock.show');
    }
}
