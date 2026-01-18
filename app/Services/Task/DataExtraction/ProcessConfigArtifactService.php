<?php

namespace App\Services\Task\DataExtraction;

use App\Models\Task\Artifact;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use Exception;

/**
 * Creates and retrieves configuration artifacts for task processes.
 *
 * Per project principle: TaskProcess.meta should only be used for data the running process sets itself.
 * Input artifacts should be used for pre-execution configuration data.
 *
 * This service manages "Process Config" artifacts that store configuration like:
 * - level (extraction level number)
 * - identity_group (for EXTRACT_IDENTITY operations)
 * - extraction_group (for EXTRACT_REMAINING operations)
 * - parent_object_ids (parent objects from previous level)
 * - object_id (resolved object ID for EXTRACT_REMAINING)
 * - search_mode (skim or exhaustive)
 */
class ProcessConfigArtifactService
{
    public const string CONFIG_ARTIFACT_NAME = 'Process Config';

    /**
     * Create a configuration artifact for a task process.
     *
     * @param  array  $config  Configuration data (level, identity_group, search_mode, etc.)
     */
    public function createConfigArtifact(TaskRun $taskRun, array $config): Artifact
    {
        return Artifact::create([
            'team_id'      => $taskRun->team_id,
            'task_run_id'  => $taskRun->id,
            'name'         => self::CONFIG_ARTIFACT_NAME,
            'meta'         => $config,
        ]);
    }

    /**
     * Get configuration from a task process's input artifacts.
     *
     * Looks for an artifact named "Process Config" and returns its meta field.
     *
     * @throws Exception If no config artifact is found
     */
    public function getConfigFromProcess(TaskProcess $taskProcess): array
    {
        $configArtifact = $taskProcess->inputArtifacts()
            ->where('name', self::CONFIG_ARTIFACT_NAME)
            ->first();

        if (!$configArtifact) {
            throw new Exception(
                "No Process Config artifact found for TaskProcess {$taskProcess->id}. " .
                'This indicates a bug - extraction processes must have a config artifact.'
            );
        }

        return $configArtifact->meta ?? [];
    }

    /**
     * Get a specific config value from a task process.
     *
     * @param  mixed  $default  Default value if key is not found
     *
     * @throws Exception If no config artifact is found
     */
    public function getConfigValue(TaskProcess $taskProcess, string $key, mixed $default = null): mixed
    {
        $config = $this->getConfigFromProcess($taskProcess);

        return $config[$key] ?? $default;
    }

    /**
     * Check if a task process has a config artifact.
     */
    public function hasConfigArtifact(TaskProcess $taskProcess): bool
    {
        return $taskProcess->inputArtifacts()
            ->where('name', self::CONFIG_ARTIFACT_NAME)
            ->exists();
    }
}
