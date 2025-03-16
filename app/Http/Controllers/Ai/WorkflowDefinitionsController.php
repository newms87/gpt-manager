<?php

namespace App\Http\Controllers\Ai;

use App\Models\Workflow\WorkflowDefinition;
use App\Repositories\WorkflowDefinitionRepository;
use App\Resources\Workflow\WorkflowDefinitionResource;
use App\Services\Workflow\ImportExportWorkflowService;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Http\Controllers\ActionController;

class WorkflowDefinitionsController extends ActionController
{
    public static string  $repo     = WorkflowDefinitionRepository::class;
    public static ?string $resource = WorkflowDefinitionResource::class;

    public function exportToJson(WorkflowDefinition $workflowDefinition)
    {
        if ($workflowDefinition->team_id !== team()->id) {
            throw new ValidationError('You do not have permission to export this workflow');
        }

        return app(ImportExportWorkflowService::class)->exportToJson($workflowDefinition);
    }
}
