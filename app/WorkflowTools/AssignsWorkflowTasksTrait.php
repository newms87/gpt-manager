<?php

namespace App\WorkflowTools;

use App\Models\Agent\Thread;
use App\Models\Workflow\Artifact;
use App\Models\Workflow\WorkflowInput;
use App\Models\Workflow\WorkflowJobRun;
use App\Models\Workflow\WorkflowTask;
use App\Repositories\ThreadRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

trait AssignsWorkflowTasksTrait
{
    /**
     * Assign tasks to the workflow job run based on the assignments and the dependency artifacts
     * (ie: tuples of WorkflowJobRun Artifacts that should be passed to each assignment)
     */
    public function assignTasks(WorkflowJobRun $workflowJobRun, array|Collection $dependencyArtifacts = []): void
    {
        $assignments = $workflowJobRun->workflowJob->workflowAssignments()->get();

        if ($assignments->isEmpty()) {
            Log::debug("$workflowJobRun the workflow job has no assignments, skipping task creation");

            return;
        }

        $this->assignTasksByDependencyArtifacts($workflowJobRun, $assignments, $dependencyArtifacts);
    }

    /**
     * Assign tasks to each assignment based on the artifacts in each group of dependencyArtifacts.
     * Each group is a tuple that represents a unique set of artifacts to be used in a single task
     */
    public function assignTasksByDependencyArtifacts(WorkflowJobRun $workflowJobRun, Collection $assignments, array $dependencyArtifacts): void
    {
        foreach($assignments as $assignment) {
            foreach($dependencyArtifacts as $groupName => $artifactTuple) {
                $task = $workflowJobRun->tasks()->create([
                    'user_id'                => user()->id,
                    'workflow_job_id'        => $workflowJobRun->workflow_job_id,
                    'workflow_assignment_id' => $assignment->id,
                    'group'                  => $groupName,
                    'status'                 => WorkflowTask::STATUS_PENDING,
                ]);

                // TODO: Setup task thread based on artifact tuple

                Log::debug("$workflowJobRun created $task for $assignment with group $groupName");
            }
        }
    }

    /**
     * Create a thread for the task and add messages from the workflow input or dependencies
     *
     * @param WorkflowTask $workflowTask
     * @return Thread
     */
    protected function setupTaskThread(WorkflowTask $workflowTask): Thread
    {
        $workflowJobRun = $workflowTask->workflowJobRun;
        $workflowRun    = $workflowJobRun->workflowRun;
        $assignment     = $workflowTask->workflowAssignment;
        $workflowJob    = $workflowTask->workflowJob;

        $threadName = $workflowTask->workflowJob->name . " ($workflowTask->id) [group: " . ($workflowTask->group ?: 'default') . "] by {$assignment->agent->name}";
        $thread     = app(ThreadRepository::class)->create($assignment->agent, $threadName);

        Log::debug("$workflowTask created $thread");

        if ($workflowJob->use_input) {
            // If we have no dependencies, then we want to use the workflow input
            $this->addThreadMessageFromWorkflowInput($thread, $workflowRun->workflowInput);
        }

        // If we have dependencies, we want to use the artifacts from the dependencies
        if ($workflowJob->dependencies->isNotEmpty()) {
            foreach($workflowJob->dependencies as $dependency) {
                $dependsOnWorkflowJobRun = $workflowRun->workflowJobRuns->where('workflow_job_id', $dependency->depends_on_workflow_job_id)->first();
                $this->addThreadMessagesFromArtifacts($thread, $dependsOnWorkflowJobRun->artifacts, $dependency->group_by, $workflowTask->group);
            }
        }

        $workflowTask->thread()->associate($thread)->save();

        return $thread;
    }

    /**
     * Add messages from artifacts to a thread
     *
     * @param Thread                $thread
     * @param Artifact[]|Collection $artifacts
     * @param                       $groupBy
     * @param                       $group
     * @return void
     */
    public function addThreadMessagesFromArtifacts(Thread $thread, $artifacts, $groupBy, $group): void
    {
        foreach($artifacts as $artifact) {
            if ($groupBy) {
                $groupedContent = $artifact->groupContentBy($groupBy);
                if (!$groupedContent) {
                    Log::debug("$thread skipped $artifact: missing group by field $groupBy");
                    continue;
                }
                $content = $groupedContent[$group] ?? null;
                if (!$content) {
                    Log::debug("$thread skipped $artifact: grouped content does not have any content in group $group");
                    continue;
                }
                app(ThreadRepository::class)->addMessageToThread($thread, $content);
                Log::debug("$thread added message for group $group from $artifact (used group by $groupBy)");
            } else {
                app(ThreadRepository::class)->addMessageToThread($thread, $artifact->content);
                Log::debug("$thread added message for $artifact");
            }
        }
    }

    /**
     * Create a message and append it to the thread based on the Workflow Input content + files
     *
     * @param Thread        $thread
     * @param WorkflowInput $workflowInput
     * @return void
     */
    public function addThreadMessageFromWorkflowInput(Thread $thread, WorkflowInput $workflowInput): void
    {
        $content = $workflowInput->content;
        $fileIds = $workflowInput->storedFiles->pluck('id')->toArray();
        app(ThreadRepository::class)->addMessageToThread($thread, $content, $fileIds);
        Log::debug("$thread added $workflowInput");
    }
}
