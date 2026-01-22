<?php

namespace App\Services\Task\DataExtraction;

use App\Models\TeamObject\TeamObject;

/**
 * Value object representing the result of duplicate resolution for a TeamObject.
 *
 * Used by DuplicateRecordResolver to return resolution outcomes with detailed explanation
 * of whether extracted data matches an existing record.
 */
readonly class ResolutionResult
{
    public function __construct(
        public bool $isDuplicate,
        public ?int $existingObjectId,
        public ?TeamObject $existingObject,
        public string $explanation,
        public float $confidence = 0.0,
        public ?array $updatedValues = null
    ) {
    }

    /**
     * Check if a duplicate was found.
     */
    public function hasDuplicate(): bool
    {
        return $this->isDuplicate && $this->existingObjectId !== null;
    }

    /**
     * Get the existing object if a duplicate was found.
     */
    public function getExistingObject(): ?TeamObject
    {
        return $this->existingObject;
    }

    /**
     * Get the confidence score (0.0-1.0) of the resolution.
     */
    public function getConfidence(): float
    {
        return $this->confidence;
    }

    /**
     * Get a human-readable summary of the resolution.
     */
    public function getSummary(): string
    {
        if ($this->hasDuplicate()) {
            $confidencePercent = round($this->confidence * 100);

            return "Duplicate found (ID: {$this->existingObjectId}, {$confidencePercent}% confidence): {$this->explanation}";
        }

        return "No duplicate found: {$this->explanation}";
    }

    /**
     * Check if there are updated values to apply to the existing record.
     */
    public function hasUpdatedValues(): bool
    {
        return $this->hasDuplicate() && !empty($this->updatedValues);
    }

    /**
     * Get the updated values to apply to the existing record.
     *
     * Returns an associative array of field name => best value pairs.
     * Only contains fields where the extracted data has better/more complete values.
     */
    public function getUpdatedValues(): ?array
    {
        return $this->updatedValues;
    }
}
