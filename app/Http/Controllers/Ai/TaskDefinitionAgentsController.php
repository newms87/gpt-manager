<?php

namespace App\Http\Controllers\Ai;

use App\Repositories\TaskDefinitionAgentRepository;
use App\Resources\TaskDefinition\TaskDefinitionAgentResource;
use Newms87\Danx\Http\Controllers\ActionController;

class TaskDefinitionAgentsController extends ActionController
{
    public static string  $repo     = TaskDefinitionAgentRepository::class;
    public static ?string $resource = TaskDefinitionAgentResource::class;
}
