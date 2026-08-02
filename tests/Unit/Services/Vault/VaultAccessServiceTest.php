<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Vault;

use EvilStudio\Cryptosik\Models\User;
use EvilStudio\Cryptosik\Models\Vault;
use EvilStudio\Cryptosik\Models\VaultCrypto;
use EvilStudio\Cryptosik\Services\Vault\VaultAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class VaultAccessServiceTest extends TestCase
{
    use RefreshDatabase;

    private const VAULT_KEY = 'kdf-params-vault-key-001';

    public function test_unlock_uses_stored_kdf_params_after_the_config_changed(): void
    {
        $this->setArgonConfig(opslimit: 1, memlimit: 16384);

        [$owner, $vault] = $this->createVault();

        $storedParams = VaultCrypto::query()->find($vault->id)?->kdf_params;

        $this->assertSame(1, (int) ($storedParams['opslimit'] ?? 0));
        $this->assertSame(16384, (int) ($storedParams['memlimit'] ?? 0));

        // An operator hardens the KDF after the vault already exists.
        $this->setArgonConfig(opslimit: 2, memlimit: 32768);

        $result = app(VaultAccessService::class)->unlockVaultForUser($owner, self::VAULT_KEY);

        $this->assertNotNull($result, 'Existing vaults must keep opening after an Argon2id config change.');
        $this->assertSame($vault->id, $result->vault->id);
        $this->assertNotSame('', (string) $result->dataKey);
    }

    public function test_unlock_falls_back_to_config_when_kdf_params_are_absent(): void
    {
        $this->setArgonConfig(opslimit: 1, memlimit: 16384);

        [$owner, $vault] = $this->createVault();

        $crypto = VaultCrypto::query()->find($vault->id);
        $crypto->kdf_params = [];
        $crypto->save();

        $result = app(VaultAccessService::class)->unlockVaultForUser($owner, self::VAULT_KEY);

        $this->assertNotNull($result, 'Rows without kdf_params must fall back to the live config.');
    }

    public function test_unlock_still_rejects_a_wrong_vault_key(): void
    {
        $this->setArgonConfig(opslimit: 1, memlimit: 16384);

        [$owner] = $this->createVault();

        $result = app(VaultAccessService::class)->unlockVaultForUser($owner, 'definitely-not-the-key');

        $this->assertNull($result);
    }

    public function test_unlock_resolves_the_right_vault_among_several_memberships(): void
    {
        $this->setArgonConfig(opslimit: 1, memlimit: 16384);

        [$owner, $first] = $this->createVault();

        $service = app(VaultAccessService::class);

        $second = $service->createVault($owner, 'second-vault-key-002', 'Second vault', null);
        $third = $service->createVault($owner, 'third-vault-key-003', 'Third vault', null);

        $this->assertSame($first->id, $service->unlockVaultForUser($owner, self::VAULT_KEY)->vault->id);
        $this->assertSame($second->id, $service->unlockVaultForUser($owner, 'second-vault-key-002')->vault->id);
        $this->assertSame($third->id, $service->unlockVaultForUser($owner, 'third-vault-key-003')->vault->id);
    }

    public function test_unlock_ignores_a_vault_the_user_does_not_belong_to(): void
    {
        $this->setArgonConfig(opslimit: 1, memlimit: 16384);

        [$owner] = $this->createVault();

        $stranger = User::query()->create([
            'email' => sprintf('stranger-%s@example.com', uniqid('', true)),
            'nickname' => 'Stranger',
            'locale' => 'en',
            'is_active' => true,
        ]);

        $this->assertNotNull(app(VaultAccessService::class)->unlockVaultForUser($owner, self::VAULT_KEY));
        $this->assertNull(app(VaultAccessService::class)->unlockVaultForUser($stranger, self::VAULT_KEY));
    }

    public function test_creating_a_second_vault_with_the_same_key_is_rejected(): void
    {
        $this->setArgonConfig(opslimit: 1, memlimit: 16384);

        [$owner] = $this->createVault();

        $this->expectException(RuntimeException::class);

        app(VaultAccessService::class)->createVault($owner, self::VAULT_KEY, 'Duplicate vault', null);
    }

    private function setArgonConfig(int $opslimit, int $memlimit): void
    {
        config([
            'cryptosik.crypto.argon_opslimit' => $opslimit,
            'cryptosik.crypto.argon_memlimit' => $memlimit,
        ]);
    }

    /**
     * @return array{User, Vault}
     */
    private function createVault(): array
    {
        $owner = User::query()->create([
            'email' => sprintf('owner-%s@example.com', uniqid('', true)),
            'nickname' => 'Owner',
            'locale' => 'en',
            'is_active' => true,
        ]);

        $vault = app(VaultAccessService::class)->createVault(
            owner: $owner,
            vaultKey: self::VAULT_KEY,
            name: 'KDF fixture vault',
            description: null,
        );

        return [$owner, $vault];
    }
}
