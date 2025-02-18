<?php

namespace App\Http\Controllers\Ai;

use App\Repositories\TaskWorkflowRepository;
use App\Resources\TaskWorkflow\TaskWorkflowResource;
use Newms87\Danx\Http\Controllers\ActionController;

class TaskWorkflowsController extends ActionController
{
    public static string  $repo     = TaskWorkflowRepository::class;
    public static ?string $resource = TaskWorkflowResource::class;
}
