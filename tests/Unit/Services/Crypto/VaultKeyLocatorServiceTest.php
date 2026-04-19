<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Crypto;

use EvilStudio\Cryptosik\Services\Crypto\VaultKeyLocatorService;
use RuntimeException;
use Tests\TestCase;

class VaultKeyLocatorServiceTest extends TestCase
{
    public function test_locator_is_deterministic_for_same_key(): void
    {
        $service = new VaultKeyLocatorService();
        $vaultKey = 'my-vault-key-123456';

        $first = $service->deriveLocator($vaultKey);
        $second = $service->deriveLocator($vaultKey);

        $this->assertSame($first, $second);
        $this->assertSame(64, strlen($first));
    }

    public function test_locator_changes_for_different_keys(): void
    {
        $service = new VaultKeyLocatorService();

        $first = $service->deriveLocator('vault-key-1');
        $second = $service->deriveLocator('vault-key-2');

        $this->assertNotSame($first, $second);
    }

    public function test_it_requires_lookup_pepper_and_salt(): void
    {
        config([
            'cryptosik.crypto.lookup_pepper' => '',
            'cryptosik.crypto.lookup_salt' => '',
        ]);

        $this->expectException(RuntimeException::class);

        (new VaultKeyLocatorService())->deriveLocator('vault-key');
    }
}
