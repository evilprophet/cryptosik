<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Services\Crypto;

use EvilStudio\Cryptosik\Exceptions\CryptoOperationException;

class EnvelopeReader
{
    public const UNREADABLE = '[Unable to decrypt]';

    public function __construct(private readonly CryptoService $cryptoService) {}

    // One unreadable field must not take down a page of otherwise valid entries.
    public function decryptOrPlaceholder(string $encryptedEnvelope, string $dataKey): string
    {
        try {
            return $this->cryptoService->decryptEnvelope($encryptedEnvelope, $dataKey);
        } catch (CryptoOperationException) {
            return self::UNREADABLE;
        }
    }
}
