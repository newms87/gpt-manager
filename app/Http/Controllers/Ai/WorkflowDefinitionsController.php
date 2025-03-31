<?php

namespace App\Http\Controllers\Ai;

use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowInput;
use App\Repositories\WorkflowDefinitionRepository;
use App\Resources\Workflow\WorkflowDefinitionResource;
use App\Services\Workflow\WorkflowExportService;
use App\Services\Workflow\WorkflowImportService;
use App\Services\Workflow\WorkflowRunnerService;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Http\Controllers\ActionController;
use Newms87\Danx\Requests\PagerRequest;

class WorkflowDefinitionsController extends ActionController
{
    public static string  $repo     = WorkflowDefinitionRepository::class;
    public static ?string $resource = WorkflowDefinitionResource::class;

    public function invoke(WorkflowDefinition $workflowDefinition, PagerRequest $request)
    {
        $payload       = $request->getJson('payload');
        $input         = $payload['input'] ?? [];
        $workflowInput = WorkflowInput::create($input);

        $workflowRun = WorkflowRunnerService::start($workflowDefinition, [$workflowInput->toArtifact()]);

        $workflowRun->workflowApiInvocation()->create([
            'name'        => $payload['name'] ?? 'No Name',
            'webhook_url' => $payload['webhook_url'] ?? '',
            'payload'     => $payload,
        ]);
    }

    public function exportToJson(WorkflowDefinition $workflowDefinition)
    {
        if ($workflowDefinition->team_id !== team()->id) {
            throw new ValidationError('You do not have permission to export this workflow');
        }

        return app(WorkflowExportService::class)->exportToJson($workflowDefinition);
    }

    public function importFromJson(PagerRequest $pagerRequest): array
    {
        $json = $pagerRequest->getJson('workflowDefinitionJson');

        return WorkflowDefinitionResource::make(app(WorkflowImportService::class)->importFromJson($json));
    }
}
