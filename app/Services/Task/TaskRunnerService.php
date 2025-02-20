<?php

namespace App\Services\Task;

use App\Jobs\ExecuteTaskProcessJob;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskInput;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\Task\WorkflowStatesContract;
use App\Models\Workflow\Artifact;
use App\Traits\HasDebugLogging;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Helpers\LockHelper;
use Newms87\Danx\Models\Job\JobDispatch;
use Throwable;

class TaskRunnerService
{
    use HasDebugLogging;

    /**
     * Start a task run for the task definition. This will create a task run and dispatch the task processes
     */
    public static function start(TaskDefinition $taskDefinition, TaskInput $taskInput = null, $artifacts = []): TaskRun
    {
        static::log("Starting for $taskDefinition");

        $taskRun = static::prepareTaskRun($taskDefinition, $taskInput, $artifacts);

        $taskRun->started_at = now();
        $taskRun->save();

        // Dispatch the take processes to begin the task run
        static::continue($taskRun);

        return $taskRun;
    }

    /**
     * Continue the task run by executing the next set of processes.
     * NOTE: This will not dispatch the next processes if the task run is stopped or failed
     */
    public static function continue(TaskRun $taskRun): void
    {
        static::log("Continuing $taskRun");

        // Always start by acquiring the lock for the task run before checking if it can continue
        // NOTE: This prevents allowing the TaskRun to continue if there was a race condition on failing/stopping the TaskRun
        LockHelper::acquire($taskRun);

        if (!$taskRun->canContinue()) {
            static::log("TaskRun is $taskRun->status. Skipping execution");

            return;
        }

        if ($taskRun->taskProcesses->isEmpty()) {
            static::log("No task processes found. Skipping execution");

            return;
        }

        try {
            if ($taskRun->isPending()) {
                static::log("TaskRun was Pending, starting now...");
                // Only start the task run if it is pending
                $taskRun->started_at = now();
                $taskRun->save();
            }

            foreach($taskRun->taskProcesses as $taskProcess) {
                if ($taskProcess->isCompleted()) {
                    static::log("TaskProcess already Completed. Skipping dispatch: $taskProcess");
                    continue;
                }

                // Only dispatch a task process if it is pending
                if ($taskProcess->isPending()) {
                    static::dispatchProcess($taskProcess);
                } elseif ($taskProcess->isPastTimeout()) {
                    static::log("TaskProcess $taskProcess timed out, stopping TaskRun $taskRun");
                    $taskProcess->timeout_at = now();
                    $taskProcess->save();
                }
            }
        } finally {
            LockHelper::release($taskRun);
        }
    }

    /**
     * Resume the task run. This will resume all task processes that were stopped
     */
    public static function resume(TaskRun $taskRun): void
    {
        static::log("Resuming $taskRun");

        LockHelper::acquire($taskRun);

        try {
            if (!$taskRun->isStopped() && !$taskRun->isPending()) {
                static::log("TaskRun is not stopped, skipping resume");

                return;
            }

            $taskRun->stopped_at = null;
            $taskRun->save();

            foreach($taskRun->taskProcesses as $taskProcess) {
                if ($taskProcess->isStopped()) {
                    $taskProcess->stopped_at = null;
                    $taskProcess->save();
                }
            }
        } finally {
            LockHelper::release($taskRun);
        }

        static::continue($taskRun);
    }

    /**
     * Stop the task run. This will stop all task processes and prevent any further execution
     */
    public static function stop(TaskRun $taskRun): void
    {
        static::log("Stopping $taskRun");

        LockHelper::acquire($taskRun);

        try {
            if ($taskRun->isStopped()) {
                static::log("TaskRun is already stopped");

                return;
            }

            $taskRun->stopped_at = now();
            $taskRun->save();

            foreach($taskRun->taskProcesses as $taskProcess) {
                if ($taskProcess->isStarted() || $taskProcess->isDispatched()) {
                    $taskProcess->status = WorkflowStatesContract::STATUS_STOPPED;
                    $taskProcess->save();
                }
            }
        } finally {
            LockHelper::release($taskRun);
        }
    }

    /**
     * Resume the task process. This will resume the task process if it was stopped
     */
    public static function resumeProcess(TaskProcess $taskProcess): void
    {
        static::log("Resuming $taskProcess");

        LockHelper::acquire($taskProcess);

        try {
            if (!$taskProcess->canResume()) {
                static::log("TaskProcess is not in a resumable state, skipping resume");

                return;
            }

            $taskProcess->stopped_at = null;
            $taskProcess->failed_at  = null;
            $taskProcess->timeout_at = null;
            // NOTE: we must reset the started_at and completed_at flag so the task process can be re-run
            $taskProcess->started_at   = null;
            $taskProcess->completed_at = null;
            $taskProcess->save();
        } finally {
            LockHelper::release($taskProcess);
        }

        static::dispatchProcess($taskProcess);
    }

    /**
     * Stop the task process. This will prevent the task process from executing further
     */
    public static function stopProcess(TaskProcess $taskProcess): void
    {
        static::log("Stopping $taskProcess");

        LockHelper::acquire($taskProcess);

        try {
            if ($taskProcess->isStopped()) {
                static::log("TaskProcess is already stopped");

                return;
            }

            $taskProcess->stopped_at = now();
            $taskProcess->save();
        } finally {
            LockHelper::release($taskProcess);
        }
    }

    /**
     * Dispatch a task process to be executed by the job queue
     */
    public static function dispatchProcess(TaskProcess $taskProcess): ?JobDispatch
    {
        static::log("Dispatching: $taskProcess");

        // associate job dispatch before dispatching in case of synchronous job execution
        $job = (new ExecuteTaskProcessJob($taskProcess));

        // Associate JobDispatch to TaskProcess
        $jobDispatch = $job->getJobDispatch();
        if ($jobDispatch) {
            // track the most recent dispatch for easier referencing
            $taskProcess->last_job_dispatch_id = $jobDispatch->id;
            $taskProcess->save();

            // Associate all job dispatches with the task process for logging purposes
            $taskProcess->jobDispatches()->attach($jobDispatch->id);
            $taskProcess->updateRelationCounter('jobDispatches');
        }

        // Dispatch the job
        $job->dispatch();

        return $jobDispatch;
    }

    /**
     * Run the task process. This will execute the task process and mark it as completed when finished
     */
    public static function runProcess(TaskProcess $taskProcess): void
    {
        static::log("Running: $taskProcess");

        LockHelper::acquire($taskProcess);

        try {
            if (!$taskProcess->canBeRun()) {
                static::log("TaskProcess is $taskProcess->status, skipping execution");

                return;
            }

            $taskProcess->started_at = now();
            $taskProcess->save();
        } finally {
            LockHelper::release($taskProcess);
        }

        // Run the task process
        try {
            $taskProcess->getRunner()->run();
        } catch(Throwable $throwable) {
            $taskProcess->failed_at = now();
            $taskProcess->save();
            throw $throwable;
        }
    }

    /**
     * Process the completion of a task process.
     * This will mark the task process completed and continue the task run
     */
    public static function processCompleted(TaskProcess $taskProcess): void
    {
        static::log("TaskProcess completed w/ " . $taskProcess->outputArtifacts()->count() . " artifacts: $taskProcess");

        LockHelper::acquire($taskProcess);

        try {
            $taskProcess->completed_at = now();
            $taskProcess->save();
        } finally {
            LockHelper::release($taskProcess);
        }

        // Continue the task run if there are more processes to run
        $taskRun = $taskProcess->taskRun->refresh();
        static::continue($taskRun);

        // If the task run is a part of a task workflow run and the task run is completed, then notify the task workflow run
        if ($taskRun->task_workflow_run_id && $taskRun->isCompleted()) {
            TaskWorkflowRunnerService::taskRunComplete($taskRun);
        }
    }

    /**
     * Prepare a task run for the task definition. Creates a TaskRun object w/ TaskProcess objects
     */
    public static function prepareTaskRun(TaskDefinition $taskDefinition, TaskInput $taskInput = null, $artifacts = []): TaskRun
    {
        static::log("Preparing task run for $taskDefinition");

        $artifacts = collect($artifacts);
        if ($taskInput) {
            $artifact = (new WorkflowInputToArtifactMapper)->setWorkflowInput($taskInput->workflowInput)->map();
            $artifacts->push($artifact);
        }

        $taskRun = $taskDefinition->taskRuns()->make([
            'status'        => WorkflowStatesContract::STATUS_PENDING,
            'task_input_id' => $taskInput?->id,
        ]);

        $taskRun->getRunner()->prepareRun();
        $taskRun->save();

        static::prepareTaskProcesses($taskRun, $artifacts);

        return $taskRun;
    }

    /**
     * Prepare task processes for the task run. Each process will receive its own Artifacts / Agent AgentThread
     * based on the input groups and the assigned agents for the TaskDefinition
     */
    public static function prepareTaskProcesses(TaskRun $taskRun, $artifacts = []): array
    {
        $artifacts = collect($artifacts);
        static::log("Preparing task processes for $taskRun");

        // Validate the artifacts are all Artifact instances
        foreach($artifacts as $artifact) {
            // Only accept Artifact instances here. The input should have already converted content into an Artifact
            if (!($artifact instanceof Artifact)) {
                throw new ValidationError("Invalid artifact provided: All artifacts should be an instance of Artifact: " . (is_object($artifact) ? get_class($artifact) : json_encode($artifact)));
            }
        }

        $taskProcesses = [];

        $taskDefinition   = $taskRun->taskDefinition;
        $definitionAgents = $taskDefinition->definitionAgents;

        // NOTE: If there are no agents assigned to the task definition, create an array w/ null entry as a convenience so the loop will create a single process with no agent
        if ($definitionAgents->isEmpty()) {
            $definitionAgents = [null];
        }
        
        // Prepare the artifact groups based on the task definition settings
        if ($artifacts->isNotEmpty()) {
            $artifactGroups = (new ArtifactsToGroupsMapper)
                ->groupingMode($taskDefinition->grouping_mode)
                ->splitByFile($taskDefinition->split_by_file)
                ->setGroupingKeys($taskDefinition->getGroupingKeys())
                ->map($artifacts->all());
        } else {
            $artifactGroups = ['default' => []];
        }

        foreach($definitionAgents as $definitionAgent) {
            foreach($artifactGroups as $groupKey => $artifactsInGroup) {
                $taskProcess = $taskRun->taskProcesses()->create([
                    'name'                     => '',
                    'activity'                 => "Initializing $groupKey...",
                    'status'                   => WorkflowStatesContract::STATUS_PENDING,
                    'task_definition_agent_id' => $definitionAgent?->id,
                ]);

                if ($artifactsInGroup) {
                    $taskProcess->inputArtifacts()->saveMany($artifactsInGroup);
                    $taskProcess->updateRelationCounter('inputArtifacts');
                }

                static::log("Prepared task process w/ " . count($artifactsInGroup) . " artifacts: $taskProcess");

                $taskProcess->getRunner()->prepareProcess();

                $taskProcesses[] = $taskProcess;
            }
        }

        $taskRun->taskProcesses()->saveMany($taskProcesses);

        return $taskProcesses;
    }
}
