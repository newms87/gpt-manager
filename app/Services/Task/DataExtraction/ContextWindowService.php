<?php

namespace App\Services\Task\DataExtraction;

use App\Models\Task\Artifact;
use Illuminate\Support\Collection;
use Newms87\Danx\Exceptions\ValidationError;

/**
 * Handles context page expansion for data extraction.
 *
 * When extracting data, surrounding pages can provide valuable context for
 * understanding the content being extracted. This service expands target
 * artifacts to include pages before and after for context.
 *
 * Usage:
 * ```php
 * $service = app(ContextWindowService::class);
 * $expandedArtifacts = $service->expandWithContext(
 *     targetArtifacts: $classifiedArtifacts,
 *     allArtifacts: $allPageArtifacts,
 *     contextBefore: 2,
 *     contextAfter: 1
 * );
 * ```
 */
class ContextWindowService
{
    /**
     * Default adjacency threshold for context page inclusion.
     * Pages with belongs_to_previous >= this value are considered part of the same document.
     */
    public const int DEFAULT_ADJACENCY_THRESHOLD = 3;

    /**
     * Validate that context pages data is available on stored files.
     * Throws error if enable_context_pages is true but files haven't been organized.
     *
     * @param  Collection<Artifact>  $artifacts  The artifacts to check
     *
     * @throws ValidationError If context pages feature is used without File Organization
     */
    public function validateContextPagesAvailable(Collection $artifacts): void
    {
        $firstArtifact = $artifacts->first();
        if (!$firstArtifact) {
            return;
        }

        $storedFile = $firstArtifact->storedFiles->first();
        if (!$storedFile) {
            return;
        }

        // Check if belongs_to_previous key EXISTS in meta (even if null)
        // This indicates File Organization has been run
        if (!array_key_exists('belongs_to_previous', $storedFile->meta ?? [])) {
            throw new ValidationError(
                'Context pages feature requires File Organization to be run first. ' .
                'Either run the File Organization task before Extract Data, or disable ' .
                'the "Enable Context Pages" option in the task configuration.'
            );
        }
    }

    /**
     * Expand target artifacts to include context pages before and after.
     *
     * Returns a collection where each artifact has an `is_context_page` property
     * indicating whether it's a target (false) or context (true) page.
     *
     * When adjacencyThreshold is provided, context pages are only included if their
     * StoredFile has belongs_to_previous >= threshold, indicating they are part of
     * the same logical document. This uses data from File Organization task.
     *
     * @param  Collection<Artifact>  $targetArtifacts  The artifacts we're extracting from
     * @param  Collection<Artifact>  $allArtifacts  All available artifacts (pages) in position order
     * @param  int  $contextBefore  Number of pages before each target to include
     * @param  int  $contextAfter  Number of pages after each target to include
     * @param  int|null  $adjacencyThreshold  Minimum belongs_to_previous score for context inclusion (null = no filtering)
     * @return Collection<Artifact> Expanded collection with is_context_page property set
     */
    public function expandWithContext(
        Collection $targetArtifacts,
        Collection $allArtifacts,
        int $contextBefore,
        int $contextAfter,
        ?int $adjacencyThreshold = null
    ): Collection {
        // If no context requested, return targets as-is with is_context_page = false
        if ($contextBefore === 0 && $contextAfter === 0) {
            return $targetArtifacts->map(function (Artifact $artifact) {
                $artifact->is_context_page = false;

                return $artifact;
            });
        }

        // Sort all artifacts by position
        $sortedAll = $allArtifacts->sortBy('position')->values();
        $targetIds = $targetArtifacts->pluck('id')->toArray();

        // Build a map of artifact ID to role ('target' or 'context')
        // Target role takes precedence if an artifact is both a target and context for another target
        $roleMap = [];

        foreach ($targetArtifacts as $target) {
            $targetIndex = $sortedAll->search(fn(Artifact $a) => $a->id === $target->id);
            if ($targetIndex === false) {
                continue;
            }

            // Add context before (checking adjacency threshold if specified)
            $startIndex = max(0, $targetIndex - $contextBefore);
            for ($i = $startIndex; $i < $targetIndex; $i++) {
                $contextArtifact = $sortedAll[$i];

                // Check adjacency threshold if specified
                if ($adjacencyThreshold !== null && !$this->meetsAdjacencyThreshold($contextArtifact, $adjacencyThreshold)) {
                    continue;
                }

                // Only set as context if not already a target
                $roleMap[$contextArtifact->id] ??= 'context';
            }

            // Add target (always overrides context)
            $roleMap[$target->id] = 'target';

            // Add context after (checking adjacency threshold if specified)
            $endIndex = min($sortedAll->count() - 1, $targetIndex + $contextAfter);
            for ($i = $targetIndex + 1; $i <= $endIndex; $i++) {
                $contextArtifact = $sortedAll[$i];

                // Check adjacency threshold if specified
                if ($adjacencyThreshold !== null && !$this->meetsAdjacencyThreshold($contextArtifact, $adjacencyThreshold)) {
                    continue;
                }

                // Only set as context if not already a target
                $roleMap[$contextArtifact->id] ??= 'context';
            }
        }

        // Build result collection maintaining position order
        return $sortedAll
            ->filter(fn(Artifact $a) => isset($roleMap[$a->id]))
            ->map(function (Artifact $artifact) use ($roleMap) {
                $artifact->is_context_page = $roleMap[$artifact->id] === 'context';

                return $artifact;
            })
            ->values();
    }

    /**
     * Check if an artifact meets the adjacency threshold for context inclusion.
     *
     * Returns true if:
     * - No stored file exists (cannot determine, allow inclusion)
     * - No belongs_to_previous meta (File Organization not run, allow inclusion)
     * - belongs_to_previous >= threshold
     *
     * @param  Artifact  $artifact  The artifact to check
     * @param  int  $threshold  Minimum belongs_to_previous score
     */
    protected function meetsAdjacencyThreshold(Artifact $artifact, int $threshold): bool
    {
        $storedFile = $artifact->storedFiles->first();
        if (!$storedFile) {
            return true; // No stored file, cannot determine - allow inclusion
        }

        $meta = $storedFile->meta ?? [];

        // If belongs_to_previous key doesn't exist, File Organization wasn't run - allow inclusion
        if (!array_key_exists('belongs_to_previous', $meta)) {
            return true;
        }

        $belongsToPrevious = $meta['belongs_to_previous'];

        // If null (first page), it's a document boundary - don't include as context
        if ($belongsToPrevious === null) {
            return false;
        }

        return $belongsToPrevious >= $threshold;
    }

    /**
     * Build a prompt section explaining which pages are context vs target.
     *
     * Returns empty string if no context pages exist.
     *
     * @param  Collection<Artifact>  $artifacts  Collection with is_context_page property set
     */
    public function buildContextPromptInstructions(Collection $artifacts): string
    {
        $contextPages = $artifacts->filter(fn(Artifact $a) => $a->is_context_page ?? false);

        if ($contextPages->isEmpty()) {
            return '';
        }

        // Build human-readable page numbers (position is 0-indexed, display as 1-indexed)
        $contextPageNumbers = $contextPages
            ->pluck('position')
            ->map(fn(int $p) => $p + 1)
            ->sort()
            ->values()
            ->join(', ');

        $targetPages       = $artifacts->filter(fn(Artifact $a) => !($a->is_context_page ?? false));
        $targetPageNumbers = $targetPages
            ->pluck('position')
            ->map(fn(int $p) => $p + 1)
            ->sort()
            ->values()
            ->join(', ');

        $template = file_get_contents(resource_path('prompts/extract-data/context-pages.md'));

        return strtr($template, [
            '{{context_pages}}' => $contextPageNumbers,
            '{{target_pages}}'  => $targetPageNumbers,
        ]);
    }

    /**
     * Get count of target (non-context) artifacts in the collection.
     *
     * Useful for batch size calculations in skim mode where context pages
     * should not count toward the batch limit.
     *
     * @param  Collection<Artifact>  $artifacts  Collection with is_context_page property set
     */
    public function getTargetCount(Collection $artifacts): int
    {
        return $artifacts->filter(fn(Artifact $a) => !($a->is_context_page ?? false))->count();
    }

    /**
     * Get count of context-only artifacts in the collection.
     *
     * @param  Collection<Artifact>  $artifacts  Collection with is_context_page property set
     */
    public function getContextCount(Collection $artifacts): int
    {
        return $artifacts->filter(fn(Artifact $a) => $a->is_context_page ?? false)->count();
    }
}
