<?php

namespace App\Console\Commands;

use App\Models\Task\Artifact;
use App\Models\Task\TaskProcess;
use Illuminate\Console\Command;

class InvestigateTaskProcess extends Command
{
    protected $signature = 'app:investigate-task-process {id} {--artifact=} {--windows} {--search=} {--files} {--page=} {--boundary}';

    protected $description = 'Investigate task process for debugging';

    public function handle()
    {
        $id = $this->argument('id');

        // If --artifact option specified, show that artifact
        if ($artifactId = $this->option('artifact')) {
            $this->showArtifact($artifactId);

            return;
        }

        // If --windows option, show all window processes for the task run
        if ($this->option('windows')) {
            $this->showWindows($id);

            return;
        }

        // If --search option, search for a term in artifact json
        if ($search = $this->option('search')) {
            $this->searchArtifacts($id, $search);

            return;
        }

        // If --files option, show stored files for artifacts
        if ($this->option('files')) {
            $this->showArtifactFiles($id);

            return;
        }

        // If --page option, show page assignment history
        if ($page = $this->option('page')) {
            $this->showPageHistory($id, (int)$page);

            return;
        }

        // If --boundary option, find boundary conflicts
        if ($this->option('boundary')) {
            $this->findBoundaryConflicts($id);

            return;
        }

        $tp = TaskProcess::find($id);

        if (!$tp) {
            $this->error("Task process {$id} not found");

            return;
        }

        $this->info("=== Task Process {$id} ===");
        $this->info("Operation: {$tp->operation}");
        $this->info("Status: {$tp->status}");
        $this->info("Name: {$tp->name}");
        $this->info("Task Run ID: {$tp->task_run_id}");
        $this->info('Agent Thread ID: ' . ($tp->agent_thread_id ?? 'none'));

        // Show meta
        $this->info("\n=== Meta ===");
        $this->line(json_encode($tp->meta ?? [], JSON_PRETTY_PRINT));

        // Show output artifacts summary
        $this->info("\n=== Output Artifacts ===");
        foreach ($tp->outputArtifacts as $a) {
            $this->info("\nArtifact {$a->id}: {$a->name}");
            $json = $a->json_content;
            $this->line('  JSON keys: ' . implode(', ', array_keys($json ?? [])));

            // Try different structures
            if (isset($json['groups'])) {
                $this->line('  Groups: ' . count($json['groups']));
            }
            if (isset($json['files'])) {
                $this->line('  Files: ' . count($json['files']));
            }
            // Show first 500 chars of json
            $this->line('  JSON preview: ' . substr(json_encode($json), 0, 500));
        }

        // Show input artifacts count
        $this->info("\n=== Input Artifacts ===");
        $this->line('Count: ' . $tp->inputArtifacts()->count());

        // Show related processes for this task run
        $this->info("\n=== Related Processes (Task Run {$tp->task_run_id}) ===");
        $processes = TaskProcess::where('task_run_id', $tp->task_run_id)->get();
        foreach ($processes as $p) {
            $this->line("  {$p->id}: {$p->operation} - {$p->status}");
        }
    }

    protected function showArtifact($id)
    {
        $a = Artifact::find($id);
        if (!$a) {
            $this->error("Artifact {$id} not found");

            return;
        }

        $this->info("=== Artifact {$id} ===");
        $this->info("Name: {$a->name}");
        $this->info("Type: {$a->type}");
        $this->line("\n=== JSON Content ===");
        $this->line(json_encode($a->json_content, JSON_PRETTY_PRINT));

        // Show stored files
        $this->info("\n=== Stored Files ===");
        $storedFiles = $a->storedFiles()->orderBy('page_number')->get();
        $this->line('Count: ' . $storedFiles->count());
        foreach ($storedFiles as $sf) {
            $this->line("  Page {$sf->page_number}: {$sf->filename}");
        }
    }

    protected function showWindows($taskRunId)
    {
        // Get task run from the process
        $tp = TaskProcess::find($taskRunId);
        if (!$tp) {
            $this->error("Task process {$taskRunId} not found");

            return;
        }

        $taskRun = $tp->task_run_id;
        $this->info("=== Window Processes for Task Run {$taskRun} ===");

        $windows = TaskProcess::where('task_run_id', $taskRun)
            ->where('operation', 'Comparison Window')
            ->get();

        foreach ($windows as $w) {
            $this->info("\nWindow Process {$w->id}:");
            $this->line("  Status: {$w->status}");
            $this->line('  Window Start: ' . ($w->meta['window_start'] ?? 'n/a'));
            $this->line('  Window End: ' . ($w->meta['window_end'] ?? 'n/a'));

            // Show output artifact
            $artifacts = $w->outputArtifacts;
            foreach ($artifacts as $a) {
                $this->line("  Output Artifact {$a->id}");
                $json = $a->json_content;
                if (isset($json['groups'])) {
                    foreach ($json['groups'] as $g) {
                        $name  = $g['group_name'] ?? $g['name'] ?? 'unnamed';
                        $conf  = $g['confidence'] ?? 'n/a';
                        $pages = $g['pages']      ?? $g['files'] ?? [];
                        if (is_array($pages) && isset($pages[0]['page_number'])) {
                            $pages = array_column($pages, 'page_number');
                        }
                        $this->line("    Group: {$name} (conf:{$conf}) pages:" . json_encode($pages));
                    }
                }
            }
        }
    }

    protected function searchArtifacts($taskProcessId, $search)
    {
        $tp = TaskProcess::find($taskProcessId);
        if (!$tp) {
            $this->error("Task process {$taskProcessId} not found");

            return;
        }

        $this->info("=== Searching '{$search}' in Task Run {$tp->task_run_id} ===");

        // Search in all task processes for this task run
        $processes = TaskProcess::where('task_run_id', $tp->task_run_id)->get();

        foreach ($processes as $p) {
            foreach ($p->outputArtifacts as $a) {
                $jsonStr = json_encode($a->json_content);
                if (stripos($jsonStr, $search) !== false) {
                    $this->info("\nFound in Artifact {$a->id} (Process {$p->id} - {$p->operation}):");
                    $this->line("  Artifact Name: {$a->name}");

                    // Show the groups containing the search term
                    $json = $a->json_content;
                    if (isset($json['groups'])) {
                        foreach ($json['groups'] as $g) {
                            $groupJson = json_encode($g);
                            if (stripos($groupJson, $search) !== false) {
                                $this->line('  Matching Group: ' . json_encode($g, JSON_PRETTY_PRINT));
                            }
                        }
                    }
                }
            }
        }
    }

    protected function showArtifactFiles($taskProcessId)
    {
        $tp = TaskProcess::find($taskProcessId);
        if (!$tp) {
            $this->error("Task process {$taskProcessId} not found");

            return;
        }

        $this->info("=== Output Artifacts with Stored Files (Task Process {$taskProcessId}) ===");

        foreach ($tp->outputArtifacts as $a) {
            $this->info("\nArtifact {$a->id}: {$a->name}");
            $storedFiles = $a->storedFiles()->orderBy('page_number')->get();
            $this->line('  Stored Files Count: ' . $storedFiles->count());
            $pages = $storedFiles->pluck('page_number')->toArray();
            $this->line('  Pages: ' . json_encode($pages));
        }
    }

    protected function showPageHistory($taskProcessId, int $targetPage)
    {
        $tp = TaskProcess::find($taskProcessId);
        if (!$tp) {
            $this->error("Task process {$taskProcessId} not found");

            return;
        }

        $this->info("=== Page {$targetPage} Assignment History (Task Run {$tp->task_run_id}) ===");

        // Get all window processes for this task run
        $processes = TaskProcess::where('task_run_id', $tp->task_run_id)
            ->where('operation', 'Comparison Window')
            ->get();

        foreach ($processes as $p) {
            foreach ($p->outputArtifacts as $a) {
                $json = $a->json_content;
                if (!isset($json['groups'])) {
                    continue;
                }

                foreach ($json['groups'] as $g) {
                    $groupName = $g['name']  ?? $g['group_name'] ?? 'unnamed';
                    $files     = $g['files'] ?? [];

                    foreach ($files as $f) {
                        $pageNum = is_array($f) ? ($f['page_number'] ?? null) : $f;
                        if ($pageNum == $targetPage) {
                            $conf = is_array($f) ? ($f['confidence'] ?? 'n/a') : 'n/a';
                            $expl = is_array($f) ? ($f['explanation'] ?? '') : '';
                            $this->info("\nWindow {$p->id} (Artifact {$a->id}):");
                            $this->line("  Group: {$groupName}");
                            $this->line("  Confidence: {$conf}");
                            $this->line('  Explanation: ' . substr($expl, 0, 200));
                            $this->line('  Window Range: ' . ($a->meta['window_start'] ?? 'n/a') . '-' . ($a->meta['window_end'] ?? 'n/a'));
                        }
                    }
                }
            }
        }
    }

    protected function findBoundaryConflicts($taskProcessId)
    {
        $tp = TaskProcess::find($taskProcessId);
        if (!$tp) {
            $this->error("Task process {$taskProcessId} not found");

            return;
        }

        $taskRun     = $tp->taskRun;
        $fileToGroup = $this->buildFileToGroupMapping($taskRun);

        $this->info('=== Finding Boundary Conflicts Between ME PT and Mountain View ===');

        // Find files that have assignments from BOTH groups
        foreach ($fileToGroup as $fileId => $data) {
            $allExplanations = $data['all_explanations'] ?? [];
            $groups          = array_unique(array_column($allExplanations, 'group_name'));

            // Check if this file has both ME Physical Therapy and Mountain View Pain Specialists
            $hasMEPT = in_array('ME Physical Therapy', $groups);
            $hasMVPS = in_array('Mountain View Pain Specialists', $groups);

            if ($hasMEPT && $hasMVPS) {
                $this->info("\n** BOUNDARY FILE: Page {$data['page_number']} (File {$fileId}) **");
                $this->line("  Current Assignment: {$data['group_name']} (conf {$data['confidence']})");
                $this->line('  All Assignments:');

                foreach ($allExplanations as $exp) {
                    $marker = ($exp['group_name'] === $data['group_name']) ? ' ← WINNER' : '';
                    $this->line("    - {$exp['group_name']} (conf {$exp['confidence']}) from window {$exp['window_id']}{$marker}");
                }
            }
        }
    }

    protected function buildFileToGroupMapping($taskRun): array
    {
        // Get all window processes
        $windowProcesses = $taskRun->taskProcesses()
            ->where('operation', 'Comparison Window')
            ->get();

        $windowArtifacts = collect();
        foreach ($windowProcesses as $process) {
            $windowArtifacts = $windowArtifacts->merge($process->outputArtifacts);
        }

        // Extract windows
        $windows = [];
        foreach ($windowArtifacts as $artifact) {
            $jsonContent = $artifact->json_content;
            if (!$jsonContent || !isset($jsonContent['groups'])) {
                continue;
            }

            $windowStart = $artifact->meta['window_start'] ?? null;
            $windowEnd   = $artifact->meta['window_end']   ?? null;
            $windowFiles = $artifact->meta['window_files'] ?? null;

            if ($windowStart === null || $windowEnd === null) {
                continue;
            }

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
        }

        usort($windows, fn($a, $b) => $a['window_start'] <=> $b['window_start']);

        // Build file-to-group mapping
        $fileToGroup = [];

        foreach ($windows as $window) {
            $pageNumberToFileId = $window['page_number_to_file_map'] ?? [];

            foreach ($window['groups'] as $group) {
                $groupName   = $group['name']        ?? null;
                $description = $group['description'] ?? '';
                $files       = $group['files']       ?? [];

                if ($groupName === null) {
                    continue;
                }

                foreach ($files as $fileData) {
                    if (is_int($fileData)) {
                        $pageNumber  = $fileData;
                        $confidence  = 3;
                        $explanation = '';
                    } else {
                        $pageNumber  = $fileData['page_number'] ?? null;
                        $confidence  = $fileData['confidence']  ?? 3;
                        $explanation = $fileData['explanation'] ?? '';
                    }

                    $fileId = $pageNumberToFileId[$pageNumber] ?? null;
                    if (!$fileId || $pageNumber === null) {
                        continue;
                    }

                    // Initialize if first time
                    if (!isset($fileToGroup[$fileId])) {
                        $fileToGroup[$fileId] = [
                            'group_name'       => $groupName,
                            'description'      => $description,
                            'page_number'      => $pageNumber,
                            'confidence'       => $confidence,
                            'all_explanations' => [],
                        ];
                    }

                    // Track ALL explanations
                    $fileToGroup[$fileId]['all_explanations'][] = [
                        'group_name'  => $groupName,
                        'confidence'  => $confidence,
                        'explanation' => $explanation,
                        'window_id'   => $window['artifact_id'],
                    ];

                    // Check if higher confidence
                    if ($confidence > $fileToGroup[$fileId]['confidence']) {
                        $fileToGroup[$fileId]['group_name']  = $groupName;
                        $fileToGroup[$fileId]['description'] = $description;
                        $fileToGroup[$fileId]['confidence']  = $confidence;
                    }
                }
            }
        }

        return $fileToGroup;
    }
}
