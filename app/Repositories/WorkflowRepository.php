<?php

namespace App\Repositories;

use App\Models\Agent\Agent;
use App\Models\Workflow\Workflow;
use App\Models\Workflow\WorkflowJob;
use Flytedan\DanxLaravel\Exceptions\ValidationError;
use Flytedan\DanxLaravel\Repositories\ActionRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class WorkflowRepository extends ActionRepository
{
    public static string $model = Workflow::class;

    /**
     * @param string              $action
     * @param Model|Workflow|null $model
     * @param array|null          $data
     * @return Workflow|WorkflowJob|bool|Model|mixed|null
     * @throws ValidationError
     */
    public function applyAction(string $action, $model = null, ?array $data = null)
    {
        return match ($action) {
            'create' => $this->createWorkflow($data),
            'create-job' => $this->createWorkflowJob($model, $data),
            default => parent::applyAction($action, $model, $data)
        };
    }

    /**
     * @param array $data
     * @return Workflow
     */
    public function createWorkflow(array $data): Model
    {
        $data['team_id'] = team()->id;

        Validator::make($data, [
            'name' => 'required|string|max:80|unique:workflows',
        ])->validate();

        return Workflow::create($data);
    }

    /**
     * @param Workflow $workflow
     * @param          $data
     * @return WorkflowJob
     */
    public function createWorkflowJob(Workflow $workflow, $data): WorkflowJob
    {
        Validator::make($data, [
            'name' => ['required', 'max:80', 'string', Rule::unique('workflow_jobs')->where('workflow_id', $workflow->id)],
        ])->validate();

        return $workflow->workflowJobs()->create($data);
    }

    public function fieldOptions(?array $filter = []): array
    {
        return [
            'agents' => team()->agents->map(fn(Agent $agent) => ['value' => $agent->id, 'label' => $agent->name]),
        ];
    }
}
