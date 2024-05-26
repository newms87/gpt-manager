<?php

namespace App\Repositories;

use App\Models\Agent\Agent;
use App\Models\Shared\InputSource;
use App\Models\Workflow\Workflow;
use App\Models\Workflow\WorkflowJob;
use App\Models\Workflow\WorkflowRun;
use App\Services\Workflow\WorkflowService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Repositories\ActionRepository;
use Throwable;

class WorkflowRepository extends ActionRepository
{
    public static string $model = Workflow::class;

    public function query(): Builder
    {
        return parent::query()->where('team_id', team()->id);
    }

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
            'create-job' => app(WorkflowJobRepository::class)->create($model, $data),
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
     * @param array|null $filter
     * @return array
     */
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
        ]);

        WorkflowService::start($workflowRun);

        return $workflowRun;
    }
}
