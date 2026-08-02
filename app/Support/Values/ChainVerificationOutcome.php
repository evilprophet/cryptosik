<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Support\Values;

use EvilStudio\Cryptosik\Enums\ChainVerificationResult;

final class ChainVerificationOutcome
{
    /**
     * @param  array<string, mixed>  $details
     */
    private function __construct(
        public readonly ChainVerificationResult $result,
        public readonly ?int $brokenSequenceNo,
        public readonly array $details,
    ) {}

    public static function passed(int $entriesVerified): self
    {
        return new self(ChainVerificationResult::Passed, null, ['entries_verified' => $entriesVerified]);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function failed(string $error, ?int $brokenSequenceNo, array $details = []): self
    {
        return new self(ChainVerificationResult::Failed, $brokenSequenceNo, ['error' => $error] + $details);
    }

    public function error(): string
    {
        return (string) ($this->details['error'] ?? 'Unknown integrity verification error.');
    }
}
