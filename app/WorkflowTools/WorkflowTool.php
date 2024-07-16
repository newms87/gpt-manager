<?php

namespace App\WorkflowTools;

use App\Models\Workflow\WorkflowJobRun;
use App\Models\Workflow\WorkflowTask;
use Illuminate\Support\Facades\Log;

abstract class WorkflowTool
{
    /* Should be initialized by the child WorkflowTool */
    public static string $toolName;

    abstract public function runTask(WorkflowTask $workflowTask): void;

    public function assignTasks(WorkflowJobRun $workflowJobRun, array $prerequisiteJobRuns = []): void
    {
        foreach($workflowJobRun->workflowJob->dependencies as $dependency) {
            $prerequisiteJob = $prerequisiteJobRuns[$dependency->depends_on_workflow_job_id] ?? null;
            if (!$prerequisiteJob) {
                Log::debug("$workflowJobRun is missing the dependency $dependency, skipping task creation");

                return;
            }

            // TODO: 1. For each dependency, loop through all the prerequisite job artifacts to extract the included data
            //       2. From the set of included artifact data, create the group by key for each artifact
            //       3. Append the artifact to the list matching the group by key index (ie: $taskGroup[$groupKey][] = $artifact)
            //       4. Take the cross product of all the group by keys to create the set of tasks to be created
            //       5. The task group will be the concatenation of all the group by keys for each tuple in the cross product


            $data = $this->resolveArtifactData($prerequisiteJob, $dependency->include_fields);

            $tasksByDependency[$dependency->depends_on_workflow_job_id] = [
                'jobRun'  => $prerequisiteJob,
                'groupBy' => $dependency->group_by,
            ];
        }

        // Resolve the unique task groups to create a task for each group.
        // If there are no task groups, just setup a default task group
        $taskGroups = $this->getTaskGroups($dependsOnJobs) ?: [''];

        $assignments = $workflowJobRun->workflowJob->workflowAssignments()->get();

        if ($assignments->isEmpty()) {
            Log::debug("$workflowJobRun has no assignments, skipping task creation");

            return;
        }

        foreach($assignments as $assignment) {
            foreach($taskGroups as $taskGroup) {
                $task = $workflowJobRun->tasks()->create([
                    'user_id'                => user()->id,
                    'workflow_job_id'        => $workflowJobRun->workflow_job_id,
                    'workflow_assignment_id' => $assignment->id,
                    'group'                  => $taskGroup,
                    'status'                 => WorkflowTask::STATUS_PENDING,
                ]);

                Log::debug("$workflowJobRun created $task for $assignment with group $taskGroup");
            }
        }
    }

    public function resolveArtifactData(WorkflowJobRun $dependsOnJob, array $includeFields): array
    {
        $artifacts = $dependsOnJob->artifacts()->get();
        $data      = [];

        foreach($artifacts as $artifact) {
            $data += $artifact->resolveFields($includeFields);
        }

        return $data;
    }

    /**
     * Resolves all the groupings of data from the artifacts produced by the WorkflowJobRun dependencies.
     * NOTE: if no group by is set on the WorkflowJobDependency, then it is skipped.
     * returning an empty set of task groups means the entire artifact should be used for all tasks, and only 1 task
     * per assignment should be created.
     * @param array $dependsOnJobs
     * @return array
     */
    protected function getTaskGroups(array $dependsOnJobs): array
    {
        $taskGroups = [];

        foreach($dependsOnJobs as $dependsOnJob) {
            // If the dependency has no grouping set, then skip it
            if (empty($dependsOnJob['groupBy'])) {
                continue;
            }

            /** @var WorkflowJobRun $dependsOnJobRun */
            $dependsOnJobRun = $dependsOnJob['jobRun'];
            $artifacts       = $dependsOnJobRun->artifacts()->get();
            foreach($artifacts as $artifact) {
                $artifactGroups = $artifact->groupContentBy($dependsOnJob['groupBy']);

                foreach(array_keys($artifactGroups) as $key) {
                    $taskGroups[$key] = $key;
                }
            }
        }

        return $taskGroups;
    }

    public function __toString()
    {
        return "Workflow Tool: " . static::$toolName;
    }
}
