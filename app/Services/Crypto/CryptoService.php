<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Services\Crypto;

use JsonException;
use RuntimeException;

class CryptoService
{
    public function generateDataKey(): string
    {
        $key = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);

        return base64_encode($key);
    }

    public function encryptWithDataKey(string $plaintext, string $dataKeyB64): array
    {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $key = $this->decodeBase64($dataKeyB64, 'data key');

        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, '', $nonce, $key);

        return [
            'ciphertext' => base64_encode($ciphertext),
            'nonce' => base64_encode($nonce),
        ];
    }

    public function decryptWithDataKey(string $ciphertextB64, string $nonceB64, string $dataKeyB64): string
    {
        $key = $this->decodeBase64($dataKeyB64, 'data key');
        $nonce = $this->decodeBase64($nonceB64, 'nonce');
        $ciphertext = $this->decodeBase64($ciphertextB64, 'ciphertext');

        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($ciphertext, '', $nonce, $key);

        if ($plaintext === false) {
            throw new RuntimeException('Unable to decrypt payload with provided data key.');
        }

        return $plaintext;
    }

    public function encryptEnvelope(string $plaintext, string $dataKeyB64): string
    {
        $payload = $this->encryptWithDataKey($plaintext, $dataKeyB64);

        try {
            return json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode encrypted payload.', previous: $exception);
        }
    }

    public function decryptEnvelope(string $encryptedPayload, string $dataKeyB64): string
    {
        try {
            $payload = json_decode($encryptedPayload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Invalid encrypted payload JSON.', previous: $exception);
        }

        if (!isset($payload['ciphertext'], $payload['nonce'])) {
            throw new RuntimeException('Encrypted payload must include ciphertext and nonce.');
        }

        return $this->decryptWithDataKey($payload['ciphertext'], $payload['nonce'], $dataKeyB64);
    }

    public function wrapDataKey(string $dataKeyB64, string $wrappingKeyB64): array
    {
        return $this->encryptWithDataKey($dataKeyB64, $wrappingKeyB64);
    }

    public function unwrapDataKey(string $wrappedKeyB64, string $nonceB64, string $wrappingKeyB64): string
    {
        return $this->decryptWithDataKey($wrappedKeyB64, $nonceB64, $wrappingKeyB64);
    }

    public function deriveWrappingKey(string $vaultKey, string $saltB64): string
    {
        $salt = $this->decodeBase64($saltB64, 'kdf salt');
        $opslimit = (int) config('cryptosik.crypto.argon_opslimit');
        $memlimit = (int) config('cryptosik.crypto.argon_memlimit');

        $raw = sodium_crypto_pwhash(
            SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES,
            $vaultKey,
            $salt,
            $opslimit,
            $memlimit,
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13,
        );

        return base64_encode($raw);
    }

    public function generateSalt(): string
    {
        return base64_encode(random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES));
    }

    private function decodeBase64(string $input, string $label): string
    {
        $decoded = base64_decode($input, true);

        if ($decoded === false) {
            throw new RuntimeException(sprintf('Invalid base64 payload for %s.', $label));
        }

        return $decoded;
    }
}
