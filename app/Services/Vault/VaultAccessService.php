<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Services\Vault;

use EvilStudio\Cryptosik\Enums\VaultMemberRole;
use EvilStudio\Cryptosik\Enums\VaultStatus;
use EvilStudio\Cryptosik\Exceptions\VaultKeyAlreadyExistsException;
use EvilStudio\Cryptosik\Models\User;
use EvilStudio\Cryptosik\Models\Vault;
use EvilStudio\Cryptosik\Models\VaultChainState;
use EvilStudio\Cryptosik\Models\VaultCrypto;
use EvilStudio\Cryptosik\Models\VaultMember;
use EvilStudio\Cryptosik\Services\Crypto\CryptoService;
use EvilStudio\Cryptosik\Support\Values\UnlockedVault;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VaultAccessService
{
    public function __construct(
        private readonly CryptoService $cryptoService,
    ) {}

    public function createVault(User $owner, string $vaultKey, string $name, ?string $description): Vault
    {
        if ($this->keyIsAlreadyInUse($vaultKey)) {
            throw VaultKeyAlreadyExistsException::make();
        }

        $dataKeyB64 = $this->cryptoService->generateDataKey();
        $kdfSalt = $this->cryptoService->generateSalt();
        $wrappingKey = $this->cryptoService->deriveWrappingKey($vaultKey, $kdfSalt);
        $wrapped = $this->cryptoService->wrapDataKey($dataKeyB64, $wrappingKey);

        sodium_memzero($wrappingKey);

        return DB::transaction(function () use (
            $owner,
            $name,
            $description,
            $dataKeyB64,
            $kdfSalt,
            $wrapped,
        ): Vault {
            $vault = Vault::query()->create([
                'owner_user_id' => $owner->id,
                'name_enc' => $this->cryptoService->encryptEnvelope($name, $dataKeyB64),
                'description_enc' => $description !== null
                    ? $this->cryptoService->encryptEnvelope($description, $dataKeyB64)
                    : null,
                'status' => VaultStatus::Active,
            ]);

            VaultCrypto::query()->create([
                'vault_id' => $vault->id,
                'kdf_salt' => $kdfSalt,
                'kdf_params' => [
                    'opslimit' => (int) config('cryptosik.crypto.argon_opslimit'),
                    'memlimit' => (int) config('cryptosik.crypto.argon_memlimit'),
                ],
                'wrapped_data_key' => $wrapped['ciphertext'],
                'wrap_nonce' => $wrapped['nonce'],
            ]);

            VaultMember::query()->create([
                'vault_id' => $vault->id,
                'user_id' => $owner->id,
                'role' => VaultMemberRole::Owner,
            ]);

            VaultChainState::query()->create([
                'vault_id' => $vault->id,
                'last_sequence_no' => 0,
                'last_entry_hash' => null,
            ]);

            return $vault;
        });
    }

    /**
     * Resolves the vault by trying to unwrap, not by a stored index. Only the user's own
     * vaults are candidates, so membership is enforced by construction.
     */
    public function unlockVaultForUser(User $user, string $vaultKey): ?UnlockedVault
    {
        $candidates = VaultCrypto::query()
            ->with('vault')
            ->whereIn('vault_id', $user->memberships()->select('vault_id'))
            ->get();

        $match = $this->findVaultForKey($candidates, $vaultKey);

        if ($match === null) {
            return null;
        }

        return new UnlockedVault($match['crypto']->vault, $match['data_key']);
    }

    // Spans archived and soft-deleted vaults too, exactly like the dropped unique index did.
    private function keyIsAlreadyInUse(string $vaultKey): bool
    {
        foreach (VaultCrypto::query()->cursor() as $vaultCrypto) {
            if ($this->unwrapDataKeyFor($vaultCrypto, $vaultKey) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, VaultCrypto>  $candidates
     * @return array{crypto: VaultCrypto, data_key: string}|null
     */
    private function findVaultForKey($candidates, string $vaultKey): ?array
    {
        foreach ($candidates as $vaultCrypto) {
            $vault = $vaultCrypto->vault;

            // Archived means closed: readable history stays in the database, but nobody
            // unlocks it again until an admin makes the vault active.
            if ($vault === null || $vault->status !== VaultStatus::Active || $vault->deleted_at !== null) {
                continue;
            }

            $dataKey = $this->unwrapDataKeyFor($vaultCrypto, $vaultKey);

            if ($dataKey !== null) {
                return ['crypto' => $vaultCrypto, 'data_key' => $dataKey];
            }
        }

        return null;
    }

    private function unwrapDataKeyFor(VaultCrypto $vaultCrypto, string $vaultKey): ?string
    {
        $kdfParams = is_array($vaultCrypto->kdf_params) ? $vaultCrypto->kdf_params : [];

        $wrappingKey = $this->cryptoService->deriveWrappingKey(
            $vaultKey,
            $vaultCrypto->kdf_salt,
            isset($kdfParams['opslimit']) ? (int) $kdfParams['opslimit'] : null,
            isset($kdfParams['memlimit']) ? (int) $kdfParams['memlimit'] : null,
        );

        try {
            return $this->cryptoService->unwrapDataKey(
                $vaultCrypto->wrapped_data_key,
                $vaultCrypto->wrap_nonce,
                $wrappingKey,
            );
        } catch (RuntimeException) {
            return null;
        } finally {
            // Best effort: PHP copies strings, so this clears one buffer, not every trace.
            sodium_memzero($wrappingKey);
        }
    }

    public function decryptVaultName(Vault $vault, string $dataKey): string
    {
        return $this->cryptoService->decryptEnvelope($vault->name_enc, $dataKey);
    }

    public function decryptVaultDescription(Vault $vault, string $dataKey): ?string
    {
        if ($vault->description_enc === null) {
            return null;
        }

        return $this->cryptoService->decryptEnvelope($vault->description_enc, $dataKey);
    }
}
