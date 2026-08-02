<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Crypto;

use EvilStudio\Cryptosik\Services\Crypto\CryptoService;
use RuntimeException;
use Tests\TestCase;

class CryptoServiceTest extends TestCase
{
    private CryptoService $crypto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crypto = app(CryptoService::class);
    }

    public function test_encrypt_decrypt_roundtrip_preserves_text(): void
    {
        $dataKey = $this->crypto->generateDataKey();
        $plaintext = "Zażółć gęślą jaźń\nlinia druga\t- tabulator";

        $payload = $this->crypto->encryptWithDataKey($plaintext, $dataKey);
        $decrypted = $this->crypto->decryptWithDataKey($payload['ciphertext'], $payload['nonce'], $dataKey);

        $this->assertSame($plaintext, $decrypted);
    }

    public function test_encrypt_decrypt_roundtrip_preserves_binary_payloads(): void
    {
        $dataKey = $this->crypto->generateDataKey();
        $plaintext = random_bytes(4096);

        $payload = $this->crypto->encryptWithDataKey($plaintext, $dataKey);
        $decrypted = $this->crypto->decryptWithDataKey($payload['ciphertext'], $payload['nonce'], $dataKey);

        $this->assertSame($plaintext, $decrypted);
    }

    public function test_every_encryption_uses_a_fresh_nonce(): void
    {
        $dataKey = $this->crypto->generateDataKey();

        $nonces = [];

        for ($i = 0; $i < 32; $i++) {
            $nonces[] = $this->crypto->encryptWithDataKey('same plaintext every time', $dataKey)['nonce'];
        }

        $this->assertCount(32, array_unique($nonces), 'Nonce reuse would break XChaCha20-Poly1305.');
    }

    public function test_decrypt_rejects_a_tampered_ciphertext(): void
    {
        $dataKey = $this->crypto->generateDataKey();
        $payload = $this->crypto->encryptWithDataKey('authentic message', $dataKey);

        $raw = (string) base64_decode($payload['ciphertext'], true);
        $raw[0] = chr(ord($raw[0]) ^ 0x01);

        $this->expectException(RuntimeException::class);

        $this->crypto->decryptWithDataKey(base64_encode($raw), $payload['nonce'], $dataKey);
    }

    public function test_decrypt_rejects_a_tampered_authentication_tag(): void
    {
        $dataKey = $this->crypto->generateDataKey();
        $payload = $this->crypto->encryptWithDataKey('authentic message', $dataKey);

        $raw = (string) base64_decode($payload['ciphertext'], true);
        $lastIndex = strlen($raw) - 1;
        $raw[$lastIndex] = chr(ord($raw[$lastIndex]) ^ 0x01);

        $this->expectException(RuntimeException::class);

        $this->crypto->decryptWithDataKey(base64_encode($raw), $payload['nonce'], $dataKey);
    }

    public function test_decrypt_rejects_a_foreign_data_key(): void
    {
        $payload = $this->crypto->encryptWithDataKey('secret', $this->crypto->generateDataKey());

        $this->expectException(RuntimeException::class);

        $this->crypto->decryptWithDataKey($payload['ciphertext'], $payload['nonce'], $this->crypto->generateDataKey());
    }

    public function test_decrypt_rejects_a_swapped_nonce(): void
    {
        $dataKey = $this->crypto->generateDataKey();
        $payload = $this->crypto->encryptWithDataKey('secret', $dataKey);
        $otherNonce = $this->crypto->encryptWithDataKey('other', $dataKey)['nonce'];

        $this->expectException(RuntimeException::class);

        $this->crypto->decryptWithDataKey($payload['ciphertext'], $otherNonce, $dataKey);
    }

    public function test_decrypt_rejects_malformed_base64(): void
    {
        $this->expectException(RuntimeException::class);

        $this->crypto->decryptWithDataKey('!!! not base64 !!!', 'also not base64 ###', $this->crypto->generateDataKey());
    }

    public function test_envelope_roundtrip(): void
    {
        $dataKey = $this->crypto->generateDataKey();

        $envelope = $this->crypto->encryptEnvelope('enveloped secret', $dataKey);

        $this->assertJson($envelope);
        $this->assertSame('enveloped secret', $this->crypto->decryptEnvelope($envelope, $dataKey));
    }

    public function test_envelope_rejects_invalid_json(): void
    {
        $this->expectException(RuntimeException::class);

        $this->crypto->decryptEnvelope('{ not json', $this->crypto->generateDataKey());
    }

    public function test_envelope_rejects_a_payload_without_ciphertext_and_nonce(): void
    {
        $this->expectException(RuntimeException::class);

        $this->crypto->decryptEnvelope('{"ciphertext":"abc"}', $this->crypto->generateDataKey());
    }

    public function test_data_key_wrap_roundtrip(): void
    {
        $dataKey = $this->crypto->generateDataKey();
        $wrappingKey = $this->crypto->deriveWrappingKey('vault-key', $this->crypto->generateSalt());

        $wrapped = $this->crypto->wrapDataKey($dataKey, $wrappingKey);
        $unwrapped = $this->crypto->unwrapDataKey($wrapped['ciphertext'], $wrapped['nonce'], $wrappingKey);

        $this->assertSame($dataKey, $unwrapped);
    }

    public function test_unwrap_rejects_a_wrapping_key_derived_from_a_different_vault_key(): void
    {
        $salt = $this->crypto->generateSalt();
        $dataKey = $this->crypto->generateDataKey();

        $wrapped = $this->crypto->wrapDataKey($dataKey, $this->crypto->deriveWrappingKey('correct-key', $salt));

        $this->expectException(RuntimeException::class);

        $this->crypto->unwrapDataKey(
            $wrapped['ciphertext'],
            $wrapped['nonce'],
            $this->crypto->deriveWrappingKey('wrong-key', $salt),
        );
    }

    public function test_derive_wrapping_key_is_deterministic_for_the_same_tuple(): void
    {
        $salt = $this->crypto->generateSalt();

        $this->assertSame(
            $this->crypto->deriveWrappingKey('vault-key', $salt, 1, 16384),
            $this->crypto->deriveWrappingKey('vault-key', $salt, 1, 16384),
        );
    }

    public function test_derive_wrapping_key_changes_with_every_input(): void
    {
        $salt = $this->crypto->generateSalt();
        $baseline = $this->crypto->deriveWrappingKey('vault-key', $salt, 1, 16384);

        $this->assertNotSame($baseline, $this->crypto->deriveWrappingKey('other-key', $salt, 1, 16384));
        $this->assertNotSame($baseline, $this->crypto->deriveWrappingKey('vault-key', $this->crypto->generateSalt(), 1, 16384));

        // The two below are why kdf_params must be read back per vault instead of from config.
        $this->assertNotSame($baseline, $this->crypto->deriveWrappingKey('vault-key', $salt, 2, 16384));
        $this->assertNotSame($baseline, $this->crypto->deriveWrappingKey('vault-key', $salt, 1, 32768));
    }

    public function test_generated_material_has_the_expected_libsodium_sizes(): void
    {
        $dataKey = (string) base64_decode($this->crypto->generateDataKey(), true);
        $salt = (string) base64_decode($this->crypto->generateSalt(), true);
        $nonce = (string) base64_decode(
            $this->crypto->encryptWithDataKey('x', $this->crypto->generateDataKey())['nonce'],
            true,
        );

        $this->assertSame(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES, strlen($dataKey));
        $this->assertSame(SODIUM_CRYPTO_PWHASH_SALTBYTES, strlen($salt));
        $this->assertSame(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES, strlen($nonce));
    }
}
