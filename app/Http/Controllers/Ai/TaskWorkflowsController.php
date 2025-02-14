<?php

namespace App\Http\Controllers\Ai;

use App\Repositories\TaskWorkflowRepository;
use App\Resources\TaskDefinition\TaskDefinitionResource;
use Newms87\Danx\Http\Controllers\ActionController;

class TaskWorkflowsController extends ActionController
{
    public static string  $repo     = TaskWorkflowRepository::class;
    public static ?string $resource = TaskDefinitionResource::class;
}
