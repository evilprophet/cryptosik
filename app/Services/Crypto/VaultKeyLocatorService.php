<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Services\Crypto;

use RuntimeException;

class VaultKeyLocatorService
{
    public function deriveLocator(string $vaultKey): string
    {
        [$pepper, $salt] = $this->guardSecrets();
        $material = hash_hkdf('sha256', $vaultKey, 32, 'cryptosik-vault-locator', $salt);

        return hash_hmac('sha256', $material, $pepper);
    }

    public function deriveFingerprint(string $vaultKey): string
    {
        [$pepper, $salt] = $this->guardSecrets();
        $material = hash_hkdf('sha256', $vaultKey, 32, 'cryptosik-vault-fingerprint', $salt);

        return hash_hmac('sha256', $material, $pepper);
    }

    private function guardSecrets(): array
    {
        $pepper = (string) config('cryptosik.crypto.lookup_pepper');
        $salt = (string) config('cryptosik.crypto.lookup_salt');

        if ($pepper === '' || $salt === '') {
            throw new RuntimeException('CRYPTOSIK_LOOKUP_PEPPER and CRYPTOSIK_LOOKUP_SALT must be configured.');
        }

        return [$pepper, $salt];
    }
}
