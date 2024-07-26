<?php

namespace App\Repositories;

use App\Models\Workflow\Workflow;
use App\Models\Workflow\WorkflowJob;
use App\WorkflowTools\WorkflowInputWorkflowTool;
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
            'assign-agent' => $this->assignAgents($model, $data),
            'set-dependencies' => $this->setDependencies($model, $data),
            default => parent::applyAction($action, $model, $data)
        };
    }

    /**
     * Create a workflow and setup initial dependencies
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
     * Assign an agent to a workflow job
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
     */
    public function setDependencies(WorkflowJob $workflowJob, $dependencies): bool
    {
        $this->assignDependenciesToWorkflowJob($workflowJob, $dependencies);
        // TODO: Remove this in favor of setting workflow job tool directly on workflow jobs
        $this->applyWorkflowInputJobInWorkflow($workflowJob->workflow);
        $this->calculateDependencyLevels($workflowJob->workflow);

        return true;
    }

    /**
     * Assign dependencies to a workflow job avoiding circular dependencies
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
     * TODO: Remove this in favor of user selecting Workflow Job Tool on jobs directly
     *
     * Prepends a Workflow Input job to the workflow if it does not already exist.
     * This will ensure that the workflow input is prepared for the jobs that require it before running the rest of the
     * workflow and provides a mechanism for choosing which parts of the input to group / pass into each job
     */
    public function applyWorkflowInputJobInWorkflow(Workflow $workflow): void
    {
        // If the job already exists, then there is nothing to do
        $workflowInputJob = $workflow->workflowJobs()->firstWhere('name', WorkflowInputWorkflowTool::$toolName);

        if (!$workflowInputJob) {
            $workflowInputJob = $workflow->workflowJobs()->create([
                'name'          => WorkflowInputWorkflowTool::$toolName,
                'workflow_tool' => WorkflowInputWorkflowTool::class,
            ]);
        }

        Log::debug("$workflow prepended $workflowInputJob");
    }

    /**
     * Determines the level of dependencies for each job in the workflow.
     * For example, if job A depends on job B, and job B depends on job C, then job A has a dependency level of 2.
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
