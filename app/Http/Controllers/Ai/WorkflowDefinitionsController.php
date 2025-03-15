<?php

namespace App\Http\Controllers\Ai;

use App\Repositories\WorkflowDefinitionRepository;
use App\Resources\Workflow\WorkflowDefinitionResource;
use Newms87\Danx\Http\Controllers\ActionController;

class WorkflowDefinitionsController extends ActionController
{
    public static string  $repo     = WorkflowDefinitionRepository::class;
    public static ?string $resource = WorkflowDefinitionResource::class;
}
