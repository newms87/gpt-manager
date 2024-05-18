<?php

namespace App\Repositories;

use App\Models\Agent\Agent;
use App\Models\Shared\InputSource;
use App\Models\Workflow\Workflow;
use App\Models\Workflow\WorkflowJob;
use App\Models\Workflow\WorkflowRun;
use App\Services\Workflow\WorkflowService;
use Flytedan\DanxLaravel\Exceptions\ValidationError;
use Flytedan\DanxLaravel\Repositories\ActionRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class WorkflowRepository extends ActionRepository
{
    public static string $model = Workflow::class;

    /**
     * @param string              $action
     * @param Model|Workflow|null $model
     * @param array|null          $data
     * @return Workflow|WorkflowJob|bool|Model|mixed|null
     * @throws ValidationError|Throwable
     */
    public function applyAction(string $action, $model = null, ?array $data = null)
    {
        return match ($action) {
            'create' => $this->createWorkflow($data),
            'create-job' => $this->createWorkflowJob($model, $data),
            'run-workflow' => $this->runWorkflow($model, $data),
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

    /**
     * @param Workflow $workflow
     * @param          $data
     * @return WorkflowRun
     * @throws Throwable
     * @throws ValidationError
     */
    public function runWorkflow(Workflow $workflow, $data): WorkflowRun
    {
        $inputSourceId = $data['input_source_id'] ?? null;
        $inputSource   = InputSource::find($inputSourceId);

        if (!$inputSource) {
            throw new ValidationError('Input Source was not found');
        }

        $workflowRun = $workflow->workflowRuns()->create([
            'input_source_id' => $inputSourceId,
            'status'          => WorkflowRun::STATUS_PENDING,
        ]);

        WorkflowService::start($workflowRun);

        return $workflowRun;
    }
}
