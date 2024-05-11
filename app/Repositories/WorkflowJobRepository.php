<?php

namespace App\Repositories;

use App\Models\Workflow\WorkflowAssignment;
use App\Models\Workflow\WorkflowJob;
use Flytedan\DanxLaravel\Exceptions\ValidationError;
use Flytedan\DanxLaravel\Repositories\ActionRepository;
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
            'assign-agent' => $this->assignAgent($model, $data),
            default => parent::applyAction($action, $model, $data)
        };
    }

    /**
     * Assign an agent to a workflow job
     *
     * @param WorkflowJob $workflowJob
     * @param             $data
     * @return WorkflowAssignment
     * @throws ValidationError
     */
    public function assignAgent(WorkflowJob $workflowJob, $data): WorkflowAssignment
    {
        $agent = team()->agents()->find($data['id']);

        if (!$agent) {
            throw new ValidationError('Agent was not found on your team\'s account.');
        }

        $assignment = $workflowJob->workflowAssignments()->make()->forceFill([
            'agent_id'     => $agent->id,
            'max_attempts' => $data['max_attempts'] ?? 1,
            'is_required'  => $data['is_required'] ?? true,
            'group'        => $data['group'] ?? '',
        ]);
        $assignment->save();

        return $assignment;
    }
}
