<?php

namespace App\Http\Controllers\Ai;

use App\Repositories\TaskWorkflowConnectionRepository;
use App\Resources\TaskWorkflow\TaskWorkflowConnectionResource;
use Newms87\Danx\Http\Controllers\ActionController;

class TaskWorkflowConnectionsController extends ActionController
{
    public static string  $repo     = TaskWorkflowConnectionRepository::class;
    public static ?string $resource = TaskWorkflowConnectionResource::class;
}
