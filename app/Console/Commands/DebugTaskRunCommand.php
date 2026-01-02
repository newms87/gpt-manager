<?php

namespace App\Console\Commands;

use App\Models\Task\TaskRun;
use App\Services\Task\Debug\ExtractDataDebugService;
use App\Services\Task\FileOrganization\ResolutionOrchestrator;
use App\Services\Task\Runners\ExtractDataTaskRunner;
use App\Services\Task\Runners\FileOrganizationTaskRunner;
use App\Services\Task\TaskProcessDispatcherService;
use App\Services\Task\TaskRunnerService;
use Illuminate\Console\Command;

class DebugTaskRunCommand extends Command
{
    protected $signature = 'debug:task-run {task-run : TaskRun ID}
        {--messages : Show agent thread messages}
        {--artifacts : Show artifact JSON content}
        {--raw : Show raw artifact data}
        {--dedup : Show full deduplication metadata}
        {--process= : Show detailed info for specific task process ID}
        {--run-process= : Run a specific task process ID synchronously to debug exceptions (ExtractData)}
        {--window= : Show detailed info for specific window (e.g., 1-10)}
        {--page= : Show all data about a specific page number across all windows}
        {--group= : Show all pages assigned to a specific group name}
        {--mismatches : Show pages with conflicting group assignments across windows}
        {--rerun-merge : Delete existing merge output and rerun the merge process}
        {--rerun-dedup : Delete existing dedup output and rerun the duplicate group resolution process}
        {--reset-from-windows : Delete merge and all resolution processes, then re-create from windows}
        {--classify-status : Show status of all classify processes (ExtractData)}
        {--artifact-tree : Show parent/child artifact hierarchy (ExtractData)}
        {--resolved-objects : Show all TeamObjects created during extraction (ExtractData)}
        {--taskrun-meta : Show the full TaskRun meta data (ExtractData)}
        {--level-progress : Show extraction level progress for each level (ExtractData)}
        {--run : Create and dispatch a new task run with same inputs}
        {--rerun : Reset and re-dispatch this task run}';

    protected $description = 'Debug a TaskRun to understand agent communication and results';

    public function handle(): int
    {
        $taskRunId = $this->argument('task-run');
        $taskRun   = TaskRun::findOrFail($taskRunId);

        // Handle --run and --rerun options first (before any other processing)
        if ($this->option('run')) {
            return $this->handleRun($taskRun);
        }

        if ($this->option('rerun')) {
            return $this->handleRerun($taskRun);
        }

        // Detect task runner type
        $runnerName = $taskRun->taskDefinition->task_runner_name;

        // Route to ExtractData debug service if applicable
        if ($runnerName === ExtractDataTaskRunner::RUNNER_NAME) {
            return $this->handleExtractDataDebug($taskRun);
        }

        // Handle FileOrganization rerun options first
        if ($this->option('rerun-merge')) {
            return $this->handleRerunMerge($taskRun);
        }

        if ($this->option('rerun-dedup')) {
            return $this->handleRerunDedup($taskRun);
        }

        if ($this->option('reset-from-windows')) {
            return $this->handleResetFromWindows($taskRun);
        }

        // Handle specialized investigation options
        if ($this->option('process')) {
            return $this->handleProcessOption($taskRun);
        }

        if ($this->option('window')) {
            return $this->handleWindowOption($taskRun);
        }

        if ($this->option('page')) {
            return $this->handlePageOption($taskRun);
        }

        if ($this->option('group')) {
            return $this->handleGroupOption($taskRun);
        }

        if ($this->option('mismatches')) {
            return $this->handleMismatchesOption($taskRun);
        }

        // Default: show full overview
        $this->info("=== TaskRun $taskRunId ===");
        $this->line("Status: {$taskRun->status}");
        $this->line("TaskDefinition: {$taskRun->taskDefinition->name}");
        $this->line("Runner: {$taskRun->taskDefinition->task_runner_name}");
        $this->newLine();

        // Show task definition prompt (user's domain-specific prompt)
        $this->info("=== User's Task Definition Prompt ===");
        $this->line($taskRun->taskDefinition->prompt ?: '(none)');
        $this->newLine();

        // Show input artifacts
        $this->info('=== Input Artifacts ===');
        $inputArtifacts = $taskRun->inputArtifacts()->orderBy('position')->get();
        $this->line("Total: {$inputArtifacts->count()} files");

        // Show sample pages
        foreach ($inputArtifacts->take(5) as $artifact) {
            $storedFile = $artifact->storedFiles->first();
            $pageNumber = $storedFile?->page_number ?? $artifact->position;
            $this->line("  Page $pageNumber: {$artifact->name}");
        }
        if ($inputArtifacts->count() > 5) {
            $this->line('  ... and ' . ($inputArtifacts->count() - 5) . ' more');
        }
        $this->newLine();

        // Show window processes
        $this->info('=== Window Comparison Processes ===');
        $windowProcesses = $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW)
            ->get();

        $this->line("Total windows: {$windowProcesses->count()}");

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

                $this->line("  Window $start-$end: " . count($groupedFiles) . ' groups');

                foreach ($groupedFiles as $groupName => $groupFiles) {
                    $displayName = $groupName === '' ? '(null/blank)' : $groupName;
                    $fileCount   = count($groupFiles);
                    $this->line("    - $displayName: $fileCount files");

                    // Show confidence scores
                    if ($this->option('verbose')) {
                        foreach ($groupFiles as $file) {
                            $page = $file['page_number']            ?? '?';
                            $conf = $file['group_name_confidence']  ?? '?';
                            $this->line("      Page $page: confidence $conf");
                        }
                    }
                }
            } else {
                $this->line("  Window $start-$end: 0 groups");
            }

            // Show raw artifact data when --raw flag is passed
            if ($this->option('raw') && $artifact) {
                $this->line("    [RAW] Artifact ID: {$artifact->id}");
                $this->line('    [RAW] json_content is null: ' . ($artifact->json_content === null ? 'YES' : 'NO'));
                $this->line('    [RAW] json_content is empty array: ' . (is_array($artifact->json_content) && empty($artifact->json_content) ? 'YES' : 'NO'));
                $this->line('    [RAW] json_content has data: ' . (is_array($artifact->json_content) && !empty($artifact->json_content) ? 'YES' : 'NO'));
                if ($artifact->meta) {
                    $this->line('    [RAW] meta: ' . json_encode($artifact->meta, JSON_PRETTY_PRINT));
                }
            }

            // Show artifact JSON content when --artifacts flag is passed
            if ($this->option('artifacts') && $artifact) {
                $this->newLine();
                $this->line('    [ARTIFACT] JSON Content (first 1000 chars):');
                $jsonContent = json_encode($artifact->json_content, JSON_PRETTY_PRINT);
                $truncated   = strlen($jsonContent) > 1000 ? substr($jsonContent, 0, 1000) . '... [truncated]' : $jsonContent;
                $this->line('    ' . str_replace("\n", "\n    ", $truncated));

                if ($artifact->meta) {
                    $this->line('    [ARTIFACT] Meta:');
                    $this->line('    ' . str_replace("\n", "\n    ", json_encode($artifact->meta, JSON_PRETTY_PRINT)));
                }
                $this->newLine();
            }

            // Show agent thread response when --artifacts or --messages flag is passed
            if (($this->option('artifacts') || $this->option('messages')) && $window->agentThread) {
                $thread               = $window->agentThread;
                $lastAssistantMessage = $thread->messages()
                    ->where('role', 'assistant')
                    ->orderByDesc('created_at')
                    ->first();

                if ($lastAssistantMessage) {
                    $this->newLine();
                    $this->line("    [AGENT] Thread ID: {$thread->id}");
                    $this->line('    [AGENT] Last Assistant Message (first 1500 chars):');
                    $content   = $lastAssistantMessage->content;
                    $truncated = strlen($content) > 1500 ? substr($content, 0, 1500) . '... [truncated]' : $content;
                    $this->line('    ' . str_replace("\n", "\n    ", $truncated));
                    $this->newLine();
                }
            }
        }
        $this->newLine();

        // Show merge process
        $this->info('=== Merge Process ===');
        $mergeProcess = $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_MERGE)
            ->first();

        if ($mergeProcess) {
            $this->line("Status: {$mergeProcess->status}");

            // Show metadata keys present
            $metaKeys = array_keys($mergeProcess->meta ?? []);
            $this->line('Meta keys: ' . implode(', ', $metaKeys));

            // Check for groups_for_deduplication
            $groupsForDedup = $mergeProcess->meta['groups_for_deduplication'] ?? null;
            if ($groupsForDedup) {
                $this->line('groups_for_deduplication: ' . count($groupsForDedup) . ' groups');
            } else {
                $this->line('groups_for_deduplication: NOT SET');
            }

            // Check for old duplicate_group_candidates key (for detecting old code paths)
            if (isset($mergeProcess->meta['duplicate_group_candidates'])) {
                $this->warn('duplicate_group_candidates: SET (old format detected!)');
            } else {
                $this->line('duplicate_group_candidates: NOT SET (good - using new format)');
            }

            // Check for low confidence files
            $lowConfFiles = $mergeProcess->meta['low_confidence_files'] ?? [];
            if (!empty($lowConfFiles)) {
                $this->warn('Low confidence files detected: ' . count($lowConfFiles));
                foreach ($lowConfFiles as $fileData) {
                    $page      = $fileData['page_number']                   ?? '?';
                    $bestGroup = $fileData['best_assignment']['group_name'] ?? 'Unknown';
                    $conf      = $fileData['best_assignment']['confidence'] ?? '?';
                    $this->line("  Page $page -> $bestGroup (confidence $conf)");
                }
            } else {
                $this->line('No low confidence files');
            }

            // Check for null group files needing LLM resolution
            $nullGroupFiles = $mergeProcess->meta['null_groups_needing_llm'] ?? [];
            if (!empty($nullGroupFiles)) {
                $this->warn('Null group files needing LLM resolution: ' . count($nullGroupFiles));
                foreach ($nullGroupFiles as $fileData) {
                    $page      = $fileData['page_number']    ?? '?';
                    $prevGroup = $fileData['previous_group'] ?? 'Unknown';
                    $nextGroup = $fileData['next_group']     ?? 'Unknown';
                    $this->line("  Page $page: between '$prevGroup' and '$nextGroup'");
                }
            } else {
                $this->line('No null group files needing resolution');
            }
        }
        $this->newLine();

        // Show low-confidence resolution process if exists
        $resolutionProcess = $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_LOW_CONFIDENCE_RESOLUTION)
            ->first();

        if ($resolutionProcess) {
            $this->info('=== Low Confidence Resolution Process ===');
            $this->line("Status: {$resolutionProcess->status}");
            $this->newLine();
        }

        // Show null group resolution process if exists
        $nullGroupResolutionProcess = $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_NULL_GROUP_RESOLUTION)
            ->first();

        if ($nullGroupResolutionProcess) {
            $this->info('=== Null Group Resolution Process ===');
            $this->line("Status: {$nullGroupResolutionProcess->status}");
            $this->newLine();
        }

        // Show duplicate group resolution process if exists
        $dedupProcess = $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_DUPLICATE_GROUP_RESOLUTION)
            ->first();

        if ($dedupProcess) {
            $this->info('=== Duplicate Group Resolution Process ===');
            $this->line("Status: {$dedupProcess->status}");

            // Get groups sent for deduplication from merge process metadata
            if ($mergeProcess && isset($mergeProcess->meta['groups_for_deduplication'])) {
                $groupsForDedup = $mergeProcess->meta['groups_for_deduplication'];
                $this->newLine();
                $this->line('Groups for Deduplication: ' . count($groupsForDedup));

                foreach ($groupsForDedup as $group) {
                    $name        = $group['name']         ?? 'Unknown';
                    $fileCount   = $group['file_count']   ?? 0;
                    $sampleFiles = $group['sample_files'] ?? [];

                    // Extract page numbers and confidences
                    $samplePages = array_column($sampleFiles, 'page_number');
                    $sampleConfs = array_column($sampleFiles, 'confidence');

                    $pagesStr = implode(', ', $samplePages);
                    $confsStr = implode(', ', $sampleConfs);

                    $this->line("  - $name: $fileCount files, samples: [$pagesStr] (conf: $confsStr)");
                }

                // Show full deduplication details when --dedup flag is passed
                if ($this->option('dedup')) {
                    $this->newLine();
                    $this->line('=== Full Deduplication Metadata ===');
                    $this->line(json_encode($groupsForDedup, JSON_PRETTY_PRINT));
                }
            }

            $this->newLine();
        }

        // Show group absorption summary
        $this->info('=== Group Analysis ===');
        $outputArtifacts = $taskRun->outputArtifacts()->get();
        $this->line("Total final groups: {$outputArtifacts->count()}");

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

                $this->line("  '$groupName': $confidenceLevel - avg=$avg, min=$min, max=$max");
            }
        }
        $this->newLine();

        // Show final output
        $this->info('=== Final Output Groups ===');

        foreach ($outputArtifacts as $artifact) {
            $groupName   = $artifact->meta['group_name']         ?? 'Unknown';
            $fileCount   = $artifact->meta['file_count']         ?? 0;
            $description = $artifact->meta['description']        ?? '';
            $confSummary = $artifact->meta['confidence_summary'] ?? null;

            $this->line("📁 Group: $groupName");
            $this->line("   Files: $fileCount");
            if ($confSummary) {
                $avg = $confSummary['avg'] ?? '?';
                $min = $confSummary['min'] ?? '?';
                $max = $confSummary['max'] ?? '?';
                $this->line("   Confidence: avg=$avg, min=$min, max=$max");
            }
            $this->line("   Description: $description");

            // Show which pages are in this group
            if ($this->option('verbose')) {
                $children    = $artifact->children()->orderBy('position')->get();
                $pageNumbers = [];
                foreach ($children as $child) {
                    $storedFile    = $child->storedFiles->first();
                    $pageNumbers[] = $storedFile?->page_number ?? $child->position;
                }
                $this->line('   Pages: ' . implode(', ', $pageNumbers));
            }

            $this->newLine();
        }

        // Show agent thread messages if requested
        if ($this->option('messages')) {
            $this->info('=== Agent Thread Messages ===');

            // Show first window's thread as example
            $firstWindow = $windowProcesses->first();
            if ($firstWindow && $firstWindow->agentThread) {
                $thread = $firstWindow->agentThread;
                $this->line("Window {$firstWindow->meta['window_start']}-{$firstWindow->meta['window_end']} thread:");
                $this->newLine();

                foreach ($thread->messages()->orderBy('created_at')->get() as $message) {
                    $role    = $message->role;
                    $content = $message->content;

                    // Truncate long content
                    if (strlen($content) > 500) {
                        $content = substr($content, 0, 500) . '... [truncated]';
                    }

                    $this->line("[$role] $content");
                    $this->newLine();
                }
            }
        }

        return 0;
    }

    /**
     * Handle ExtractData debug routing.
     */
    protected function handleExtractDataDebug(TaskRun $taskRun): int
    {
        $debugService = app(ExtractDataDebugService::class);

        // Handle specialized options
        if ($this->option('run-process')) {
            $processId = (int)$this->option('run-process');

            return $debugService->runProcess($taskRun, $processId, $this);
        }

        if ($this->option('process')) {
            $processId = (int)$this->option('process');

            return $debugService->showProcessDetail($taskRun, $processId, $this);
        }

        if ($this->option('classify-status')) {
            $debugService->showClassifyProcesses($taskRun, $this);

            return 0;
        }

        if ($this->option('artifact-tree')) {
            $debugService->showArtifactStructure($taskRun, $this);

            return 0;
        }

        if ($this->option('resolved-objects')) {
            $debugService->showResolvedObjects($taskRun, $this);

            return 0;
        }

        if ($this->option('taskrun-meta')) {
            $debugService->showTaskRunMeta($taskRun, $this);

            return 0;
        }

        if ($this->option('level-progress')) {
            $debugService->showLevelProgress($taskRun, $this);

            return 0;
        }

        // Default: show overview
        $debugService->showOverview($taskRun, $this);

        return 0;
    }

    /**
     * Handle --process option: Show detailed info about a specific TaskProcess
     */
    protected function handleProcessOption(TaskRun $taskRun): int
    {
        $processId = $this->option('process');
        $process   = $taskRun->taskProcesses()->find($processId);

        if (!$process) {
            $this->error("TaskProcess $processId not found in TaskRun {$taskRun->id}");

            return 1;
        }

        $this->info("=== TaskProcess $processId Details ===");
        $this->line("Status: {$process->status}");
        $this->line("Operation: {$process->operation}");
        $this->line("Activity: {$process->activity}");
        $this->newLine();

        // Show meta data
        if ($process->meta) {
            $this->info('=== Meta Data ===');
            $this->line(json_encode($process->meta, JSON_PRETTY_PRINT));
            $this->newLine();
        }

        // Show input artifacts
        $this->info('=== Input Artifacts ===');
        $inputArtifacts = $process->inputArtifacts;
        $this->line("Total: {$inputArtifacts->count()} artifacts");

        foreach ($inputArtifacts as $artifact) {
            $this->line("  Artifact #{$artifact->id}: {$artifact->name}");
            if ($artifact->json_content) {
                $this->line('    JSON Content:');
                $this->line('    ' . str_replace("\n", "\n    ", json_encode($artifact->json_content, JSON_PRETTY_PRINT)));
            }
        }
        $this->newLine();

        // Show output artifacts with full JSON content
        $this->info('=== Output Artifacts ===');
        $outputArtifacts = $process->outputArtifacts;
        $this->line("Total: {$outputArtifacts->count()} artifacts");

        foreach ($outputArtifacts as $artifact) {
            $this->line("  Artifact #{$artifact->id}: {$artifact->name}");
            if ($artifact->json_content) {
                $this->line('    JSON Content:');
                $this->line('    ' . str_replace("\n", "\n    ", json_encode($artifact->json_content, JSON_PRETTY_PRINT)));
            }
            if ($artifact->meta) {
                $this->line('    Meta:');
                $this->line('    ' . str_replace("\n", "\n    ", json_encode($artifact->meta, JSON_PRETTY_PRINT)));
            }
        }
        $this->newLine();

        // Show agent thread messages (full content, not truncated)
        if ($process->agentThread) {
            $this->info('=== Agent Thread Messages ===');
            $thread = $process->agentThread;
            $this->line("Thread ID: {$thread->id}");
            $this->newLine();

            foreach ($thread->messages()->orderBy('created_at')->get() as $message) {
                $this->line("[$message->role] - {$message->created_at}");
                $this->line($message->content);
                $this->newLine();
            }
        }

        return 0;
    }

    /**
     * Handle --window option: Show detailed info about a specific window
     */
    protected function handleWindowOption(TaskRun $taskRun): int
    {
        $windowRange = $this->option('window');

        // Parse window range (e.g., "1-10")
        if (!preg_match('/^(\d+)-(\d+)$/', $windowRange, $matches)) {
            $this->error('Invalid window format. Expected format: 1-10');

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
            $this->error("Window $windowRange not found");

            return 1;
        }

        $this->info("=== Window $windowRange Details ===");
        $this->line("TaskProcess ID: {$windowProcess->id}");
        $this->line("Status: {$windowProcess->status}");
        $this->newLine();

        // Show output artifact with full JSON content
        $artifact = $windowProcess->outputArtifacts->first();
        if ($artifact && $artifact->json_content) {
            $this->info('=== Files in Window ===');
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
                $this->line("Group: $displayName");

                foreach ($groupFiles as $file) {
                    $page     = $file['page_number']            ?? '?';
                    $conf     = $file['group_name_confidence']  ?? '?';
                    $belongs  = $file['belongs_to_previous']    ?? '?';
                    $this->line("  Page $page: confidence=$conf, belongs_to_previous=$belongs");
                }
                $this->newLine();
            }

            $this->info('=== Full JSON Content ===');
            $this->line(json_encode($artifact->json_content, JSON_PRETTY_PRINT));
            $this->newLine();
        }

        // Show agent thread conversation
        if ($windowProcess->agentThread) {
            $this->info('=== Agent Thread Conversation ===');
            $thread = $windowProcess->agentThread;

            foreach ($thread->messages()->orderBy('created_at')->get() as $message) {
                $this->line("[$message->role] - {$message->created_at}");
                $this->line($message->content);
                $this->newLine();
            }
        }

        return 0;
    }

    /**
     * Handle --page option: Show all data about a specific page across all windows
     */
    protected function handlePageOption(TaskRun $taskRun): int
    {
        $pageNumber = (int)$this->option('page');

        $this->info("=== Page $pageNumber Analysis ===");
        $this->newLine();

        $windowProcesses = $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW)
            ->get();

        $conflicts   = [];
        $assignments = [];

        foreach ($windowProcesses as $window) {
            $start    = $window->meta['window_start'] ?? '?';
            $end      = $window->meta['window_end']   ?? '?';
            $artifact = $window->outputArtifacts->first();

            if ($artifact && $artifact->json_content) {
                $files = $artifact->json_content['files'] ?? [];

                // Find this page in the window
                foreach ($files as $file) {
                    if (($file['page_number'] ?? null) === $pageNumber) {
                        $groupName = $file['group_name']            ?? '';
                        $conf      = $file['group_name_confidence'] ?? '?';
                        $belongs   = $file['belongs_to_previous']   ?? '?';

                        $assignment = [
                            'window'     => "$start-$end",
                            'group_name' => $groupName,
                            'confidence' => $conf,
                            'belongs'    => $belongs,
                        ];

                        $assignments[] = $assignment;

                        $this->line("Window $start-$end:");
                        $this->line('  Group: ' . ($groupName === '' ? '(null/blank)' : $groupName));
                        $this->line("  Confidence: $conf");
                        $this->line("  Belongs to Previous: $belongs");
                        $this->newLine();
                    }
                }
            }
        }

        if (empty($assignments)) {
            $this->warn("Page $pageNumber not found in any window");

            return 1;
        }

        // Check for conflicts (different group names)
        $uniqueGroups = array_unique(array_column($assignments, 'group_name'));
        if (count($uniqueGroups) > 1) {
            $this->warn('=== CONFLICT DETECTED ===');
            $this->line("Page $pageNumber has " . count($uniqueGroups) . ' different group assignments:');
            foreach ($uniqueGroups as $group) {
                $displayName     = $group === '' ? '(null/blank)' : $group;
                $windowsWithThis = array_filter($assignments, fn($a) => $a['group_name'] === $group);
                $windowList      = implode(', ', array_column($windowsWithThis, 'window'));
                $this->line("  - \"$displayName\" in windows: $windowList");
            }
        } else {
            $this->info('No conflicts - page has consistent group assignment across all windows');
        }

        return 0;
    }

    /**
     * Handle --group option: Show all pages assigned to a specific group
     */
    protected function handleGroupOption(TaskRun $taskRun): int
    {
        $groupName = $this->option('group');

        $this->info("=== Pages Assigned to Group: \"$groupName\" ===");
        $this->newLine();

        $windowProcesses = $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW)
            ->get();

        $allPages = [];

        foreach ($windowProcesses as $window) {
            $start    = $window->meta['window_start'] ?? '?';
            $end      = $window->meta['window_end']   ?? '?';
            $artifact = $window->outputArtifacts->first();

            if ($artifact && $artifact->json_content) {
                $files = $artifact->json_content['files'] ?? [];

                $matchingFiles = array_filter($files, fn($f) => ($f['group_name'] ?? '') === $groupName);

                if (!empty($matchingFiles)) {
                    $this->line("Window $start-$end:");
                    foreach ($matchingFiles as $file) {
                        $page = $file['page_number']           ?? '?';
                        $conf = $file['group_name_confidence'] ?? '?';

                        $this->line("  Page $page (confidence: $conf)");

                        if (!isset($allPages[$page])) {
                            $allPages[$page] = [];
                        }
                        $allPages[$page][] = [
                            'window'     => "$start-$end",
                            'confidence' => $conf,
                        ];
                    }
                    $this->newLine();
                }
            }
        }

        if (empty($allPages)) {
            $this->warn("No pages found assigned to group \"$groupName\"");

            return 1;
        }

        $this->info('=== Summary ===');
        $this->line('Total unique pages: ' . count($allPages));
        foreach ($allPages as $page => $windows) {
            $windowList = implode(', ', array_column($windows, 'window'));
            $avgConf    = array_sum(array_column($windows, 'confidence')) / count($windows);
            $this->line("  Page $page: seen in " . count($windows) . " window(s) [$windowList], avg confidence: " . round($avgConf, 1));
        }

        return 0;
    }

    /**
     * Handle --mismatches option: Show pages with conflicting group assignments
     */
    protected function handleMismatchesOption(TaskRun $taskRun): int
    {
        $this->info('=== Group Assignment Conflicts ===');
        $this->newLine();

        $windowProcesses = $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW)
            ->get();

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
            $this->info('No group assignment conflicts found!');

            return 0;
        }

        $this->warn('Found ' . count($conflicts) . ' pages with conflicting assignments:');
        $this->newLine();

        foreach ($conflicts as $page => $assignments) {
            $this->line("Page $page:");

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

                $this->line("  \"$displayName\" (avg confidence: " . round($avgConf, 1) . ") in windows: $windows");
            }

            $this->newLine();
        }

        return 0;
    }

    /**
     * Handle --rerun-merge option: Delete merge output and rerun the merge process
     */
    protected function handleRerunMerge(TaskRun $taskRun): int
    {
        $mergeProcess = $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_MERGE)
            ->first();

        if (!$mergeProcess) {
            $this->error('No merge process found');

            return 1;
        }

        $this->info("Rerunning merge process ID: {$mergeProcess->id}");

        // Delete output artifacts
        foreach ($mergeProcess->outputArtifacts as $artifact) {
            $this->line("  Deleting artifact: {$artifact->id}");
            $artifact->delete();
        }
        $mergeProcess->outputArtifacts()->detach();

        // Reset status and meta
        $mergeProcess->status   = 'Pending';
        $mergeProcess->meta     = [];
        $mergeProcess->is_ready = true;
        $mergeProcess->save();

        $this->info('Merge process reset to Pending status');

        // Dispatch to rerun
        TaskProcessDispatcherService::dispatchForTaskRun($taskRun);

        $this->info('Merge process queued for rerun');

        return 0;
    }

    /**
     * Handle --rerun-dedup option: Delete dedup output and rerun duplicate group resolution
     */
    protected function handleRerunDedup(TaskRun $taskRun): int
    {
        $dedupProcess = $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_DUPLICATE_GROUP_RESOLUTION)
            ->first();

        if (!$dedupProcess) {
            $this->error('No duplicate group resolution process found');

            return 1;
        }

        $this->info("Rerunning duplicate group resolution process ID: {$dedupProcess->id}");

        // Get groups_for_deduplication from merge process
        $mergeProcess = $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_MERGE)
            ->first();

        if (!$mergeProcess) {
            $this->error('No merge process found - cannot get groups_for_deduplication');

            return 1;
        }

        $groupsForDeduplication = $mergeProcess->meta['groups_for_deduplication'] ?? [];

        if (empty($groupsForDeduplication)) {
            $this->error('No groups_for_deduplication found in merge process meta');

            return 1;
        }

        $this->info('Found ' . count($groupsForDeduplication) . ' groups for deduplication');

        // Delete output artifacts
        foreach ($dedupProcess->outputArtifacts as $artifact) {
            $this->line("  Deleting artifact: {$artifact->id}");
            $artifact->delete();
        }
        $dedupProcess->outputArtifacts()->detach();

        // Reset status and meta - preserve groups_for_deduplication
        $dedupProcess->status   = 'Pending';
        $dedupProcess->meta     = ['groups_for_deduplication' => $groupsForDeduplication];
        $dedupProcess->is_ready = true;
        $dedupProcess->save();

        $this->info('Duplicate group resolution process reset to Pending status');

        // Dispatch to rerun
        TaskProcessDispatcherService::dispatchForTaskRun($taskRun);

        $this->info('Duplicate group resolution process queued for rerun');

        return 0;
    }

    /**
     * Handle --reset-from-windows option: Delete all merge and resolution processes, recreate from windows
     */
    protected function handleResetFromWindows(TaskRun $taskRun): int
    {
        $this->info('Resetting task run from window results...');

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
                $this->line("  Deleting process: {$process->id} ({$process->operation})");

                // Delete output artifacts
                foreach ($process->outputArtifacts as $artifact) {
                    $this->line("    Deleting artifact: {$artifact->id}");
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
            $this->line("  Deleting {$outputCount} task run output artifacts");
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

        $this->info('Reset complete. Creating merge process...');

        // Use ResolutionOrchestrator to create the merge process
        $orchestrator = app(ResolutionOrchestrator::class);
        $mergeCreated = $orchestrator->createMergeProcessIfReady($taskRun);

        if ($mergeCreated) {
            $this->info('Merge process created successfully');

            // Dispatch to run the merge
            TaskProcessDispatcherService::dispatchForTaskRun($taskRun);

            $this->info('Merge process dispatched for execution');
        } else {
            $this->warn('Failed to create merge process - windows may not be ready');

            return 1;
        }

        return 0;
    }

    /**
     * Handle --run option: Create a new task run with same inputs and dispatch it
     */
    protected function handleRun(TaskRun $taskRun): int
    {
        $taskDef        = $taskRun->taskDefinition;
        $inputArtifacts = $taskRun->inputArtifacts()->get();

        $this->info("Creating new TaskRun for: {$taskDef->name}");
        $this->line("Input artifacts: {$inputArtifacts->count()}");

        // Create new task run
        $newTaskRun = $taskDef->taskRuns()->create([
            'team_id' => $taskDef->team_id,
            'status'  => 'Pending',
        ]);

        // Attach input artifacts
        $newTaskRun->addInputArtifacts($inputArtifacts);

        $this->info("Created TaskRun: {$newTaskRun->id}");

        // Prepare task processes (creates the initial Default Task process)
        $processes = TaskRunnerService::prepareTaskProcesses($newTaskRun);
        $this->line('Prepared ' . count($processes) . ' task process(es)');

        // Start it
        TaskRunnerService::continue($newTaskRun);

        $this->info("Started! Use: php artisan debug:task-run {$newTaskRun->id}");

        return 0;
    }

    /**
     * Handle --rerun option: Reset and re-dispatch this task run
     */
    protected function handleRerun(TaskRun $taskRun): int
    {
        $this->info("Resetting TaskRun {$taskRun->id}");

        // Delete all task processes
        $processCount = $taskRun->taskProcesses()->count();
        $taskRun->taskProcesses()->delete();
        $this->line("Deleted $processCount task processes");

        // Delete output artifacts
        foreach ($taskRun->outputArtifacts as $artifact) {
            // Delete children first
            $artifact->children()->delete();
            $artifact->delete();
        }
        $taskRun->outputArtifacts()->detach();
        $taskRun->updateRelationCounter('outputArtifacts');
        $this->line('Deleted output artifacts');

        // Reset status
        $taskRun->status       = 'Pending';
        $taskRun->meta         = [];
        $taskRun->started_at   = null;
        $taskRun->completed_at = null;
        $taskRun->save();

        // Prepare task processes (creates the initial Default Task process)
        $processes = TaskRunnerService::prepareTaskProcesses($taskRun);
        $this->line('Prepared ' . count($processes) . ' task process(es)');

        // Start it
        TaskRunnerService::continue($taskRun);

        $this->info("Re-started TaskRun {$taskRun->id}");

        return 0;
    }
}
