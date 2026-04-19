<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Services\Vault;

use EvilStudio\Cryptosik\Enums\VaultMemberRole;
use EvilStudio\Cryptosik\Enums\VaultStatus;
use EvilStudio\Cryptosik\Models\User;
use EvilStudio\Cryptosik\Models\Vault;
use EvilStudio\Cryptosik\Models\VaultChainState;
use EvilStudio\Cryptosik\Models\VaultCrypto;
use EvilStudio\Cryptosik\Models\VaultMember;
use EvilStudio\Cryptosik\Services\Crypto\CryptoService;
use EvilStudio\Cryptosik\Services\Crypto\VaultKeyLocatorService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VaultAccessService
{
    public function __construct(
        private readonly CryptoService $cryptoService,
        private readonly VaultKeyLocatorService $locatorService,
    ) {
    }

    public function createVault(User $owner, string $vaultKey, string $name, ?string $description): Vault
    {
        $locator = $this->locatorService->deriveLocator($vaultKey);

        if (VaultCrypto::query()->where('vault_locator', $locator)->exists()) {
            throw new RuntimeException('Vault key already exists. Duplicate keys are not allowed.');
        }

        $dataKeyB64 = $this->cryptoService->generateDataKey();
        $kdfSalt = $this->cryptoService->generateSalt();
        $wrappingKey = $this->cryptoService->deriveWrappingKey($vaultKey, $kdfSalt);
        $wrapped = $this->cryptoService->wrapDataKey($dataKeyB64, $wrappingKey);

        return DB::transaction(function () use (
            $owner,
            $name,
            $description,
            $dataKeyB64,
            $locator,
            $kdfSalt,
            $wrapped,
            $vaultKey,
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
                'vault_locator' => $locator,
                'kdf_salt' => $kdfSalt,
                'kdf_params' => [
                    'opslimit' => (int)config('cryptosik.crypto.argon_opslimit'),
                    'memlimit' => (int)config('cryptosik.crypto.argon_memlimit'),
                ],
                'wrapped_data_key' => $wrapped['ciphertext'],
                'wrap_nonce' => $wrapped['nonce'],
                'key_fingerprint' => $this->locatorService->deriveFingerprint($vaultKey),
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

    public function unlockVaultForUser(User $user, string $vaultKey): ?array
    {
        $locator = $this->locatorService->deriveLocator($vaultKey);

        $vaultCrypto = VaultCrypto::query()->with('vault')->where('vault_locator', $locator)->first();

        if ($vaultCrypto === null || $vaultCrypto->vault === null) {
            return null;
        }

        $vault = $vaultCrypto->vault;

        if ($vault->status === VaultStatus::SoftDeleted || $vault->deleted_at !== null) {
            return null;
        }

        $isMember = $vault->members()->where('user_id', $user->id)->exists();

        if (!$isMember) {
            return null;
        }

        $wrappingKey = $this->cryptoService->deriveWrappingKey($vaultKey, $vaultCrypto->kdf_salt);

        try {
            $dataKey = $this->cryptoService->unwrapDataKey(
                $vaultCrypto->wrapped_data_key,
                $vaultCrypto->wrap_nonce,
                $wrappingKey,
            );
        } catch (RuntimeException) {
            return null;
        }

        return [
            'vault' => $vault,
            'data_key' => $dataKey,
        ];
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
