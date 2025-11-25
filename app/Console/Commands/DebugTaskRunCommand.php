<?php

namespace App\Console\Commands;

use App\Models\Task\TaskRun;
use App\Services\Task\Runners\FileOrganizationTaskRunner;
use Illuminate\Console\Command;

class DebugTaskRunCommand extends Command
{
    protected $signature = 'debug:task-run {task-run : TaskRun ID} {--messages : Show agent thread messages}';

    protected $description = 'Debug a TaskRun to understand agent communication and results';

    public function handle(): int
    {
        $taskRunId = $this->argument('task-run');
        $taskRun = TaskRun::findOrFail($taskRunId);

        $this->info("=== TaskRun $taskRunId ===" );
        $this->line("Status: {$taskRun->status}");
        $this->line("TaskDefinition: {$taskRun->taskDefinition->name}");
        $this->line("Runner: {$taskRun->taskDefinition->task_runner_name}");
        $this->newLine();

        // Show task definition prompt (user's domain-specific prompt)
        $this->info("=== User's Task Definition Prompt ===");
        $this->line($taskRun->taskDefinition->prompt ?: '(none)');
        $this->newLine();

        // Show input artifacts
        $this->info("=== Input Artifacts ===");
        $inputArtifacts = $taskRun->inputArtifacts()->orderBy('position')->get();
        $this->line("Total: {$inputArtifacts->count()} files");

        // Show sample pages
        foreach ($inputArtifacts->take(5) as $artifact) {
            $storedFile = $artifact->storedFiles->first();
            $pageNumber = $storedFile?->page_number ?? $artifact->position;
            $this->line("  Page $pageNumber: {$artifact->name}");
        }
        if ($inputArtifacts->count() > 5) {
            $this->line("  ... and " . ($inputArtifacts->count() - 5) . " more");
        }
        $this->newLine();

        // Show window processes
        $this->info("=== Window Comparison Processes ===");
        $windowProcesses = $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW)
            ->get();

        $this->line("Total windows: {$windowProcesses->count()}");

        foreach ($windowProcesses as $window) {
            $start = $window->meta['window_start'] ?? '?';
            $end = $window->meta['window_end'] ?? '?';
            $artifact = $window->outputArtifacts->first();

            if ($artifact && $artifact->json_content) {
                $groups = $artifact->json_content['groups'] ?? [];
                $this->line("  Window $start-$end: " . count($groups) . " groups");

                foreach ($groups as $group) {
                    $name = $group['name'] ?? 'Unknown';
                    $fileCount = count($group['files'] ?? []);
                    $this->line("    - $name: $fileCount files");

                    // Show confidence scores
                    if ($this->option('verbose')) {
                        foreach ($group['files'] as $file) {
                            $page = $file['page_number'] ?? '?';
                            $conf = $file['confidence'] ?? '?';
                            $this->line("      Page $page: confidence $conf");
                        }
                    }
                }
            }
        }
        $this->newLine();

        // Show merge process
        $this->info("=== Merge Process ===");
        $mergeProcess = $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_MERGE)
            ->first();

        if ($mergeProcess) {
            $this->line("Status: {$mergeProcess->status}");

            // Check for low confidence files
            $lowConfFiles = $mergeProcess->meta['low_confidence_files'] ?? [];
            if (!empty($lowConfFiles)) {
                $this->warn("Low confidence files detected: " . count($lowConfFiles));
                foreach ($lowConfFiles as $fileData) {
                    $page = $fileData['page_number'] ?? '?';
                    $bestGroup = $fileData['best_assignment']['group_name'] ?? 'Unknown';
                    $conf = $fileData['best_assignment']['confidence'] ?? '?';
                    $this->line("  Page $page -> $bestGroup (confidence $conf)");
                }
            } else {
                $this->line("No low confidence files");
            }

            // Check for null group files needing LLM resolution
            $nullGroupFiles = $mergeProcess->meta['null_groups_needing_llm'] ?? [];
            if (!empty($nullGroupFiles)) {
                $this->warn("Null group files needing LLM resolution: " . count($nullGroupFiles));
                foreach ($nullGroupFiles as $fileData) {
                    $page = $fileData['page_number'] ?? '?';
                    $prevGroup = $fileData['previous_group'] ?? 'Unknown';
                    $nextGroup = $fileData['next_group'] ?? 'Unknown';
                    $this->line("  Page $page: between '$prevGroup' and '$nextGroup'");
                }
            } else {
                $this->line("No null group files needing resolution");
            }
        }
        $this->newLine();

        // Show low-confidence resolution process if exists
        $resolutionProcess = $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_LOW_CONFIDENCE_RESOLUTION)
            ->first();

        if ($resolutionProcess) {
            $this->info("=== Low Confidence Resolution Process ===");
            $this->line("Status: {$resolutionProcess->status}");
            $this->newLine();
        }

        // Show null group resolution process if exists
        $nullGroupResolutionProcess = $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_NULL_GROUP_RESOLUTION)
            ->first();

        if ($nullGroupResolutionProcess) {
            $this->info("=== Null Group Resolution Process ===");
            $this->line("Status: {$nullGroupResolutionProcess->status}");
            $this->newLine();
        }

        // Show group absorption summary
        $this->info("=== Group Analysis ===");
        $outputArtifacts = $taskRun->outputArtifacts()->get();
        $this->line("Total final groups: {$outputArtifacts->count()}");

        // Show confidence distribution
        foreach ($outputArtifacts as $artifact) {
            $groupName = $artifact->meta['group_name'] ?? 'Unknown';
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
        $this->info("=== Final Output Groups ===");

        foreach ($outputArtifacts as $artifact) {
            $groupName = $artifact->meta['group_name'] ?? 'Unknown';
            $fileCount = $artifact->meta['file_count'] ?? 0;
            $description = $artifact->meta['description'] ?? '';
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
                $children = $artifact->children()->orderBy('position')->get();
                $pageNumbers = [];
                foreach ($children as $child) {
                    $storedFile = $child->storedFiles->first();
                    $pageNumbers[] = $storedFile?->page_number ?? $child->position;
                }
                $this->line("   Pages: " . implode(', ', $pageNumbers));
            }

            $this->newLine();
        }

        // Show agent thread messages if requested
        if ($this->option('messages')) {
            $this->info("=== Agent Thread Messages ===");

            // Show first window's thread as example
            $firstWindow = $windowProcesses->first();
            if ($firstWindow && $firstWindow->agentThread) {
                $thread = $firstWindow->agentThread;
                $this->line("Window {$firstWindow->meta['window_start']}-{$firstWindow->meta['window_end']} thread:");
                $this->newLine();

                foreach ($thread->messages()->orderBy('created_at')->get() as $message) {
                    $role = $message->role;
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
}
