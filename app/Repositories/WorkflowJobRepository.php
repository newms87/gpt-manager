<?php

namespace App\Repositories;

use App\Models\Workflow\Workflow;
use App\Models\Workflow\WorkflowAssignment;
use App\Models\Workflow\WorkflowJob;
use App\WorkflowTools\TranscodeWorkflowInputWorkflowTool;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Repositories\ActionRepository;

class WorkflowJobRepository extends ActionRepository
{
    public static string $model = WorkflowJob::class;

    /**
     * @param string                  $action
     * @param Model|WorkflowJob |null $model
     * @param array|null              $data
     * @return WorkflowJob|bool|Model|mixed|null
     * @throws ValidationError
     */
    public function applyAction(string $action, $model = null, ?array $data = null)
    {
        return match ($action) {
            'create' => $this->create(Workflow::find($data['workflow_id']), $data),
            'update' => $this->update($model, $data),
            'assign-agent' => $this->assignAgents($model, $data),
            'set-dependencies' => $this->setDependencies($model, $data),
            default => parent::applyAction($action, $model, $data)
        };
    }

    /**
     * @param Workflow $workflow
     * @param          $data
     * @return WorkflowJob
     * @throws ValidationError
     */
    public function create(Workflow $workflow, $data): WorkflowJob
    {
        $workflowJob = $workflow->workflowJobs()->make($data);
        $workflowJob->validate();
        $workflowJob->save();
        $this->setDependencies($workflowJob, $data['dependencies'] ?? []);

        return $workflowJob;
    }

    /**
     * @param WorkflowJob $workflowJob
     * @param             $data
     * @return true
     */
    public function update(WorkflowJob $workflowJob, $data): true
    {
        $workflowJob->update($data);

        if ($workflowJob->wasChanged('use_input')) {
            $this->applyTranscodeWorkflowInputJobInWorkflow($workflowJob->workflow);
            $this->calculateDependencyLevels($workflowJob->workflow);
        }

        return true;
    }

    /**
     * Assign an agent to a workflow job
     *
     * @param WorkflowJob $workflowJob
     * @param             $data
     * @return WorkflowAssignment[]
     * @throws ValidationError
     */
    public function assignAgents(WorkflowJob $workflowJob, $data): array
    {
        $agents = team()->agents()->whereIn('id', $data['ids'])->get();

        if ($agents->isEmpty()) {
            throw new ValidationError('No agents were not found matching the list provided on your team\'s account.');
        }

        $assignments = [];
        foreach($agents as $agent) {
            $assignment = $workflowJob->workflowAssignments()->make()->forceFill([
                'agent_id'     => $agent->id,
                'max_attempts' => $data['max_attempts'] ?? 1,
                'is_required'  => $data['is_required'] ?? true,
            ]);
            $assignment->save();
            $assignments[] = $assignment;
        }

        return $assignments;
    }

    /**
     * Set dependencies for a workflow job and calculate the dependency levels for the workflow
     *
     * @param WorkflowJob $workflowJob
     * @param             $dependencies
     * @return bool
     * @throws ValidationError
     */
    public function setDependencies(WorkflowJob $workflowJob, $dependencies): bool
    {
        $this->assignDependenciesToWorkflowJob($workflowJob, $dependencies);
        $this->applyTranscodeWorkflowInputJobInWorkflow($workflowJob->workflow);
        $this->calculateDependencyLevels($workflowJob->workflow);

        return true;
    }

    /**
     * Assign dependencies to a workflow job avoiding circular dependencies
     *
     * @param WorkflowJob $workflowJob
     * @param             $dependencies
     * @return void
     * @throws ValidationError
     */
    public function assignDependenciesToWorkflowJob(WorkflowJob $workflowJob, $dependencies): void
    {
        foreach($dependencies as $dependency) {
            $dependencyJob = WorkflowJob::find($dependency['depends_on_id']);
            if ($this->isCircularDependency($workflowJob, $dependencyJob)) {
                throw new ValidationError("A circular dependency was detected: $workflowJob is already depended on by $dependencyJob");
            }

            $dependsOnWorkflowJob = $workflowJob->dependencies->where('depends_on_workflow_job_id', $dependency['depends_on_id'])->first();
            if (!$dependsOnWorkflowJob) {
                $dependsOnWorkflowJob = $workflowJob->dependencies()->make()->forceFill([
                    'depends_on_workflow_job_id' => $dependency['depends_on_id'],
                ]);
            }
            $dependsOnWorkflowJob->group_by       = $dependency['group_by'] ?? [];
            $dependsOnWorkflowJob->include_fields = $dependency['include_fields'] ?? [];
            $dependsOnWorkflowJob->save();
        }

        // Remove any dependencies that were not included in the request
        $workflowJob->dependencies()->whereNotIn('depends_on_workflow_job_id', collect($dependencies)->pluck('depends_on_id'))->delete();
    }

    /**
     * Checks if a circular dependency exists between the given job and the dependencies of another job to be depended
     * on
     *
     * @param WorkflowJob $workflowJob
     * @param WorkflowJob $dependencyJob
     * @return bool
     */
    public function isCircularDependency(WorkflowJob $workflowJob, WorkflowJob $dependencyJob): bool
    {
        // Check for self dependency
        if ($workflowJob->id === $dependencyJob->id) {
            return true;
        }

        foreach($dependencyJob->dependencies()->get() as $childDependency) {
            if ($this->isCircularDependency($workflowJob, $childDependency->dependsOn)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Prepends a Transcode Workflow Input job to the workflow if it does not already exist.
     * This will ensure that the workflow input is prepared for the jobs that require it before running the rest of the
     * workflow. The Transcode Workflow Input job will be a dependency for all jobs that require the workflow input (ie:
     * use_input).
     *
     * @param Workflow $workflow
     * @return void
     */
    public function applyTranscodeWorkflowInputJobInWorkflow(Workflow $workflow): void
    {
        // If the job already exists, then there is nothing to do
        $transcodeWorkflowInputJob = $workflow->workflowJobs()->firstWhere('name', TranscodeWorkflowInputWorkflowTool::$toolName);

        if (!$transcodeWorkflowInputJob) {
            $transcodeWorkflowInputJob = $workflow->workflowJobs()->create([
                'name'          => TranscodeWorkflowInputWorkflowTool::$toolName,
                'use_input'     => true,
                'workflow_tool' => TranscodeWorkflowInputWorkflowTool::class,
            ]);
        }

        $workflowJobs = $workflow->workflowJobs()->get();

        // Only make the Workflow input a dependency for the jobs that require it
        foreach($workflowJobs as $workflowJob) {
            // Skip assigning the workflow input job to itself
            if ($workflowJob->id === $transcodeWorkflowInputJob?->id) {
                continue;
            }

            $workflowInputDependency = $workflowJob->dependencies()->firstWhere('depends_on_workflow_job_id', $transcodeWorkflowInputJob->id);
            if ($workflowJob->use_input) {
                if (!$workflowInputDependency) {
                    $workflowJob->dependencies()->create([
                        'depends_on_workflow_job_id' => $transcodeWorkflowInputJob->id,
                    ]);
                }
            } else {
                $workflowInputDependency?->delete();
            }
        }

        Log::debug("$workflow prepended $transcodeWorkflowInputJob");
    }

    /**
     * Determines the level of dependencies for each job in the workflow.
     * For example, if job A depends on job B, and job B depends on job C, then job A has a dependency level of 2.
     * @param Workflow $workflow
     * @return void
     */
    public function calculateDependencyLevels(Workflow $workflow): void
    {
        $jobs = $workflow->workflowJobs()->with(['dependencies'])->get();
        foreach($jobs as $job) {
            $job->dependency_level = $this->getDependencyLevel($job, $jobs);
            $job->save();
        }
    }

    /**
     * @param WorkflowJob              $job  The job to calculate the dependency level
     * @param WorkflowJob[]|Collection $jobs All the jobs in the workflow for referencing
     * @return int
     */
    public function getDependencyLevel(WorkflowJob $job, $jobs): int
    {
        if ($job->dependencies->isEmpty()) {
            return $job->workflow_tool ? 0 : 1;
        }

        $maxLevel = 1;
        foreach($job->dependencies as $dependency) {
            $dependsOnJob = $jobs->where('id', $dependency->depends_on_workflow_job_id)->first();

            // TODO: Make this more robust and throw errors
            // Detect a single-level circular dependency just in case something went wrong, and ignore with a warning for now
            if ($dependsOnJob->id === $job->id) {
                Log::warning("Circular dependency detected");
                continue;
            }

            $level    = $this->getDependencyLevel($dependsOnJob, $jobs);
            $maxLevel = max($maxLevel, $level);
        }

        return $maxLevel + 1;
    }
}
