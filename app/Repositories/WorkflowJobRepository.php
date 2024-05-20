<?php

namespace App\Repositories;

use App\Models\Workflow\WorkflowAssignment;
use App\Models\Workflow\WorkflowJob;
use Flytedan\DanxLaravel\Exceptions\ValidationError;
use Flytedan\DanxLaravel\Repositories\ActionRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

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
            'assign-agent' => $this->assignAgents($model, $data),
            'set-dependencies' => $this->setDependencies($model, $data),
            default => parent::applyAction($action, $model, $data)
        };
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
     * Set dependencies for a workflow job
     *
     * @param WorkflowJob $workflowJob
     * @param             $dependencies
     * @return Collection
     */
    public function setDependencies(WorkflowJob $workflowJob, $dependencies): Collection
    {
        foreach($dependencies as $dependency) {
            $dependsOnWorkflowJob = $workflowJob->dependencies->where('depends_on_workflow_job_id', $dependency['depends_on_id'])->first();
            if (!$dependsOnWorkflowJob) {
                $dependsOnWorkflowJob = $workflowJob->dependencies()->make()->forceFill([
                    'depends_on_workflow_job_id' => $dependency['depends_on_id'],
                ]);
            }
            $dependsOnWorkflowJob->group_by = $dependency['group_by'] ?? null;
            $dependsOnWorkflowJob->save();
        }

        // Remove any dependencies that were not included in the request
        $workflowJob->dependencies()->whereNotIn('depends_on_workflow_job_id', collect($dependencies)->pluck('depends_on_id'))->delete();

        return $workflowJob->dependencies()->get();
    }
}
