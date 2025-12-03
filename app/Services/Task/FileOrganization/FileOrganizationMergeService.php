<?php

namespace App\Services\Task\FileOrganization;

use App\Models\Task\Artifact;
use Illuminate\Support\Collection;

/**
 * FileOrganizationMergeService - Adjacency-Based File Grouping Algorithm
 *
 * This service implements the new adjacency-based algorithm for merging window results
 * into final file groups. The algorithm uses explicit adjacency signals (belongs_to_previous)
 * as a DISAMBIGUATION TOOL for low-confidence group assignments.
 *
 * Key Principles:
 * - PRIMARY grouping is by group_name (NOT adjacency)
 * - Adjacency is used to RESOLVE low-confidence files only
 * - High confidence group assignments are NEVER overridden by adjacency
 * - Ties in adjacency resolve to PREVIOUS group (continuity over forward association)
 *
 * Algorithm Phases:
 * 1. Collect all file data from window artifacts
 * 2. Build adjacency scores (MAX of belongs_to_previous across windows)
 * 3. Build initial groups by group_name (PRIMARY grouping)
 * 4. Use adjacency to resolve low-confidence files
 * 5. Handle blank pages based on configuration
 * 6. Merge similar group names
 *
 * @see /home/newms/.claude/plans/breezy-finding-wind.md for complete algorithm specification
 */
class FileOrganizationMergeService
{
    /**
     * Default configuration values for the merge algorithm.
     */
    private const DEFAULT_GROUP_CONFIDENCE_THRESHOLD = 3;

    private const DEFAULT_ADJACENCY_BOUNDARY_THRESHOLD = 2;

    private const DEFAULT_BLANK_PAGE_HANDLING = 'join_previous';

    private const DEFAULT_NAME_SIMILARITY_THRESHOLD = 0.7;

    /**
     * Configuration for this merge operation.
     */
    private array $config;

    /**
     * All file data collected from windows.
     * Structure: [page_number => [...file data...]]
     */
    private array $fileData = [];

    /**
     * Adjacency scores between adjacent files.
     * Structure: [page_number => belongs_to_previous_score]
     */
    private array $adjacencyScores = [];

    /**
     * Final group assignments.
     * Structure: [page_number => group_name]
     */
    private array $fileGroupAssignments = [];

    /**
     * Merge window results into final file groups.
     *
     * @param  Collection<Artifact>  $artifacts  Collection of window artifacts
     * @param  array  $config  Configuration overrides
     * @return array Final groups and file-to-group mapping
     */
    public function mergeWindowResults(Collection $artifacts, array $config = []): array
    {
        // Initialize configuration
        $this->config = $this->mergeConfig($config);

        // Reset state
        $this->fileData             = [];
        $this->adjacencyScores      = [];
        $this->fileGroupAssignments = [];

        // Phase 1: Collect all file data from window artifacts
        $this->collectFileDataFromWindows($artifacts);

        // If no files, return empty result
        if (empty($this->fileData)) {
            return [
                'groups'                => [],
                'file_to_group_mapping' => [],
            ];
        }

        // Phase 2: Build adjacency scores (MAX across windows)
        $this->buildAdjacencyScores();

        // Phase 3: Build initial groups by group_name (PRIMARY grouping)
        $this->buildInitialGroupAssignments();

        // Phase 4: Use adjacency to resolve low-confidence files
        $this->resolveFilesUsingAdjacency();

        // Phase 5: Handle blank pages based on configuration
        $this->handleBlankPages();

        // Phase 6: Merge similar group names (future enhancement - currently returns as-is)
        // $this->mergeSimilarGroupNames();

        // Build final output
        return $this->buildFinalOutput();
    }

    /**
     * Phase 1: Collect all file data from window artifacts.
     *
     * For each file across all windows:
     * - Collect all group_name votes with confidence
     * - Collect all belongs_to_previous votes
     * - Track all metadata
     *
     * Format: { "files": [ { "page_number": 1, "group_name": "...", "group_name_confidence": 5, ... } ] }
     */
    private function collectFileDataFromWindows(Collection $artifacts): void
    {
        foreach ($artifacts as $artifact) {
            $files = $artifact->json_content['files'] ?? [];

            foreach ($files as $file) {
                $pageNumber = $file['page_number'];

                // Initialize file data if not exists
                if (!isset($this->fileData[$pageNumber])) {
                    $this->fileData[$pageNumber] = [
                        'page_number'            => $pageNumber,
                        'group_votes'            => [],
                        'adjacency_votes'        => [],
                        'belongs_to_prev_reason' => null,
                    ];
                }

                // Extract group_name directly from file
                $groupName = $file['group_name'] ?? '';

                // Collect group name vote with confidence
                $this->fileData[$pageNumber]['group_votes'][] = [
                    'group_name'  => $groupName,
                    'confidence'  => $file['group_name_confidence'] ?? 5,
                    'explanation' => $file['group_explanation']     ?? '',
                ];

                // Collect adjacency vote if present
                if (isset($file['belongs_to_previous'])) {
                    $this->fileData[$pageNumber]['adjacency_votes'][] = $file['belongs_to_previous'];
                }

                // Store reason if provided
                if (isset($file['belongs_to_previous_reason'])) {
                    $this->fileData[$pageNumber]['belongs_to_prev_reason'] = $file['belongs_to_previous_reason'];
                }
            }
        }
    }

    /**
     * Phase 2: Build adjacency scores.
     *
     * For each file, calculate the MAX belongs_to_previous score across all windows.
     * This represents the strongest signal of connection to the previous file.
     */
    private function buildAdjacencyScores(): void
    {
        foreach ($this->fileData as $pageNumber => $data) {
            $votes = $data['adjacency_votes'];

            // Filter out null values (first page in window)
            $validVotes = array_filter($votes, fn($vote) => $vote !== null);

            if (!empty($validVotes)) {
                // Use MAX across all windows
                $this->adjacencyScores[$pageNumber] = max($validVotes);
            } else {
                // No adjacency data available (e.g., always first in window)
                $this->adjacencyScores[$pageNumber] = null;
            }
        }
    }

    /**
     * Phase 3: Build initial group assignments by group_name.
     *
     * This is the PRIMARY grouping mechanism. Files with the same group_name
     * belong together. Use highest confidence vote when windows disagree.
     */
    private function buildInitialGroupAssignments(): void
    {
        foreach ($this->fileData as $pageNumber => $data) {
            $votes = $data['group_votes'];

            // Find vote with highest confidence
            $bestVote = $this->selectBestGroupVote($votes);

            // Assign file to this group
            $this->fileGroupAssignments[$pageNumber] = $bestVote['group_name'];

            // Store the confidence and explanation back in file data
            $this->fileData[$pageNumber]['group_name']        = $bestVote['group_name'];
            $this->fileData[$pageNumber]['group_confidence']  = $bestVote['confidence'];
            $this->fileData[$pageNumber]['group_explanation'] = $bestVote['explanation'];
        }
    }

    /**
     * Phase 4: Use adjacency to resolve low-confidence files.
     *
     * For files with group_name_confidence below threshold, use adjacency
     * to decide if they should stay in current group or be reassigned.
     *
     * Resolution Rules:
     * 1. HIGH group_conf (>= threshold): Keep current assignment, ignore adjacency
     * 2. LOW group_conf + HIGH adjacency to prev: Keep with previous group
     * 3. LOW group_conf + LOW adjacency to prev + HIGH adjacency from next: Reassign to next group
     * 4. TIES resolve to PREVIOUS group (critical!)
     */
    private function resolveFilesUsingAdjacency(): void
    {
        $pageNumbers = array_keys($this->fileData);
        sort($pageNumbers);

        foreach ($pageNumbers as $index => $pageNumber) {
            $file       = $this->fileData[$pageNumber];
            $confidence = $file['group_confidence'];
            $groupName  = $this->fileGroupAssignments[$pageNumber];

            // Skip blank pages - they're handled in Phase 5
            if ($groupName === '') {
                continue;
            }

            // Rule 1: Files with confidence ABOVE threshold keep assignment, ignore adjacency
            // Files AT or BELOW threshold are eligible for adjacency-based reassignment
            if ($confidence > $this->config['group_confidence_threshold']) {
                continue;
            }

            // This file has LOW confidence - eligible for adjacency-based resolution
            $adjacencyToPrev = $this->adjacencyScores[$pageNumber] ?? null;

            // If no adjacency data, can't resolve - keep current assignment
            if ($adjacencyToPrev === null) {
                continue;
            }

            // Get next file's adjacency score (if exists)
            $nextIndex         = $index + 1;
            $adjacencyFromNext = null;
            if ($nextIndex < count($pageNumbers)) {
                $nextPageNumber    = $pageNumbers[$nextIndex];
                $adjacencyFromNext = $this->adjacencyScores[$nextPageNumber] ?? null;
            }

            // Rule 2: Compare adjacency signals to determine reassignment
            // - If adjacencyFromNext > adjacencyToPrev: Reassign to next (strong forward signal)
            // - If adjacencyToPrev >= threshold: Reassign to previous (strong backward signal)
            // - Otherwise: Keep in current assigned group (no strong signal either way)

            // Check if next file has STRONGER claim than previous
            if ($adjacencyFromNext !== null && $adjacencyFromNext > $adjacencyToPrev) {
                // Next file's claim is stronger - reassign to next file's group
                $nextGroup                               = $this->fileGroupAssignments[$pageNumbers[$nextIndex]];
                $this->fileGroupAssignments[$pageNumber] = $nextGroup;

                continue;
            }

            // Rule 3: Only reassign to previous if adjacency is strong enough
            // If adjacency is weak or there's no previous file, keep in current assigned group
            if ($adjacencyToPrev >= $this->config['adjacency_boundary_threshold'] && $index > 0) {
                $prevPageNumber = $pageNumbers[$index - 1];
                $prevGroup      = $this->fileGroupAssignments[$prevPageNumber];

                // Reassign this file to previous file's group
                $this->fileGroupAssignments[$pageNumber] = $prevGroup;
            }
            // Otherwise: Keep in current assigned group (no strong adjacency signal)
        }
    }

    /**
     * Phase 5: Handle blank pages based on configuration.
     *
     * Blank pages are identified by empty group_name ('').
     *
     * Handling options:
     * - join_previous: Blank pages join previous group (or forward if first)
     * - create_blank_group: Blank pages stay in their own group with empty name
     * - discard: Blank pages removed from output
     */
    private function handleBlankPages(): void
    {
        $handling = $this->config['blank_page_handling'];

        if ($handling === 'create_blank_group') {
            // Keep blank pages as-is with empty group name
            return;
        }

        $pageNumbers = array_keys($this->fileData);
        sort($pageNumbers);

        foreach ($pageNumbers as $index => $pageNumber) {
            $groupName = $this->fileGroupAssignments[$pageNumber];

            // Not a blank page - skip
            if ($groupName !== '') {
                continue;
            }

            // This is a blank page
            if ($handling === 'discard') {
                // Remove from assignments
                unset($this->fileGroupAssignments[$pageNumber]);

                continue;
            }

            // handling === 'join_previous'
            // Find previous non-blank group
            $targetGroup = $this->findNonBlankGroup($pageNumbers, $index, 'previous');

            // If no previous group, merge forward to next non-blank group
            if ($targetGroup === null) {
                $targetGroup = $this->findNonBlankGroup($pageNumbers, $index, 'next');
            }

            // Reassign to target group (or leave as blank if all files are blank)
            if ($targetGroup !== null) {
                $this->fileGroupAssignments[$pageNumber] = $targetGroup;
            }
        }
    }

    /**
     * Build final output structure.
     *
     * Groups files by their final group assignments and creates:
     * - groups: Array of groups with name, files, description
     * - file_to_group_mapping: Detailed mapping of each file
     */
    private function buildFinalOutput(): array
    {
        $groups      = [];
        $fileMapping = [];

        // Group files by their final group assignment
        $filesByGroup = [];
        foreach ($this->fileGroupAssignments as $pageNumber => $groupName) {
            if (!isset($filesByGroup[$groupName])) {
                $filesByGroup[$groupName] = [];
            }
            $filesByGroup[$groupName][] = $pageNumber;
        }

        // Build groups array
        foreach ($filesByGroup as $groupName => $pageNumbers) {
            sort($pageNumbers);

            $groups[] = [
                'name'        => $groupName,
                'files'       => $pageNumbers,
                'description' => $this->buildGroupDescription($groupName, $pageNumbers),
            ];
        }

        // Build file-to-group mapping
        foreach ($this->fileGroupAssignments as $pageNumber => $groupName) {
            $fileData = $this->fileData[$pageNumber];

            $fileMapping[$pageNumber] = [
                'page_number'                => $pageNumber,
                'group_name'                 => $groupName,
                'confidence'                 => $fileData['group_confidence'],
                'explanation'                => $fileData['group_explanation']      ?? '',
                'description'                => $fileData['group_explanation']      ?? '',
                'belongs_to_previous'        => $this->adjacencyScores[$pageNumber] ?? null,
                'belongs_to_previous_reason' => $fileData['belongs_to_prev_reason'] ?? null,
            ];
        }

        return [
            'groups'                => $groups,
            'file_to_group_mapping' => $fileMapping,
        ];
    }

    /**
     * Select the best group vote from multiple windows.
     *
     * Rules:
     * 1. Highest confidence wins
     * 2. If tied, first window wins (first seen)
     */
    private function selectBestGroupVote(array $votes): array
    {
        $bestVote = null;

        foreach ($votes as $vote) {
            if ($bestVote === null || $vote['confidence'] > $bestVote['confidence']) {
                $bestVote = $vote;
            }
        }

        return $bestVote;
    }

    /**
     * Find the nearest non-blank group in specified direction.
     *
     * @param  array  $pageNumbers  Sorted array of page numbers
     * @param  int  $currentIndex  Current index in pageNumbers array
     * @param  string  $direction  'previous' or 'next'
     * @return string|null Group name or null if not found
     */
    private function findNonBlankGroup(array $pageNumbers, int $currentIndex, string $direction): ?string
    {
        if ($direction === 'previous') {
            // Search backward
            for ($i = $currentIndex - 1; $i >= 0; $i--) {
                $pageNumber = $pageNumbers[$i];
                $groupName  = $this->fileGroupAssignments[$pageNumber];
                if ($groupName !== '') {
                    return $groupName;
                }
            }
        } else {
            // Search forward
            for ($i = $currentIndex + 1; $i < count($pageNumbers); $i++) {
                $pageNumber = $pageNumbers[$i];
                $groupName  = $this->fileGroupAssignments[$pageNumber];
                if ($groupName !== '') {
                    return $groupName;
                }
            }
        }

        return null;
    }

    /**
     * Build group description from files.
     */
    private function buildGroupDescription(string $groupName, array $pageNumbers): string
    {
        if ($groupName === '') {
            return 'Blank pages';
        }

        $count    = count($pageNumbers);
        $pageList = $this->formatPageRange($pageNumbers);

        return "$groupName ($count " . ($count === 1 ? 'page' : 'pages') . ": $pageList)";
    }

    /**
     * Format page numbers into a readable range.
     *
     * Examples:
     * [1, 2, 3] => "1-3"
     * [1, 3, 4, 5] => "1, 3-5"
     */
    private function formatPageRange(array $pageNumbers): string
    {
        if (empty($pageNumbers)) {
            return '';
        }

        sort($pageNumbers);
        $ranges = [];
        $start  = $pageNumbers[0];
        $end    = $pageNumbers[0];

        for ($i = 1; $i < count($pageNumbers); $i++) {
            if ($pageNumbers[$i] === $end + 1) {
                // Continue range
                $end = $pageNumbers[$i];
            } else {
                // End current range, start new one
                $ranges[] = $start === $end ? "$start" : "$start-$end";
                $start    = $pageNumbers[$i];
                $end      = $pageNumbers[$i];
            }
        }

        // Add final range
        $ranges[] = $start === $end ? "$start" : "$start-$end";

        return implode(', ', $ranges);
    }

    /**
     * Merge provided config with defaults.
     */
    private function mergeConfig(array $config): array
    {
        return array_merge([
            'group_confidence_threshold'   => self::DEFAULT_GROUP_CONFIDENCE_THRESHOLD,
            'adjacency_boundary_threshold' => self::DEFAULT_ADJACENCY_BOUNDARY_THRESHOLD,
            'blank_page_handling'          => self::DEFAULT_BLANK_PAGE_HANDLING,
            'name_similarity_threshold'    => self::DEFAULT_NAME_SIMILARITY_THRESHOLD,
        ], $config);
    }
}
