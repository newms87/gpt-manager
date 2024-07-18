<?php

namespace App\WorkflowTools;

use App\Models\Workflow\WorkflowJob;
use App\Models\Workflow\WorkflowJobRun;
use App\Models\Workflow\WorkflowTask;

abstract class WorkflowTool
{
    /* Should be initialized by the child WorkflowTool */
    public static string $toolName;

    abstract public function resolveDependencyArtifacts(WorkflowJob $workflowJob, array $prerequisiteJobRuns = []): array;

    abstract public function assignTasks(WorkflowJobRun $workflowJobRun, array $dependencyArtifacts = []): void;

    /**
     * Resolve the dependency artifacts and assign tasks to the workflow job run
     */
    public function resolveAndAssignTasks(WorkflowJobRun $workflowJobRun, array $prerequisiteJobRuns = []): void
    {
        $artifactGroupTuples = $this->resolveDependencyArtifacts($workflowJobRun->workflowJob, $prerequisiteJobRuns);

        $this->assignTasks($workflowJobRun, $artifactGroupTuples);
    }

    abstract public function runTask(WorkflowTask $workflowTask): void;

    public function __toString()
    {
        return "Workflow Tool: " . static::$toolName;
    }
}
