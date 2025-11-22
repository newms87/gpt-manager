<?php

namespace App\Services\Task;

use App\Models\Task\Artifact;
use App\Traits\HasDebugLogging;
use Illuminate\Support\Collection;

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
     * @return array Array of final groups with file IDs in correct order
     */
    public function mergeWindowResults(Collection $windowArtifacts): array
    {
        static::logDebug('Starting merge of ' . $windowArtifacts->count() . ' window artifacts');

        // Extract all window results from artifacts
        $windows = $this->extractWindowsFromArtifacts($windowArtifacts);

        if (empty($windows)) {
            static::logDebug('No windows found in artifacts');

            return [];
        }

        static::logDebug('Extracted ' . count($windows) . ' windows for merging');

        // Build file-to-group mapping from windows (later windows override earlier)
        $fileToGroup = $this->buildFileToGroupMapping($windows);

        static::logDebug('Built file-to-group mapping with ' . count($fileToGroup) . ' files');

        // Build final groups from the mapping
        $finalGroups = $this->buildFinalGroups($fileToGroup);

        static::logDebug('Created ' . count($finalGroups) . ' final groups');

        return $finalGroups;
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

            // Build position-to-file_id mapping from window metadata
            $positionToFileMap = [];
            if ($windowFiles && is_array($windowFiles)) {
                foreach ($windowFiles as $file) {
                    if (isset($file['position']) && isset($file['file_id'])) {
                        $positionToFileMap[$file['position']] = $file['file_id'];
                    }
                }
            }

            $windows[] = [
                'artifact_id'          => $artifact->id,
                'window_start'         => $windowStart,
                'window_end'           => $windowEnd,
                'groups'               => $jsonContent['groups'],
                'position_to_file_map' => $positionToFileMap,
            ];

            static::logDebug("Extracted window from artifact {$artifact->id}: positions {$windowStart}-{$windowEnd} with " . count($jsonContent['groups']) . ' groups');
        }

        // Sort windows by start position to ensure priority ordering
        usort($windows, fn($a, $b) => $a['window_start'] <=> $b['window_start']);

        return $windows;
    }

    /**
     * Build file-to-group mapping from windows using simple overwrite logic.
     * Groups identified by NAME. Later windows override earlier windows.
     *
     * @return array Map of file_id => ['group_name' => string, 'description' => string, 'position' => int]
     */
    protected function buildFileToGroupMapping(array $windows): array
    {
        $fileToGroup = [];

        foreach ($windows as $window) {
            static::logDebug("Processing window {$window['artifact_id']}: positions {$window['window_start']}-{$window['window_end']}");

            // Build position-to-file_id mapping from window metadata
            $positionToFileId = [];
            if (isset($window['position_to_file_map'])) {
                $positionToFileId = $window['position_to_file_map'];
            }

            foreach ($window['groups'] as $group) {
                $groupName   = $group['name'] ?? null;
                $description = $group['description'] ?? '';
                $files       = $group['files'] ?? [];

                if (!$groupName) {
                    static::logDebug('Group missing name, skipping');

                    continue;
                }

                static::logDebug("  Group '$groupName': " . count($files) . ' files');

                foreach ($files as $position) {
                    // Files are now always just position numbers (integers)
                    $fileId = $positionToFileId[$position] ?? null;

                    if (!$fileId || $position === null) {
                        static::logDebug('    File missing file_id or position, skipping');

                        continue;
                    }

                    // Check if this file was already assigned by a previous window
                    if (isset($fileToGroup[$fileId])) {
                        static::logDebug("    File $fileId (pos $position): overriding previous assignment '{$fileToGroup[$fileId]['group_name']}' with '$groupName'");
                    } else {
                        static::logDebug("    File $fileId (pos $position): assigned to group '$groupName'");
                    }

                    // Later windows override earlier windows (simple overwrite)
                    $fileToGroup[$fileId] = [
                        'group_name'  => $groupName,
                        'description' => $description,
                        'position'    => $position,
                    ];
                }
            }
        }

        return $fileToGroup;
    }


    /**
     * Build final groups from file-to-group mapping.
     * Groups files by name, sorts by position, uses earliest description.
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
            $position    = $data['position'];

            if (!isset($groupsMap[$groupName])) {
                $groupsMap[$groupName] = [];
                // Store the first description we see for this group name
                $groupDescriptions[$groupName] = $description;
            }

            $groupsMap[$groupName][] = [
                'file_id'  => $fileId,
                'position' => $position,
            ];
        }

        // Sort files within each group by position
        foreach ($groupsMap as $groupName => $files) {
            usort($files, fn($a, $b) => $a['position'] <=> $b['position']);
            $groupsMap[$groupName] = $files;
        }

        // Convert to final output format
        $finalGroups = [];

        foreach ($groupsMap as $groupName => $files) {
            $fileIds = array_map(fn($file) => $file['file_id'], $files);

            $finalGroups[] = [
                'name'        => $groupName,
                'description' => $groupDescriptions[$groupName],
                'files'       => $fileIds,
            ];

            static::logDebug("Final group '$groupName': " . count($fileIds) . ' files (positions ' .
                $files[0]['position'] . '-' . $files[count($files) - 1]['position'] . ')');
        }

        return $finalGroups;
    }

    /**
     * Get file IDs from artifacts in position order.
     * Used to create the initial list of files for window creation.
     *
     * @return array Array of ['file_id' => artifact_id, 'position' => int]
     */
    public function getFileListFromArtifacts(Collection $artifacts): array
    {
        $files = [];

        foreach ($artifacts as $artifact) {
            $files[] = [
                'file_id'  => $artifact->id,
                'position' => $artifact->position ?? 0,
            ];
        }

        // Sort by position
        usort($files, fn($a, $b) => $a['position'] <=> $b['position']);

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
     * @param  array  $files  Array of ['file_id' => id, 'position' => int]
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
            $windowFiles = [];
            $positions   = [];

            // Collect up to $windowSize files for this window
            for ($j = 0; $j < $windowSize && ($startIndex + $j) < $fileCount; $j++) {
                $file          = $files[$startIndex + $j];
                $windowFiles[] = $file;
                $positions[]   = $file['position'];
            }

            // Only create window if we have at least 2 files
            if (count($windowFiles) < 2) {
                static::logDebug("Window $windowIndex: skipping (only " . count($windowFiles) . ' file(s))');
                break;
            }

            $windows[] = [
                'window_index' => $windowIndex,
                'window_start' => min($positions),
                'window_end'   => max($positions),
                'files'        => $windowFiles,
            ];

            static::logDebug("Window $windowIndex: positions " . min($positions) . '-' . max($positions) . ' (' . count($windowFiles) . ' files)');
            $windowIndex++;

            // Move to next window starting at the LAST file of current window (overlap by 1)
            $startIndex += $windowSize - 1;
        }

        static::logDebug('Created ' . count($windows) . ' overlapping windows');

        return $windows;
    }
}
