<?php

namespace App\Services\Task;

use App\Models\Task\Artifact;
use App\Services\Task\FileOrganization\GroupAbsorptionService;
use App\Services\Task\FileOrganization\GroupConfidenceAnalyzer;
use App\Services\Task\FileOrganization\NullGroupResolver;
use App\Traits\HasDebugLogging;
use Illuminate\Support\Collection;
use Newms87\Danx\Exceptions\ValidationError;

/**
 * Merges file organization windows from parallel comparison processes into final groups.
 *
 * Handles overlapping windows with simple merge logic:
 * - Windows overlap by 1 file (last file of window N = first file of window N+1)
 * - Groups identified by NAME (not index)
 * - Later windows override earlier windows (simple overwrite)
 * - Final groups maintain file order from original input
 */
class FileOrganizationMergeService
{
    use HasDebugLogging;

    /**
     * Merge window results from parallel comparison processes into final groups.
     *
     * @param  Collection  $windowArtifacts  Collection of artifacts containing window comparison results
     * @return array Array with 'groups' and 'file_to_group_mapping' keys
     */
    public function mergeWindowResults(Collection $windowArtifacts): array
    {
        static::logDebug('Starting merge of ' . $windowArtifacts->count() . ' window artifacts');

        // Extract all window results from artifacts
        $windows = $this->extractWindowsFromArtifacts($windowArtifacts);

        if (empty($windows)) {
            static::logDebug('No windows found in artifacts');

            return [
                'groups'                => [],
                'file_to_group_mapping' => [],
            ];
        }

        static::logDebug('Extracted ' . count($windows) . ' windows for merging');

        // Build file-to-group mapping from windows (highest confidence wins)
        $fileToGroup = $this->buildFileToGroupMapping($windows);

        static::logDebug('Built file-to-group mapping with ' . count($fileToGroup) . ' files');

        // Build final groups from the mapping
        $finalGroups = $this->buildFinalGroups($fileToGroup);

        static::logDebug('Created ' . count($finalGroups) . ' final groups');

        // Handle null groups (empty string names) - reassign to adjacent groups
        $nullGroupResolution = app(NullGroupResolver::class)->resolveNullGroups($fileToGroup);

        // Apply auto-assignments from null group resolution
        foreach ($nullGroupResolution['auto_assignments'] as $fileId => $groupName) {
            $fileToGroup[$fileId]['group_name'] = $groupName;
            static::logDebug("Auto-reassigned file $fileId (page {$fileToGroup[$fileId]['page_number']}) to group '$groupName'");
        }

        // Rebuild groups with null group auto-assignments applied
        $finalGroups = $this->buildFinalGroups($fileToGroup);

        // Iteratively absorb groups until no more absorptions occur (cascade/chain reaction)
        // This handles cases where absorbing one group creates new absorption opportunities
        $iteration = 0;
        $maxIterations = 10; // Safety limit to prevent infinite loops
        $totalAbsorptions = 0;

        do {
            $iteration++;
            $absorptions = app(GroupAbsorptionService::class)->identifyAbsorptions($fileToGroup, $finalGroups);

            if (!empty($absorptions)) {
                $filesAbsorbed = app(GroupAbsorptionService::class)->applyAbsorptions($absorptions, $fileToGroup);
                $totalAbsorptions += count($absorptions);
                static::logDebug("Iteration $iteration: Absorbed $filesAbsorbed files via " . count($absorptions) . " group mergers");

                // Rebuild groups after absorption for next iteration
                $finalGroups = $this->buildFinalGroups($fileToGroup);
            } else {
                static::logDebug("Iteration $iteration: No more absorptions found");
                break;
            }

            if ($iteration >= $maxIterations) {
                static::logDebug("WARNING: Reached maximum iterations ($maxIterations) for absorption - stopping to prevent infinite loop");
                break;
            }
        } while (!empty($absorptions));

        if ($totalAbsorptions > 0) {
            static::logDebug("Total cascade absorptions: $totalAbsorptions groups absorbed across $iteration iterations");
        }

        // Return both groups, mapping, and null group resolution info
        return [
            'groups'                    => $finalGroups,
            'file_to_group_mapping'     => $fileToGroup,
            'null_groups_needing_llm'   => $nullGroupResolution['needs_llm_resolution'],
        ];
    }

    /**
     * Extract window data structures from artifacts.
     *
     * @return array Array of window data structures
     */
    protected function extractWindowsFromArtifacts(Collection $artifacts): array
    {
        $windows = [];

        foreach ($artifacts as $artifact) {
            $jsonContent = $artifact->json_content;

            if (!$jsonContent || !isset($jsonContent['groups']) || !is_array($jsonContent['groups'])) {
                static::logDebug("Artifact {$artifact->id} has no valid groups data");

                continue;
            }

            // Extract window metadata
            $windowStart = $artifact->meta['window_start'] ?? null;
            $windowEnd   = $artifact->meta['window_end']   ?? null;
            $windowFiles = $artifact->meta['window_files'] ?? null;

            if ($windowStart === null || $windowEnd === null) {
                static::logDebug("Artifact {$artifact->id} missing window metadata");

                continue;
            }

            // Build page_number-to-file_id mapping from window metadata
            $pageNumberToFileMap = [];
            if ($windowFiles && is_array($windowFiles)) {
                foreach ($windowFiles as $file) {
                    if (isset($file['page_number']) && isset($file['file_id'])) {
                        $pageNumberToFileMap[$file['page_number']] = $file['file_id'];
                    }
                }
            }

            $windows[] = [
                'artifact_id'             => $artifact->id,
                'window_start'            => $windowStart,
                'window_end'              => $windowEnd,
                'groups'                  => $jsonContent['groups'],
                'page_number_to_file_map' => $pageNumberToFileMap,
            ];

            static::logDebug("Extracted window from artifact {$artifact->id}: positions {$windowStart}-{$windowEnd} with " . count($jsonContent['groups']) . ' groups');
        }

        // Sort windows by start position to ensure priority ordering
        usort($windows, fn($a, $b) => $a['window_start'] <=> $b['window_start']);

        return $windows;
    }

    /**
     * Build file-to-group mapping from windows using confidence-based logic.
     * Groups identified by NAME. Highest confidence assignment wins.
     * Supports both old format (integer page_number) and new format (object with confidence).
     *
     * @return array Map of file_id => ['group_name' => string, 'description' => string, 'page_number' => int, 'confidence' => int, 'all_explanations' => array]
     */
    protected function buildFileToGroupMapping(array $windows): array
    {
        $fileToGroup = [];

        foreach ($windows as $window) {
            static::logDebug("Processing window {$window['artifact_id']}: page numbers {$window['window_start']}-{$window['window_end']}");

            // Build page_number-to-file_id mapping from window metadata
            $pageNumberToFileId = [];
            if (isset($window['page_number_to_file_map'])) {
                $pageNumberToFileId = $window['page_number_to_file_map'];
            }

            // Get list of valid page numbers for this window (for validation)
            $validPageNumbers = array_keys($pageNumberToFileId);

            foreach ($window['groups'] as $group) {
                $groupName   = $group['name']        ?? null;
                $description = $group['description'] ?? '';
                $files       = $group['files']       ?? [];

                // Allow empty string for "no identifier" cases, but skip null
                if ($groupName === null) {
                    static::logDebug('Group has null name, skipping');

                    continue;
                }

                // Empty string means "no identifier found" - we'll handle these later
                if ($groupName === '') {
                    static::logDebug('Group has empty name (no identifier) with ' . count($files) . ' files - will process for adjacent group assignment');
                }

                static::logDebug("  Group '$groupName': " . count($files) . ' files');

                foreach ($files as $fileData) {
                    // Handle both old format (integer) and new format (object)
                    if (is_int($fileData)) {
                        // Old format: just the page number
                        $pageNumber  = $fileData;
                        $confidence  = 3; // Default confidence for legacy data
                        $explanation = 'Legacy assignment (no confidence score provided)';
                    } else {
                        // New format: object with page_number, confidence, explanation
                        $pageNumber  = $fileData['page_number']  ?? null;
                        $confidence  = $fileData['confidence']   ?? 3;
                        $explanation = $fileData['explanation']  ?? '';
                    }

                    // Validate that agent only returned page numbers that were in the prompt
                    if (!in_array($pageNumber, $validPageNumbers)) {
                        throw new ValidationError(
                            "Agent returned invalid page_number $pageNumber in group '$groupName'. " .
                            'Valid page numbers for this window are: ' . implode(', ', $validPageNumbers),
                            400
                        );
                    }

                    $fileId = $pageNumberToFileId[$pageNumber] ?? null;

                    if (!$fileId || $pageNumber === null) {
                        static::logDebug("    File missing file_id for page_number $pageNumber, skipping");

                        continue;
                    }

                    // Initialize all_explanations if this is the first time we see this file
                    if (!isset($fileToGroup[$fileId])) {
                        $fileToGroup[$fileId] = [
                            'group_name'        => $groupName,
                            'description'       => $description,
                            'page_number'       => $pageNumber,
                            'confidence'        => $confidence,
                            'all_explanations'  => [],
                        ];
                    }

                    // Track ALL explanations for this file across all windows
                    $fileToGroup[$fileId]['all_explanations'][] = [
                        'group_name'  => $groupName,
                        'confidence'  => $confidence,
                        'explanation' => $explanation,
                        'window_id'   => $window['artifact_id'],
                    ];

                    // Check if this assignment has higher confidence than the current assignment
                    if ($confidence > $fileToGroup[$fileId]['confidence']) {
                        static::logDebug("    File $fileId (page $pageNumber): upgrading from '{$fileToGroup[$fileId]['group_name']}' (confidence {$fileToGroup[$fileId]['confidence']}) to '$groupName' (confidence $confidence)");

                        $fileToGroup[$fileId]['group_name']  = $groupName;
                        $fileToGroup[$fileId]['description'] = $description;
                        $fileToGroup[$fileId]['confidence']  = $confidence;
                    } elseif ($confidence === $fileToGroup[$fileId]['confidence'] && $fileToGroup[$fileId]['group_name'] !== $groupName) {
                        static::logDebug("    File $fileId (page $pageNumber): same confidence ($confidence) for different groups - keeping '{$fileToGroup[$fileId]['group_name']}' (first wins on tie)");
                    } else {
                        static::logDebug("    File $fileId (page $pageNumber): lower confidence ($confidence) than current assignment (confidence {$fileToGroup[$fileId]['confidence']}) - keeping '{$fileToGroup[$fileId]['group_name']}'");
                    }
                }
            }
        }

        return $fileToGroup;
    }

    /**
     * Identify files with low confidence scores (< 3) that have MULTIPLE different group assignments.
     * Only returns files that need resolution due to ambiguity (appeared in multiple groups across windows).
     * Files with only one low-confidence assignment are kept as-is (that's the only answer we have).
     *
     * @param  array  $fileToGroup  Map from buildFileToGroupMapping
     * @return array Array of low-confidence files that have multiple conflicting assignments
     */
    public function identifyLowConfidenceFiles(array $fileToGroup): array
    {
        $lowConfidenceFiles = [];

        foreach ($fileToGroup as $fileId => $data) {
            if ($data['confidence'] < 3) {
                // Count how many DIFFERENT groups this file appeared in
                $allExplanations = $data['all_explanations'] ?? [];
                $uniqueGroups = array_unique(array_column($allExplanations, 'group_name'));

                // Only include if file appeared in MULTIPLE different groups
                if (count($uniqueGroups) > 1) {
                    $lowConfidenceFiles[] = [
                        'file_id'           => $fileId,
                        'page_number'       => $data['page_number'],
                        'best_assignment'   => [
                            'group_name'  => $data['group_name'],
                            'description' => $data['description'],
                            'confidence'  => $data['confidence'],
                        ],
                        'all_explanations'  => $allExplanations,
                    ];

                    static::logDebug("Low confidence file with MULTIPLE assignments: page {$data['page_number']} (confidence {$data['confidence']}) - appeared in " . count($uniqueGroups) . " different groups");
                } else {
                    static::logDebug("Low confidence file with SINGLE assignment: page {$data['page_number']} (confidence {$data['confidence']}) assigned to '{$data['group_name']}' - keeping as-is (only answer available)");
                }
            }
        }

        static::logDebug('Found ' . count($lowConfidenceFiles) . ' low-confidence files requiring resolution (have multiple conflicting assignments)');

        return $lowConfidenceFiles;
    }

    /**
     * Build final groups from file-to-group mapping.
     * Groups files by name, sorts by page_number, uses earliest description.
     *
     * @return array Array of groups: [['name' => string, 'description' => string, 'files' => [file_id, ...]], ...]
     */
    protected function buildFinalGroups(array $fileToGroup): array
    {
        if (empty($fileToGroup)) {
            return [];
        }

        // Group files by group name and track descriptions
        $groupsMap         = [];
        $groupDescriptions = [];

        foreach ($fileToGroup as $fileId => $data) {
            $groupName   = $data['group_name'];
            $description = $data['description'];
            $pageNumber  = $data['page_number'];

            if (!isset($groupsMap[$groupName])) {
                $groupsMap[$groupName] = [];
                // Store the first description we see for this group name
                $groupDescriptions[$groupName] = $description;
            }

            $groupsMap[$groupName][] = [
                'file_id'     => $fileId,
                'page_number' => $pageNumber,
            ];
        }

        // Sort files within each group by page_number
        foreach ($groupsMap as $groupName => $files) {
            usort($files, fn($a, $b) => $a['page_number'] <=> $b['page_number']);
            $groupsMap[$groupName] = $files;
        }

        // Convert to final output format with confidence metadata
        $finalGroups = [];

        foreach ($groupsMap as $groupName => $files) {
            $fileIds = array_map(fn($file) => $file['file_id'], $files);

            // Calculate confidence statistics for this group
            $confidences   = array_map(fn($file) => $fileToGroup[$file['file_id']]['confidence'], $files);
            $avgConfidence = count($confidences) > 0 ? round(array_sum($confidences) / count($confidences), 2) : 0;
            $minConfidence = count($confidences) > 0 ? min($confidences) : 0;
            $maxConfidence = count($confidences) > 0 ? max($confidences) : 0;

            $finalGroups[] = [
                'name'               => $groupName,
                'description'        => $groupDescriptions[$groupName],
                'files'              => $fileIds,
                'confidence_summary' => [
                    'avg' => $avgConfidence,
                    'min' => $minConfidence,
                    'max' => $maxConfidence,
                ],
            ];

            static::logDebug("Final group '$groupName': " . count($fileIds) . ' files (page numbers ' .
                $files[0]['page_number'] . '-' . $files[count($files) - 1]['page_number'] .
                ", confidence avg=$avgConfidence min=$minConfidence max=$maxConfidence)");
        }

        return $finalGroups;
    }

    /**
     * Get file IDs from artifacts with page_number from StoredFile.
     * Used to create the initial list of files for window creation.
     *
     * @return array Array of ['file_id' => artifact_id, 'page_number' => int]
     */
    public function getFileListFromArtifacts(Collection $artifacts): array
    {
        $files = [];

        foreach ($artifacts as $artifact) {
            // Get page_number from the StoredFile model (NOT from artifact meta or position)
            $storedFile = $artifact->storedFiles ? $artifact->storedFiles->first() : null;
            $pageNumber = $storedFile?->page_number ?? $artifact->position ?? 0;

            $files[] = [
                'file_id'     => $artifact->id,
                'page_number' => $pageNumber,
            ];
        }

        // Sort by page_number
        usort($files, fn($a, $b) => $a['page_number'] <=> $b['page_number']);

        static::logDebug('Extracted ' . count($files) . ' files from artifacts');

        return $files;
    }

    /**
     * Create overlapping windows from a list of files.
     * Each window overlaps by one file - the last file of window N is the first file of window N+1.
     *
     * Examples:
     * - 10 files, size 5 → 3 windows: [1-5], [5-9], [9-10]
     * - 6 files, size 5 → 2 windows: [1-5], [5-6]
     * - 4 files, size 5 → 1 window: [1-4]
     *
     * @param  array  $files  Array of ['file_id' => id, 'page_number' => int]
     * @param  int  $windowSize  Maximum number of files per window
     * @return array Array of windows with metadata
     */
    public function createOverlappingWindows(array $files, int $windowSize): array
    {
        if (empty($files)) {
            static::logDebug('No files to create windows from');

            return [];
        }

        if ($windowSize < 2) {
            static::logDebug('Window size must be at least 2');

            return [];
        }

        $windows   = [];
        $fileCount = count($files);

        static::logDebug("Creating overlapping windows from $fileCount files with max window size $windowSize");

        // Create overlapping windows where last file of window N = first file of window N+1
        $windowIndex = 0;
        $startIndex  = 0;

        while ($startIndex < $fileCount) {
            $windowFiles  = [];
            $pageNumbers  = [];

            // Collect up to $windowSize files for this window
            for ($j = 0; $j < $windowSize && ($startIndex + $j) < $fileCount; $j++) {
                $file          = $files[$startIndex + $j];
                $windowFiles[] = $file;
                $pageNumbers[] = $file['page_number'];
            }

            // Only create window if we have at least 2 files
            if (count($windowFiles) < 2) {
                static::logDebug("Window $windowIndex: skipping (only " . count($windowFiles) . ' file(s))');
                break;
            }

            $windows[] = [
                'window_index' => $windowIndex,
                'window_start' => min($pageNumbers),
                'window_end'   => max($pageNumbers),
                'files'        => $windowFiles,
            ];

            static::logDebug("Window $windowIndex: page numbers " . min($pageNumbers) . '-' . max($pageNumbers) . ' (' . count($windowFiles) . ' files)');
            $windowIndex++;

            // Move to next window starting at the LAST file of current window (overlap by 1)
            $startIndex += $windowSize - 1;
        }

        static::logDebug('Created ' . count($windows) . ' overlapping windows');

        return $windows;
    }
}
