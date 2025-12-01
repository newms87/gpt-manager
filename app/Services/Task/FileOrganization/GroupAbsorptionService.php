<?php

namespace App\Services\Task\FileOrganization;

use App\Traits\HasDebugLogging;

/**
 * Handles absorption of low-confidence groups into high-confidence groups when they overlap.
 */
class GroupAbsorptionService
{
    use HasDebugLogging;

    /**
     * Absorb files based on relative confidence when they share conflict boundaries.
     *
     * This works based on RELATIVE confidence (not absolute thresholds), so even
     * high-confidence groups can be absorbed if they conflict with HIGHER-confidence groups.
     *
     * Strategy: Conflict boundary - files grouped together in the same window follow the winner
     *
     * @param  array  $fileToGroup  Map from buildFileToGroupMapping
     * @param  array  $finalGroups  Groups from buildFinalGroups
     * @return array Map of file_id => winning_group for file absorptions to apply
     */
    public function identifyAbsorptions(array $fileToGroup, array $finalGroups): array
    {
        if (empty($finalGroups)) {
            return [];
        }

        static::logDebug('Checking for files to absorb based on relative confidence');

        $analyzer = app(GroupConfidenceAnalyzer::class);

        $groupConfidenceSummary = $analyzer->calculateGroupConfidenceSummary($finalGroups, $fileToGroup);
        $lowConfidenceGroups    = $analyzer->identifyLowConfidenceGroups($groupConfidenceSummary);
        $mediumConfidenceGroups = $analyzer->identifyMediumConfidenceGroups($groupConfidenceSummary);
        $highConfidenceGroups   = $analyzer->identifyHighConfidenceGroups($groupConfidenceSummary);

        static::logDebug('Found ' . count($lowConfidenceGroups) . ' low-confidence groups, ' .
                        count($mediumConfidenceGroups) . ' medium-confidence groups, and ' .
                        count($highConfidenceGroups) . ' high-confidence groups');

        // Check for conflict boundary absorptions across ALL groups
        // (not limited to low/medium, because relative confidence matters)
        // Pass the group confidence summary so we can compare GROUP-level confidences
        return $this->findConflictBoundaryAbsorptions(array_keys($groupConfidenceSummary), $fileToGroup, $groupConfidenceSummary);
    }

    /**
     * Find absorptions based on conflict boundaries where a higher-confidence GROUP
     * won a conflict and the losing group has adjacent files from the same window.
     *
     * IMPORTANT: Only absorbs SPECIFIC FILES from the conflict chain, not entire groups.
     * This prevents absorbing unrelated files that were never in conflict.
     *
     * This works BIDIRECTIONALLY:
     *
     * FORWARD absorption example:
     * - Window A: Pages 109-112 grouped as "Ivo DPT" (group conf 3)
     * - Window B: Page 109 gets reassigned to "ME PT" (group conf 5)
     * - Result: Absorb pages 110-112 from "Ivo DPT" into "ME PT" (forward from boundary)
     *
     * BACKWARD absorption example:
     * - Window A: Pages 93-95 in group "X" (group conf 4)
     * - Window B: Pages 95-97 in group "Y" (group conf 5)
     * - Page 95 gets absorbed into "Y"
     * - Result: Absorb pages 93-94 from "X" into "Y" (backward from boundary)
     *
     * @param  array  $allGroups  Array of ALL group names to check
     * @param  array  $fileToGroup  Map of file_id => group data
     * @param  array  $groupConfidenceSummary  Map of group_name => ['max' => int, ...]
     * @return array Map of file_id => winning_group for specific files to absorb
     */
    protected function findConflictBoundaryAbsorptions(array $allGroups, array $fileToGroup, array $groupConfidenceSummary): array
    {
        $fileAbsorptions = [];

        // Find files where a higher-confidence GROUP assignment won
        foreach ($fileToGroup as $fileId => $data) {
            $winningGroup      = $data['group_name'];
            $allExplanations   = $data['all_explanations'] ?? [];

            // Get the GROUP-level confidence (max confidence in the winning group)
            $winningGroupConfidence = $groupConfidenceSummary[$winningGroup]['max'] ?? 3;

            // Check ALL alternative assignments this file had (both forward and backward)
            foreach ($allExplanations as $explanation) {
                $losingGroup      = $explanation['group_name'];
                $losingFileConf   = $explanation['confidence']; // The ACTUAL confidence this file had in the losing group
                $winningFileConf  = $data['confidence'];        // The ACTUAL confidence this file has in the winning group

                // Skip if the losing assignment is the same as current (not a conflict)
                if ($losingGroup === $winningGroup) {
                    continue;
                }

                // Skip if the losing group isn't in our group list
                if (!in_array($losingGroup, $allGroups)) {
                    continue;
                }

                // Get the GROUP-level confidence (max confidence in each group)
                $losingGroupConfidence = $groupConfidenceSummary[$losingGroup]['max'] ?? 3;

                // CRITICAL FIX: We need to check BOTH:
                // 1. Group-level confidence comparison (prevents absorbing high-conf groups into low-conf groups)
                // 2. File-level confidence at the boundary (handles cases where groups have equal max but different boundary conf)
                //
                // Example where file-level matters:
                // - ME PT group: max=5 (from other files)
                // - Mountain View group: max=5 (from other files)
                // - But at page 73 boundary: ME PT has conf 5, Mountain View has conf 4
                // - Group max is equal (5=5), but boundary shows ME PT won (5>4)
                // - So we SHOULD absorb Mountain View's window into ME PT
                //
                // Priority order:
                // 1. If winning group max > losing group max: Always absorb (higher confidence group wins)
                // 2. If winning group max == losing group max: Check file-level boundary confidence
                // 3. If winning group max < losing group max: Never absorb (would be wrong direction)

                $shouldAbsorb = false;

                if ($winningGroupConfidence > $losingGroupConfidence) {
                    // Case 1: Winning group has higher max confidence - always absorb
                    $shouldAbsorb = true;
                    static::logDebug("CONFLICT BOUNDARY DETECTED: File $fileId (page {$data['page_number']}) - '$winningGroup' (group max $winningGroupConfidence) > '$losingGroup' (group max $losingGroupConfidence)");
                } elseif ($winningGroupConfidence === $losingGroupConfidence && $winningFileConf > $losingFileConf) {
                    // Case 2: Groups have equal max, but winning file has higher confidence at boundary
                    $shouldAbsorb = true;
                    static::logDebug("CONFLICT BOUNDARY DETECTED: File $fileId (page {$data['page_number']}) - Groups have equal max ($winningGroupConfidence), but '$winningGroup' file conf ($winningFileConf) > '$losingGroup' file conf ($losingFileConf) at boundary");
                }

                if (!$shouldAbsorb) {
                    continue;
                }

                // Find all adjacent files from the same window (both forward and backward chain)
                $adjacentFiles = $this->findAdjacentFilesFromSameWindow($fileId, $losingGroup, $explanation['window_id'], $fileToGroup);

                foreach ($adjacentFiles as $adjacentFileId) {
                    // Only absorb if not already marked for absorption to a different group (avoid conflicts)
                    if (!isset($fileAbsorptions[$adjacentFileId])) {
                        $fileAbsorptions[$adjacentFileId] = $winningGroup;
                        static::logDebug("  → Will absorb file $adjacentFileId from '$losingGroup' into '$winningGroup' (from boundary chain)");
                    }
                }
            }
        }

        return $fileAbsorptions;
    }

    /**
     * Get all files currently assigned to a specific group.
     *
     * @param  string  $groupName  Group name
     * @param  array  $fileToGroup  Map of file_id => group data
     * @return array Map of file_id => file data for files in this group
     */
    protected function getGroupFiles(string $groupName, array $fileToGroup): array
    {
        $groupFiles = [];

        foreach ($fileToGroup as $fileId => $data) {
            if ($data['group_name'] === $groupName) {
                $groupFiles[$fileId] = $data;
            }
        }

        return $groupFiles;
    }

    /**
     * Find files adjacent to a given file that were grouped together
     * in the SAME WINDOW and should be absorbed due to the boundary conflict.
     *
     * This builds a continuous absorption chain starting from the boundary file,
     * stopping when encountering files with higher confidence or gaps in the sequence.
     *
     * @param  int  $fileId  The boundary file ID
     * @param  string  $groupName  Group name to check for adjacent files
     * @param  int  $windowId  The window ID where the conflict occurred
     * @param  array  $fileToGroup  Map of file_id => group data
     * @return array Array of file IDs that are adjacent and from the same window
     */
    protected function findAdjacentFilesFromSameWindow(int $fileId, string $groupName, int $windowId, array $fileToGroup): array
    {
        $filesToAbsorb      = [];
        $boundaryPageNumber = $fileToGroup[$fileId]['page_number'];
        $boundaryFileData   = $fileToGroup[$fileId];

        // Get the confidence the boundary file had in the LOSING group
        $boundaryLosingConf = null;
        foreach ($boundaryFileData['all_explanations'] ?? [] as $exp) {
            if ($exp['group_name'] === $groupName && $exp['window_id'] === $windowId) {
                $boundaryLosingConf = $exp['confidence'];
                break;
            }
        }

        // Build a continuous chain forward and backward from the boundary
        // Stop when we hit a gap or a file with higher confidence
        $chain = $this->buildAbsorptionChain($fileId, $groupName, $windowId, $boundaryLosingConf, $fileToGroup);

        foreach ($chain as $chainFileId) {
            $data = $fileToGroup[$chainFileId];
            static::logDebug("    Found file in absorption chain: file $chainFileId (page {$data['page_number']}) from '$groupName' window $windowId");
            $filesToAbsorb[] = $chainFileId;
        }

        return $filesToAbsorb;
    }

    /**
     * Build a continuous absorption chain from a boundary file.
     * Only includes files that are DIRECTLY adjacent (no gaps) and have confidence <= boundary.
     *
     * CRITICAL: Detects gaps based on the ORIGINAL window composition, not current group membership.
     * This prevents absorbing files separated by pages that were already reassigned to the winning group.
     *
     * @param  int  $boundaryFileId  The boundary file
     * @param  string  $groupName  The losing group name
     * @param  int  $windowId  The window where conflict occurred
     * @param  int|null  $maxConfidence  Maximum confidence to absorb (files with higher conf stop the chain)
     * @param  array  $fileToGroup  File to group mapping
     * @return array Array of file IDs in the absorption chain
     */
    protected function buildAbsorptionChain(int $boundaryFileId, string $groupName, int $windowId, ?int $maxConfidence, array $fileToGroup): array
    {
        $chain              = [];
        $boundaryPageNumber = $fileToGroup[$boundaryFileId]['page_number'];

        // Find ALL files from the original window (regardless of current group assignment)
        // This allows us to detect gaps where files were reassigned to other groups
        $allWindowFiles = [];
        foreach ($fileToGroup as $fileId => $data) {
            foreach ($data['all_explanations'] ?? [] as $exp) {
                if ($exp['group_name'] === $groupName && $exp['window_id'] === $windowId) {
                    $allWindowFiles[$fileId] = $data['page_number'];
                    break;
                }
            }
        }

        // Find files CURRENTLY in the losing group from this window (candidates for absorption)
        $absorbableCandidates = [];
        foreach ($fileToGroup as $otherFileId => $data) {
            if ($otherFileId === $boundaryFileId) {
                continue; // Skip boundary itself
            }

            if ($data['group_name'] !== $groupName) {
                continue; // Only files currently in losing group
            }

            // Check if file was in this window with this group
            foreach ($data['all_explanations'] ?? [] as $exp) {
                if ($exp['group_name'] === $groupName && $exp['window_id'] === $windowId) {
                    // Check if file only appeared in one group (no conflicts of its own)
                    $allExplanations = $data['all_explanations'] ?? [];
                    $uniqueGroups    = array_unique(array_column($allExplanations, 'group_name'));

                    if (count($uniqueGroups) === 1) {
                        $absorbableCandidates[$otherFileId] = [
                            'page'       => $data['page_number'],
                            'confidence' => $exp['confidence'],
                        ];
                    }
                    break;
                }
            }
        }

        // Sort by page number to find continuous sequences
        uasort($absorbableCandidates, fn($a, $b) => $a['page'] <=> $b['page']);

        // Build chain forward (pages > boundary)
        // Start from the page immediately after the boundary
        $currentPage = $boundaryPageNumber + 1;

        // Log what we're working with
        static::logDebug("Building absorption chain from boundary page $boundaryPageNumber for group '$groupName' window $windowId");
        static::logDebug('All window file pages: ' . implode(', ', $allWindowFiles));
        static::logDebug('Absorbable candidate pages: ' . implode(', ', array_column($absorbableCandidates, 'page')));

        foreach ($absorbableCandidates as $fileId => $fileData) {
            // Skip files before current position in chain
            if ($fileData['page'] < $currentPage) {
                continue;
            }

            // CRITICAL: Stop if there's a gap in the ORIGINAL window composition
            // Check if the expected page exists in the original window
            // If it exists but is not absorbable (already reassigned), that's a gap
            if ($fileData['page'] > $currentPage) {
                // Check if pages between current and found page were in the original window
                $gapFound = false;
                for ($checkPage = $currentPage; $checkPage < $fileData['page']; $checkPage++) {
                    // Check if this page was in the original window
                    if (in_array($checkPage, $allWindowFiles)) {
                        // Page was in original window but is not in absorbable candidates
                        // This means it was already reassigned → there's a gap
                        static::logDebug("    STOPPING forward chain - gap detected at page $checkPage (was in original window but already reassigned)");
                        $gapFound = true;
                        break;
                    }
                }
                if ($gapFound) {
                    break;
                }
                // If no pages in between were in the original window, it's not a gap (just sparse numbering)
                // Continue with this file
            }

            // Stop if confidence is higher than boundary
            if ($maxConfidence !== null && $fileData['confidence'] > $maxConfidence) {
                static::logDebug("    STOPPING forward chain at page {$fileData['page']} - higher confidence ({$fileData['confidence']} > $maxConfidence)");
                break;
            }

            $chain[]     = $fileId;
            $currentPage = $fileData['page'] + 1; // Next expected page
        }

        // Build chain backward (pages < boundary)
        $currentPage   = $boundaryPageNumber - 1;
        $backwardChain = [];
        foreach (array_reverse($absorbableCandidates, true) as $fileId => $fileData) {
            // Skip files after current position in chain
            if ($fileData['page'] > $currentPage) {
                continue;
            }

            // CRITICAL: Stop if there's a gap in the ORIGINAL window composition
            if ($fileData['page'] < $currentPage) {
                // Check if pages between found and current were in the original window
                $gapFound = false;
                for ($checkPage = $fileData['page'] + 1; $checkPage <= $currentPage; $checkPage++) {
                    if (in_array($checkPage, $allWindowFiles)) {
                        static::logDebug("    STOPPING backward chain - gap detected at page $checkPage (was in original window but already reassigned)");
                        $gapFound = true;
                        break;
                    }
                }
                if ($gapFound) {
                    break;
                }
            }

            // Stop if confidence is higher than boundary
            if ($maxConfidence !== null && $fileData['confidence'] > $maxConfidence) {
                static::logDebug("    STOPPING backward chain at page {$fileData['page']} - higher confidence ({$fileData['confidence']} > $maxConfidence)");
                break;
            }

            $backwardChain[] = $fileId;
            $currentPage     = $fileData['page'] - 1; // Next expected page
        }

        // Combine backward and forward chains
        return array_merge(array_reverse($backwardChain), $chain);
    }

    /**
     * Apply file-level absorptions by reassigning specific files to winning groups.
     * Absorbed files inherit the winning group's confidence level to enable cascade absorption.
     *
     * @param  array  $fileAbsorptions  Map of file_id => winning_group
     * @param  array  $fileToGroup  Map of file_id => group data (will be modified)
     * @return int Number of files absorbed
     */
    public function applyAbsorptions(array $fileAbsorptions, array &$fileToGroup): int
    {
        $totalFilesAbsorbed = 0;

        foreach ($fileAbsorptions as $fileId => $winningGroup) {
            if (!isset($fileToGroup[$fileId])) {
                static::logDebug("  Skipping file $fileId - not found in fileToGroup mapping");

                continue;
            }

            $data              = $fileToGroup[$fileId];
            $oldGroup          = $data['group_name'];
            $oldConfidence     = $data['confidence'];
            $inheritConfidence = $this->calculateGroupMaxConfidence($winningGroup, $fileToGroup);

            static::logDebug("Absorbing file $fileId (page {$data['page_number']}) from '$oldGroup' (conf $oldConfidence) into '$winningGroup' (conf $inheritConfidence)");

            $fileToGroup[$fileId]['group_name'] = $winningGroup;
            $fileToGroup[$fileId]['confidence'] = $inheritConfidence;
            $totalFilesAbsorbed++;
        }

        static::logDebug("Total files absorbed: $totalFilesAbsorbed");

        return $totalFilesAbsorbed;
    }

    /**
     * Calculate the maximum confidence level in a group.
     *
     * @param  string  $groupName  Group name
     * @param  array  $fileToGroup  Map of file_id => group data
     * @return int Maximum confidence in the group
     */
    protected function calculateGroupMaxConfidence(string $groupName, array $fileToGroup): int
    {
        $maxConfidence = 0;

        foreach ($fileToGroup as $data) {
            if ($data['group_name'] === $groupName) {
                $maxConfidence = max($maxConfidence, $data['confidence']);
            }
        }

        return $maxConfidence ?: 3; // Default to 3 if group has no files
    }
}
