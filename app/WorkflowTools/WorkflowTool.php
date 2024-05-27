<?php

namespace App\WorkflowTools;

use App\Models\Workflow\WorkflowJobRun;
use App\Models\Workflow\WorkflowTask;
use Illuminate\Support\Facades\Log;

abstract class WorkflowTool
{
    abstract public function runTask(WorkflowTask $workflowTask): void;

    public function assignTasks(WorkflowJobRun $workflowJobRun, array $dependsOnJobs): void
    {
        // Resolve the unique task groups to create a task for each group.
        // If there are no task groups, just setup a default task group
        $taskGroups  = $this->getTaskGroups($dependsOnJobs) ?: [''];
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
}
