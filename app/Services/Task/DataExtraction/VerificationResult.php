<?php

namespace App\Services\Task\DataExtraction;

/**
 * Value object representing the result of verifying an existing TeamObject against source artifacts.
 *
 * Used by ExistingObjectVerifier to return verification outcomes with detailed explanation
 * of which fields matched or mismatched during verification.
 */
readonly class VerificationResult
{
    public function __construct(
        public bool $matches,
        public string $explanation,
        public array $matchedFields = [],
        public array $mismatchedFields = []
    ) {
    }

    /**
     * Check if the verification succeeded (object matches source artifacts).
     */
    public function isMatch(): bool
    {
        return $this->matches;
    }

    /**
     * Get a human-readable summary of the verification.
     */
    public function getSummary(): string
    {
        if ($this->matches) {
            return "Verification passed: {$this->explanation}";
        }

        return "Verification failed: {$this->explanation}";
    }
}
