<?php

namespace App\Services\Task\FileOrganization;

use Newms87\Danx\Traits\HasDebugLogging;

/**
 * Manages page context gathering for resolution processes.
 */
class PageContextService
{
    use HasDebugLogging;

    /**
     * Gather context page numbers (2 before and 2 after each target page).
     * Deduplicates overlapping ranges.
     *
     * @param  array  $targetPageNumbers  Array of page numbers needing context
     * @return array Array of context page numbers (excludes target pages)
     */
    public function gatherContextPageNumbers(array $targetPageNumbers): array
    {
        static::logDebug('Gathering context pages for ' . count($targetPageNumbers) . ' target pages');

        $contextPages = [];

        foreach ($targetPageNumbers as $pageNumber) {
            // Add 2 pages before
            for ($i = 2; $i >= 1; $i--) {
                $beforePage = $pageNumber - $i;
                if ($beforePage > 0 && !in_array($beforePage, $targetPageNumbers)) {
                    $contextPages[] = $beforePage;
                }
            }

            // Add 2 pages after
            for ($i = 1; $i <= 2; $i++) {
                $afterPage = $pageNumber + $i;
                if (!in_array($afterPage, $targetPageNumbers)) {
                    $contextPages[] = $afterPage;
                }
            }
        }

        $uniqueContextPages = array_unique($contextPages);
        static::logDebug('Found ' . count($uniqueContextPages) . ' unique context pages');

        return $uniqueContextPages;
    }
}
