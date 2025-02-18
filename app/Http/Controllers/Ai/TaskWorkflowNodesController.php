<?php

namespace App\Http\Controllers\Ai;

use App\Repositories\TaskWorkflowNodeRepository;
use App\Resources\TaskWorkflow\TaskWorkflowNodeResource;
use Newms87\Danx\Http\Controllers\ActionController;

class TaskWorkflowNodesController extends ActionController
{
    public static string  $repo     = TaskWorkflowNodeRepository::class;
    public static ?string $resource = TaskWorkflowNodeResource::class;
}
