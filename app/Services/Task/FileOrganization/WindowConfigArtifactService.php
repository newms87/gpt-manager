<?php

namespace App\Services\Task\FileOrganization;

use App\Models\Task\Artifact;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Traits\HasDebugLogging;

/**
 * Manages window configuration artifacts for file organization processes.
 *
 * Per project principle: TaskProcess.meta should only be used for data the running process sets itself.
 * Input artifacts should be used for pre-execution configuration data.
 */
class WindowConfigArtifactService
{
    use HasDebugLogging;

    public const string ARTIFACT_NAME = 'Window Config';

    /**
     * Create a window config artifact with the window configuration.
     */
    public function createWindowConfigArtifact(TaskRun $taskRun, array $windowConfig): Artifact
    {
        return Artifact::create([
            'team_id'      => $taskRun->team_id,
            'task_run_id'  => $taskRun->id,
            'name'         => self::ARTIFACT_NAME,
            'json_content' => $windowConfig,
        ]);
    }

    /**
     * Get window configuration from a task process's input artifacts.
     */
    public function getWindowConfigFromProcess(TaskProcess $taskProcess): ?array
    {
        $configArtifact = $taskProcess->inputArtifacts()
            ->where('name', self::ARTIFACT_NAME)
            ->first();

        return $configArtifact?->json_content;
    }

    /**
     * Get window files from a task process's config artifact.
     * Convenience method that extracts just the window_files array.
     */
    public function getWindowFiles(TaskProcess $taskProcess): ?array
    {
        $config = $this->getWindowConfigFromProcess($taskProcess);

        return $config['window_files'] ?? null;
    }
}
