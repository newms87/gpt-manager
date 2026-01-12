<?php

namespace App\Services\Task\DataExtraction;

use Illuminate\Support\Collection;

/**
 * Result DTO for DuplicateRecordResolver::findCandidates().
 *
 * Contains the collection of candidate TeamObjects and an optional exactMatchId
 * when an exact match was found during the search.
 */
readonly class FindCandidatesResult
{
    /**
     * @param  Collection  $candidates  Collection of candidate TeamObjects
     * @param  int|null  $exactMatchId  ID of the exact match if found, null otherwise
     */
    public function __construct(
        public Collection $candidates,
        public ?int $exactMatchId = null,
    ) {
    }

    /**
     * Check if an exact match was found.
     */
    public function hasExactMatch(): bool
    {
        return $this->exactMatchId !== null;
    }
}
