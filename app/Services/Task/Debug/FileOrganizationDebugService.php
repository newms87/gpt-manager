<?php

namespace App\Services\Task\Debug;

use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Services\Task\FileOrganization\ResolutionOrchestrator;
use App\Services\Task\Runners\FileOrganizationTaskRunner;
use App\Services\Task\TaskProcessDispatcherService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class FileOrganizationDebugService
{
    /**
     * Show full overview of a FileOrganization task run.
     */
    public function showOverview(
        TaskRun $taskRun,
        Command $command,
        bool $showRaw = false,
        bool $showArtifacts = false,
        bool $showMessages = false,
        bool $showDedup = false,
        bool $verbose = false
    ): void {
        $command->info("=== TaskRun {$taskRun->id} ===");
        $command->line("Status: {$taskRun->status}");
        $command->line("TaskDefinition: {$taskRun->taskDefinition->name}");
        $command->line("Runner: {$taskRun->taskDefinition->task_runner_name}");
        $command->newLine();

        // Show task definition prompt
        $command->info("=== User's Task Definition Prompt ===");
        $command->line($taskRun->taskDefinition->prompt ?: '(none)');
        $command->newLine();

        // Show input artifacts
        $command->info('=== Input Artifacts ===');
        $inputArtifacts = $taskRun->inputArtifacts()->orderBy('position')->get();
        $command->line("Total: {$inputArtifacts->count()} files");

        foreach ($inputArtifacts->take(5) as $artifact) {
            $storedFile = $artifact->storedFiles->first();
            $pageNumber = $storedFile?->page_number ?? $artifact->position;
            $command->line("  Page $pageNumber: {$artifact->name}");
        }
        if ($inputArtifacts->count() > 5) {
            $command->line('  ... and ' . ($inputArtifacts->count() - 5) . ' more');
        }
        $command->newLine();

        // Show window processes
        $windowProcesses = $this->getWindowProcesses($taskRun);
        $this->showWindowSummary($windowProcesses, $command, $showRaw, $showArtifacts, $showMessages);

        // Show merge process
        $this->showMergeProcessInfo($taskRun, $command);

        // Show resolution processes
        $this->showResolutionProcessInfo($taskRun, $command);

        // Show duplicate group resolution
        $this->showDuplicateGroupInfo($taskRun, $command, $showDedup);

        // Show group analysis
        $this->showGroupAnalysis($taskRun, $command);

        // Show final output groups
        $this->showFinalOutputGroups($taskRun, $command, $verbose);

        // Show agent thread messages if requested
        if ($showMessages) {
            $this->showFirstWindowMessages($windowProcesses, $command);
        }
    }

    /**
     * Show detailed info for a specific window.
     */
    public function showWindowDetail(TaskRun $taskRun, string $range, Command $command): int
    {
        // Parse window range (e.g., "1-10")
        if (!preg_match('/^(\d+)-(\d+)$/', $range, $matches)) {
            $command->error('Invalid window format. Expected format: 1-10');

            return 1;
        }

        $start = (int)$matches[1];
        $end   = (int)$matches[2];

        // Find window process with matching meta data
        $windowProcess = $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW)
            ->get()
            ->first(function ($process) use ($start, $end) {
                return ($process->meta['window_start'] ?? null) === $start
                    && ($process->meta['window_end'] ?? null)   === $end;
            });

        if (!$windowProcess) {
            $command->error("Window $range not found");

            return 1;
        }

        $command->info("=== Window $range Details ===");
        $command->line("TaskProcess ID: {$windowProcess->id}");
        $command->line("Status: {$windowProcess->status}");
        $command->newLine();

        // Show output artifact with full JSON content
        $artifact = $windowProcess->outputArtifacts->first();
        if ($artifact && $artifact->json_content) {
            $command->info('=== Files in Window ===');
            $files = $artifact->json_content['files'] ?? [];

            // Group files by group_name
            $groupedFiles = [];
            foreach ($files as $file) {
                $groupName = $file['group_name'] ?? '';
                if (!isset($groupedFiles[$groupName])) {
                    $groupedFiles[$groupName] = [];
                }
                $groupedFiles[$groupName][] = $file;
            }

            foreach ($groupedFiles as $groupName => $groupFiles) {
                $displayName = $groupName === '' ? '(null/blank)' : $groupName;
                $command->line("Group: $displayName");

                foreach ($groupFiles as $file) {
                    $page    = $file['page_number']           ?? '?';
                    $conf    = $file['group_name_confidence'] ?? '?';
                    $belongs = $file['belongs_to_previous']   ?? '?';
                    $command->line("  Page $page: confidence=$conf, belongs_to_previous=$belongs");
                }
                $command->newLine();
            }

            $command->info('=== Full JSON Content ===');
            $command->line(json_encode($artifact->json_content, JSON_PRETTY_PRINT));
            $command->newLine();
        }

        // Show agent thread conversation
        if ($windowProcess->agentThread) {
            $command->info('=== Agent Thread Conversation ===');
            $thread = $windowProcess->agentThread;

            foreach ($thread->messages()->orderBy('created_at')->get() as $message) {
                $command->line("[$message->role] - {$message->created_at}");
                $command->line($message->content);
                $command->newLine();
            }
        }

        return 0;
    }

    /**
     * Show all data about a specific page number across all windows.
     */
    public function showPageAnalysis(TaskRun $taskRun, int $page, Command $command): int
    {
        $command->info("=== Page $page Analysis ===");
        $command->newLine();

        $windowProcesses = $this->getWindowProcesses($taskRun);
        $assignments     = [];

        foreach ($windowProcesses as $window) {
            $start    = $window->meta['window_start'] ?? '?';
            $end      = $window->meta['window_end']   ?? '?';
            $artifact = $window->outputArtifacts->first();

            if ($artifact && $artifact->json_content) {
                $files = $artifact->json_content['files'] ?? [];

                // Find this page in the window
                foreach ($files as $file) {
                    if (($file['page_number'] ?? null) === $page) {
                        $groupName = $file['group_name']            ?? '';
                        $conf      = $file['group_name_confidence'] ?? '?';
                        $belongs   = $file['belongs_to_previous']   ?? '?';

                        $assignments[] = [
                            'window'     => "$start-$end",
                            'group_name' => $groupName,
                            'confidence' => $conf,
                            'belongs'    => $belongs,
                        ];

                        $command->line("Window $start-$end:");
                        $command->line('  Group: ' . ($groupName === '' ? '(null/blank)' : $groupName));
                        $command->line("  Confidence: $conf");
                        $command->line("  Belongs to Previous: $belongs");
                        $command->newLine();
                    }
                }
            }
        }

        if (empty($assignments)) {
            $command->warn("Page $page not found in any window");

            return 1;
        }

        // Check for conflicts (different group names)
        $uniqueGroups = array_unique(array_column($assignments, 'group_name'));
        if (count($uniqueGroups) > 1) {
            $command->warn('=== CONFLICT DETECTED ===');
            $command->line("Page $page has " . count($uniqueGroups) . ' different group assignments:');
            foreach ($uniqueGroups as $group) {
                $displayName     = $group === '' ? '(null/blank)' : $group;
                $windowsWithThis = array_filter($assignments, fn($a) => $a['group_name'] === $group);
                $windowList      = implode(', ', array_column($windowsWithThis, 'window'));
                $command->line("  - \"$displayName\" in windows: $windowList");
            }
        } else {
            $command->info('No conflicts - page has consistent group assignment across all windows');
        }

        return 0;
    }

    /**
     * Show all pages assigned to a specific group.
     */
    public function showGroupPages(TaskRun $taskRun, string $groupName, Command $command): int
    {
        $command->info("=== Pages Assigned to Group: \"$groupName\" ===");
        $command->newLine();

        $windowProcesses = $this->getWindowProcesses($taskRun);
        $allPages        = [];

        foreach ($windowProcesses as $window) {
            $start    = $window->meta['window_start'] ?? '?';
            $end      = $window->meta['window_end']   ?? '?';
            $artifact = $window->outputArtifacts->first();

            if ($artifact && $artifact->json_content) {
                $files         = $artifact->json_content['files'] ?? [];
                $matchingFiles = array_filter($files, fn($f) => ($f['group_name'] ?? '') === $groupName);

                if (!empty($matchingFiles)) {
                    $command->line("Window $start-$end:");
                    foreach ($matchingFiles as $file) {
                        $page = $file['page_number']           ?? '?';
                        $conf = $file['group_name_confidence'] ?? '?';

                        $command->line("  Page $page (confidence: $conf)");

                        if (!isset($allPages[$page])) {
                            $allPages[$page] = [];
                        }
                        $allPages[$page][] = [
                            'window'     => "$start-$end",
                            'confidence' => $conf,
                        ];
                    }
                    $command->newLine();
                }
            }
        }

        if (empty($allPages)) {
            $command->warn("No pages found assigned to group \"$groupName\"");

            return 1;
        }

        $command->info('=== Summary ===');
        $command->line('Total unique pages: ' . count($allPages));
        foreach ($allPages as $page => $windows) {
            $windowList = implode(', ', array_column($windows, 'window'));
            $avgConf    = array_sum(array_column($windows, 'confidence')) / count($windows);
            $command->line("  Page $page: seen in " . count($windows) . " window(s) [$windowList], avg confidence: " . round($avgConf, 1));
        }

        return 0;
    }

    /**
     * Show pages with conflicting group assignments across windows.
     */
    public function showMismatches(TaskRun $taskRun, Command $command): int
    {
        $command->info('=== Group Assignment Conflicts ===');
        $command->newLine();

        $windowProcesses = $this->getWindowProcesses($taskRun);

        // Collect all assignments by page number
        $pageAssignments = [];

        foreach ($windowProcesses as $window) {
            $start    = $window->meta['window_start'] ?? '?';
            $end      = $window->meta['window_end']   ?? '?';
            $artifact = $window->outputArtifacts->first();

            if ($artifact && $artifact->json_content) {
                $files = $artifact->json_content['files'] ?? [];

                foreach ($files as $file) {
                    $page      = $file['page_number']           ?? null;
                    $groupName = $file['group_name']            ?? '';
                    $conf      = $file['group_name_confidence'] ?? '?';

                    if ($page !== null) {
                        if (!isset($pageAssignments[$page])) {
                            $pageAssignments[$page] = [];
                        }

                        $pageAssignments[$page][] = [
                            'window'     => "$start-$end",
                            'group_name' => $groupName,
                            'confidence' => $conf,
                        ];
                    }
                }
            }
        }

        // Find conflicts (pages with different group names)
        $conflicts = [];
        foreach ($pageAssignments as $page => $assignments) {
            $uniqueGroups = array_unique(array_column($assignments, 'group_name'));
            if (count($uniqueGroups) > 1) {
                $conflicts[$page] = $assignments;
            }
        }

        if (empty($conflicts)) {
            $command->info('No group assignment conflicts found!');

            return 0;
        }

        $command->warn('Found ' . count($conflicts) . ' pages with conflicting assignments:');
        $command->newLine();

        foreach ($conflicts as $page => $assignments) {
            $command->line("Page $page:");

            // Group by group_name
            $byGroup = [];
            foreach ($assignments as $assignment) {
                $group = $assignment['group_name'];
                if (!isset($byGroup[$group])) {
                    $byGroup[$group] = [];
                }
                $byGroup[$group][] = $assignment;
            }

            foreach ($byGroup as $groupName => $groupAssignments) {
                $displayName = $groupName === '' ? '(null/blank)' : $groupName;
                $avgConf     = array_sum(array_column($groupAssignments, 'confidence')) / count($groupAssignments);
                $windows     = implode(', ', array_column($groupAssignments, 'window'));

                $command->line("  \"$displayName\" (avg confidence: " . round($avgConf, 1) . ") in windows: $windows");
            }

            $command->newLine();
        }

        return 0;
    }

    /**
     * Rerun the merge process.
     */
    public function rerunMerge(TaskRun $taskRun, Command $command): int
    {
        $mergeProcess = $this->getMergeProcess($taskRun);

        if (!$mergeProcess) {
            $command->error('No merge process found');

            return 1;
        }

        $command->info("Rerunning merge process ID: {$mergeProcess->id}");

        // Delete output artifacts
        foreach ($mergeProcess->outputArtifacts as $artifact) {
            $command->line("  Deleting artifact: {$artifact->id}");
            $artifact->delete();
        }
        $mergeProcess->outputArtifacts()->detach();

        // Reset status and meta
        $mergeProcess->status   = 'Pending';
        $mergeProcess->meta     = [];
        $mergeProcess->is_ready = true;
        $mergeProcess->save();

        $command->info('Merge process reset to Pending status');

        // Dispatch to rerun
        TaskProcessDispatcherService::dispatchForTaskRun($taskRun);

        $command->info('Merge process queued for rerun');

        return 0;
    }

    /**
     * Rerun the duplicate group resolution process.
     */
    public function rerunDedup(TaskRun $taskRun, Command $command): int
    {
        $resolutionProcesses = $this->getResolutionProcesses($taskRun);
        $dedupProcess        = $resolutionProcesses[FileOrganizationTaskRunner::OPERATION_DUPLICATE_GROUP_RESOLUTION] ?? null;

        if (!$dedupProcess) {
            $command->error('No duplicate group resolution process found');

            return 1;
        }

        $command->info("Rerunning duplicate group resolution process ID: {$dedupProcess->id}");

        // Get groups_for_deduplication from merge process
        $mergeProcess = $this->getMergeProcess($taskRun);

        if (!$mergeProcess) {
            $command->error('No merge process found - cannot get groups_for_deduplication');

            return 1;
        }

        $groupsForDeduplication = $mergeProcess->meta['groups_for_deduplication'] ?? [];

        if (empty($groupsForDeduplication)) {
            $command->error('No groups_for_deduplication found in merge process meta');

            return 1;
        }

        $command->info('Found ' . count($groupsForDeduplication) . ' groups for deduplication');

        // Delete output artifacts
        foreach ($dedupProcess->outputArtifacts as $artifact) {
            $command->line("  Deleting artifact: {$artifact->id}");
            $artifact->delete();
        }
        $dedupProcess->outputArtifacts()->detach();

        // Reset status and meta - preserve groups_for_deduplication
        $dedupProcess->status   = 'Pending';
        $dedupProcess->meta     = ['groups_for_deduplication' => $groupsForDeduplication];
        $dedupProcess->is_ready = true;
        $dedupProcess->save();

        $command->info('Duplicate group resolution process reset to Pending status');

        // Dispatch to rerun
        TaskProcessDispatcherService::dispatchForTaskRun($taskRun);

        $command->info('Duplicate group resolution process queued for rerun');

        return 0;
    }

    /**
     * Reset from window results and recreate merge process.
     */
    public function resetFromWindows(TaskRun $taskRun, Command $command): int
    {
        $command->info('Resetting task run from window results...');

        // Define all resolution operations to delete
        $resolutionOps = [
            FileOrganizationTaskRunner::OPERATION_MERGE,
            FileOrganizationTaskRunner::OPERATION_LOW_CONFIDENCE_RESOLUTION,
            FileOrganizationTaskRunner::OPERATION_NULL_GROUP_RESOLUTION,
            FileOrganizationTaskRunner::OPERATION_DUPLICATE_GROUP_RESOLUTION,
        ];

        // Delete all resolution processes and their outputs
        foreach ($resolutionOps as $op) {
            $processes = $taskRun->taskProcesses()->where('operation', $op)->get();
            foreach ($processes as $process) {
                $command->line("  Deleting process: {$process->id} ({$process->operation})");

                // Delete output artifacts
                foreach ($process->outputArtifacts as $artifact) {
                    $command->line("    Deleting artifact: {$artifact->id}");
                    $artifact->delete();
                }
                $process->outputArtifacts()->detach();

                // Delete the process itself
                $process->delete();
            }
        }

        // Delete task run output artifacts (final groups)
        $outputCount = $taskRun->outputArtifacts()->count();
        if ($outputCount > 0) {
            $command->line("  Deleting {$outputCount} task run output artifacts");
            foreach ($taskRun->outputArtifacts as $artifact) {
                $artifact->delete();
            }
            $taskRun->outputArtifacts()->detach();
        }

        // Update counters
        $taskRun->updateRelationCounter('taskProcesses');
        $taskRun->updateRelationCounter('outputArtifacts');

        // Reset task run status
        $taskRun->status = TaskRun::STATUS_RUNNING;
        $taskRun->save();

        $command->info('Reset complete. Creating merge process...');

        // Use ResolutionOrchestrator to create the merge process
        $orchestrator = app(ResolutionOrchestrator::class);
        $mergeCreated = $orchestrator->createMergeProcessIfReady($taskRun);

        if ($mergeCreated) {
            $command->info('Merge process created successfully');

            // Dispatch to run the merge
            TaskProcessDispatcherService::dispatchForTaskRun($taskRun);

            $command->info('Merge process dispatched for execution');
        } else {
            $command->warn('Failed to create merge process - windows may not be ready');

            return 1;
        }

        return 0;
    }

    /**
     * Get window comparison processes.
     */
    protected function getWindowProcesses(TaskRun $taskRun): Collection
    {
        return $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW)
            ->get();
    }

    /**
     * Get the merge process.
     */
    protected function getMergeProcess(TaskRun $taskRun): ?TaskProcess
    {
        return $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_MERGE)
            ->first();
    }

    /**
     * Get resolution processes keyed by operation.
     *
     * @return array<string, TaskProcess|null>
     */
    protected function getResolutionProcesses(TaskRun $taskRun): array
    {
        $operations = [
            FileOrganizationTaskRunner::OPERATION_LOW_CONFIDENCE_RESOLUTION,
            FileOrganizationTaskRunner::OPERATION_NULL_GROUP_RESOLUTION,
            FileOrganizationTaskRunner::OPERATION_DUPLICATE_GROUP_RESOLUTION,
        ];

        $processes = [];
        foreach ($operations as $op) {
            $processes[$op] = $taskRun->taskProcesses()
                ->where('operation', $op)
                ->first();
        }

        return $processes;
    }

    /**
     * Display window list with options.
     */
    protected function showWindowSummary(
        Collection $windowProcesses,
        Command $command,
        bool $showRaw = false,
        bool $showArtifacts = false,
        bool $showMessages = false
    ): void {
        $command->info('=== Window Comparison Processes ===');
        $command->line("Total windows: {$windowProcesses->count()}");

        foreach ($windowProcesses as $window) {
            $start    = $window->meta['window_start'] ?? '?';
            $end      = $window->meta['window_end']   ?? '?';
            $artifact = $window->outputArtifacts->first();

            if ($artifact && $artifact->json_content) {
                // New format: flat files array with group_name field
                $files = $artifact->json_content['files'] ?? [];

                // Group files by their group_name to match old display
                $groupedFiles = [];
                foreach ($files as $file) {
                    $groupName = $file['group_name'] ?? '';
                    if (!isset($groupedFiles[$groupName])) {
                        $groupedFiles[$groupName] = [];
                    }
                    $groupedFiles[$groupName][] = $file;
                }

                $command->line("  Window $start-$end: " . count($groupedFiles) . ' groups');

                foreach ($groupedFiles as $groupName => $groupFiles) {
                    $displayName = $groupName === '' ? '(null/blank)' : $groupName;
                    $fileCount   = count($groupFiles);
                    $command->line("    - $displayName: $fileCount files");
                }
            } else {
                $command->line("  Window $start-$end: 0 groups");
            }

            // Show raw artifact data when --raw flag is passed
            if ($showRaw && $artifact) {
                $command->line("    [RAW] Artifact ID: {$artifact->id}");
                $command->line('    [RAW] json_content is null: ' . ($artifact->json_content === null ? 'YES' : 'NO'));
                $command->line('    [RAW] json_content is empty array: ' . (is_array($artifact->json_content) && empty($artifact->json_content) ? 'YES' : 'NO'));
                $command->line('    [RAW] json_content has data: ' . (is_array($artifact->json_content) && !empty($artifact->json_content) ? 'YES' : 'NO'));
                if ($artifact->meta) {
                    $command->line('    [RAW] meta: ' . json_encode($artifact->meta, JSON_PRETTY_PRINT));
                }
            }

            // Show artifact JSON content when --artifacts flag is passed
            if ($showArtifacts && $artifact) {
                $command->newLine();
                $command->line('    [ARTIFACT] JSON Content (first 1000 chars):');
                $jsonContent = json_encode($artifact->json_content, JSON_PRETTY_PRINT);
                $truncated   = strlen($jsonContent) > 1000 ? substr($jsonContent, 0, 1000) . '... [truncated]' : $jsonContent;
                $command->line('    ' . str_replace("\n", "\n    ", $truncated));

                if ($artifact->meta) {
                    $command->line('    [ARTIFACT] Meta:');
                    $command->line('    ' . str_replace("\n", "\n    ", json_encode($artifact->meta, JSON_PRETTY_PRINT)));
                }
                $command->newLine();
            }

            // Show agent thread response when --artifacts or --messages flag is passed
            if (($showArtifacts || $showMessages) && $window->agentThread) {
                $thread               = $window->agentThread;
                $lastAssistantMessage = $thread->messages()
                    ->where('role', 'assistant')
                    ->orderByDesc('created_at')
                    ->first();

                if ($lastAssistantMessage) {
                    $command->newLine();
                    $command->line("    [AGENT] Thread ID: {$thread->id}");
                    $command->line('    [AGENT] Last Assistant Message (first 1500 chars):');
                    $content   = $lastAssistantMessage->content;
                    $truncated = strlen($content) > 1500 ? substr($content, 0, 1500) . '... [truncated]' : $content;
                    $command->line('    ' . str_replace("\n", "\n    ", $truncated));
                    $command->newLine();
                }
            }
        }
        $command->newLine();
    }

    /**
     * Show merge process information.
     */
    protected function showMergeProcessInfo(TaskRun $taskRun, Command $command): void
    {
        $command->info('=== Merge Process ===');
        $mergeProcess = $this->getMergeProcess($taskRun);

        if ($mergeProcess) {
            $command->line("Status: {$mergeProcess->status}");

            // Show metadata keys present
            $metaKeys = array_keys($mergeProcess->meta ?? []);
            $command->line('Meta keys: ' . implode(', ', $metaKeys));

            // Check for groups_for_deduplication
            $groupsForDedup = $mergeProcess->meta['groups_for_deduplication'] ?? null;
            if ($groupsForDedup) {
                $command->line('groups_for_deduplication: ' . count($groupsForDedup) . ' groups');
            } else {
                $command->line('groups_for_deduplication: NOT SET');
            }

            // Check for old duplicate_group_candidates key (for detecting old code paths)
            if (isset($mergeProcess->meta['duplicate_group_candidates'])) {
                $command->warn('duplicate_group_candidates: SET (old format detected!)');
            } else {
                $command->line('duplicate_group_candidates: NOT SET (good - using new format)');
            }

            // Check for low confidence files
            $lowConfFiles = $mergeProcess->meta['low_confidence_files'] ?? [];
            if (!empty($lowConfFiles)) {
                $command->warn('Low confidence files detected: ' . count($lowConfFiles));
                foreach ($lowConfFiles as $fileData) {
                    $page      = $fileData['page_number']                   ?? '?';
                    $bestGroup = $fileData['best_assignment']['group_name'] ?? 'Unknown';
                    $conf      = $fileData['best_assignment']['confidence'] ?? '?';
                    $command->line("  Page $page -> $bestGroup (confidence $conf)");
                }
            } else {
                $command->line('No low confidence files');
            }

            // Check for null group files needing LLM resolution
            $nullGroupFiles = $mergeProcess->meta['null_groups_needing_llm'] ?? [];
            if (!empty($nullGroupFiles)) {
                $command->warn('Null group files needing LLM resolution: ' . count($nullGroupFiles));
                foreach ($nullGroupFiles as $fileData) {
                    $page      = $fileData['page_number']    ?? '?';
                    $prevGroup = $fileData['previous_group'] ?? 'Unknown';
                    $nextGroup = $fileData['next_group']     ?? 'Unknown';
                    $command->line("  Page $page: between '$prevGroup' and '$nextGroup'");
                }
            } else {
                $command->line('No null group files needing resolution');
            }
        }
        $command->newLine();
    }

    /**
     * Show resolution process information.
     */
    protected function showResolutionProcessInfo(TaskRun $taskRun, Command $command): void
    {
        $resolutionProcesses = $this->getResolutionProcesses($taskRun);

        // Show low-confidence resolution process if exists
        $resolutionProcess = $resolutionProcesses[FileOrganizationTaskRunner::OPERATION_LOW_CONFIDENCE_RESOLUTION] ?? null;
        if ($resolutionProcess) {
            $command->info('=== Low Confidence Resolution Process ===');
            $command->line("Status: {$resolutionProcess->status}");
            $command->newLine();
        }

        // Show null group resolution process if exists
        $nullGroupResolutionProcess = $resolutionProcesses[FileOrganizationTaskRunner::OPERATION_NULL_GROUP_RESOLUTION] ?? null;
        if ($nullGroupResolutionProcess) {
            $command->info('=== Null Group Resolution Process ===');
            $command->line("Status: {$nullGroupResolutionProcess->status}");
            $command->newLine();
        }
    }

    /**
     * Show duplicate group resolution information.
     */
    protected function showDuplicateGroupInfo(TaskRun $taskRun, Command $command, bool $showDedup = false): void
    {
        $resolutionProcesses = $this->getResolutionProcesses($taskRun);
        $dedupProcess        = $resolutionProcesses[FileOrganizationTaskRunner::OPERATION_DUPLICATE_GROUP_RESOLUTION] ?? null;
        $mergeProcess        = $this->getMergeProcess($taskRun);

        if ($dedupProcess) {
            $command->info('=== Duplicate Group Resolution Process ===');
            $command->line("Status: {$dedupProcess->status}");

            // Get groups sent for deduplication from merge process metadata
            if ($mergeProcess && isset($mergeProcess->meta['groups_for_deduplication'])) {
                $groupsForDedup = $mergeProcess->meta['groups_for_deduplication'];
                $command->newLine();
                $command->line('Groups for Deduplication: ' . count($groupsForDedup));

                foreach ($groupsForDedup as $group) {
                    $name        = $group['name']         ?? 'Unknown';
                    $fileCount   = $group['file_count']   ?? 0;
                    $sampleFiles = $group['sample_files'] ?? [];

                    // Extract page numbers and confidences
                    $samplePages = array_column($sampleFiles, 'page_number');
                    $sampleConfs = array_column($sampleFiles, 'confidence');

                    $pagesStr = implode(', ', $samplePages);
                    $confsStr = implode(', ', $sampleConfs);

                    $command->line("  - $name: $fileCount files, samples: [$pagesStr] (conf: $confsStr)");
                }

                // Show full dedup metadata when --dedup flag is set
                if ($showDedup) {
                    $command->newLine();
                    $command->info('=== Full Deduplication Metadata ===');
                    $command->line(json_encode($groupsForDedup, JSON_PRETTY_PRINT));
                }
            }

            $command->newLine();
        }
    }

    /**
     * Show group analysis (confidence distribution).
     */
    protected function showGroupAnalysis(TaskRun $taskRun, Command $command): void
    {
        $command->info('=== Group Analysis ===');
        $outputArtifacts = $taskRun->outputArtifacts()->get();
        $command->line("Total final groups: {$outputArtifacts->count()}");

        // Show confidence distribution
        foreach ($outputArtifacts as $artifact) {
            $groupName   = $artifact->meta['group_name']         ?? 'Unknown';
            $confSummary = $artifact->meta['confidence_summary'] ?? null;

            if ($confSummary) {
                $avg = $confSummary['avg'] ?? '?';
                $min = $confSummary['min'] ?? '?';
                $max = $confSummary['max'] ?? '?';

                $confidenceLevel = 'MIXED';
                if ($min >= 4) {
                    $confidenceLevel = 'HIGH (all >= 4)';
                } elseif ($max < 3) {
                    $confidenceLevel = 'LOW (all < 3)';
                }

                $command->line("  '$groupName': $confidenceLevel - avg=$avg, min=$min, max=$max");
            }
        }
        $command->newLine();
    }

    /**
     * Show final output groups.
     */
    protected function showFinalOutputGroups(TaskRun $taskRun, Command $command, bool $verbose = false): void
    {
        $command->info('=== Final Output Groups ===');
        $outputArtifacts = $taskRun->outputArtifacts()->get();

        foreach ($outputArtifacts as $artifact) {
            $groupName   = $artifact->meta['group_name']         ?? 'Unknown';
            $fileCount   = $artifact->meta['file_count']         ?? 0;
            $description = $artifact->meta['description']        ?? '';
            $confSummary = $artifact->meta['confidence_summary'] ?? null;

            $command->line("Group: $groupName");
            $command->line("   Files: $fileCount");
            if ($confSummary) {
                $avg = $confSummary['avg'] ?? '?';
                $min = $confSummary['min'] ?? '?';
                $max = $confSummary['max'] ?? '?';
                $command->line("   Confidence: avg=$avg, min=$min, max=$max");
            }
            $command->line("   Description: $description");

            // Show which pages are in this group
            if ($verbose) {
                $children    = $artifact->children()->orderBy('position')->get();
                $pageNumbers = [];
                foreach ($children as $child) {
                    $storedFile    = $child->storedFiles->first();
                    $pageNumbers[] = $storedFile?->page_number ?? $child->position;
                }
                $command->line('   Pages: ' . implode(', ', $pageNumbers));
            }

            $command->newLine();
        }
    }

    /**
     * Show first window's agent thread messages.
     */
    protected function showFirstWindowMessages(Collection $windowProcesses, Command $command): void
    {
        $command->info('=== Agent Thread Messages ===');

        // Show first window's thread as example
        $firstWindow = $windowProcesses->first();
        if ($firstWindow && $firstWindow->agentThread) {
            $thread = $firstWindow->agentThread;
            $command->line("Window {$firstWindow->meta['window_start']}-{$firstWindow->meta['window_end']} thread:");
            $command->newLine();

            foreach ($thread->messages()->orderBy('created_at')->get() as $message) {
                $role    = $message->role;
                $content = $message->content;

                // Truncate long content
                if (strlen($content) > 500) {
                    $content = substr($content, 0, 500) . '... [truncated]';
                }

                $command->line("[$role] $content");
                $command->newLine();
            }
        }
    }
}
